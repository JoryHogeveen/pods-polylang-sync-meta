<?php

namespace Pods_Polylang_Sync_Meta;

class Plugin extends Data
{
	private static $_instance = null;

	private $sync = array();

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	public function get_pll_option( $key ) {
		$options = PLL()->options;
		return ( isset( $options[ $key ] ) ) ? $options[ $key ] : null;
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @param string $field
	 * @return string
	 */
	public function get_obj_language( $id, $type = '', $field = 'slug' ) {
		$lang = '';
		switch ( $type ) {
			case 'post':
			case 'post_type':
				$lang = pll_get_post_language( $id, $field );
			break;
			case 'term':
			case 'taxonomy':
				$lang = pll_get_term_language( $id, $field );
			break;
		}
		return $lang;
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return array
	 */
	public function get_obj_translations( $id, $type = '' ) {
		$translations = array();
		switch ( $type ) {
			case 'post':
			case 'post_type':
				$translations = pll_get_post_translations( $id );
			break;
			case 'term':
			case 'taxonomy':
				$translations = pll_get_term_translations( $id );
			break;
		}
		return $translations;
	}

	public function is_translation_enabled( $type, $obj_type = '' ) {

		switch ( $obj_type ) {
			case 'post':
			case 'post_type':
				if ( pll_is_translated_post_type( $type ) ) {
					return true;
				}
			break;
			case 'term':
			case 'taxonomy':
				if ( pll_is_translated_taxonomy( $type ) ) {
					return true;
				}
			break;
			case 'media':
			case 'attachment':
				if ( $this->get_pll_option( 'media_support' ) ) {
					return true;
				}
			break;
		}

		return false;
	}

	public function save_translations( $obj_type, $id, $lang, $translations ) {

		switch ( $obj_type ) {
			case 'post':
			case 'post_type':
				// Save the translations.
				pll_set_post_language( $id, $lang );
				pll_save_post_translations( $translations );
			break;
			case 'term':
			case 'taxonomy':
				// Save the translations.
				pll_set_term_language( $id, $lang );
				pll_save_post_translations( $translations );
			break;
			case 'media':
			case 'attachment':
				if ( $this->get_pll_option( 'media_support' ) ) {
					return true;
				}
			break;
		}

		return false;
	}

	public function create_media_translation( $attachment, $lang ) {

		add_filter( 'pll_enable_duplicate_media', '__return_false', 99 );

		// Make sure metadata exists.
		wp_maybe_generate_attachment_metadata( $attachment );

		$src_language = pll_get_post_language( $attachment->ID );

		$new_id = $attachment->ID;

		if ( ! empty( $src_language ) && $lang !== $src_language ) {
			if ( isset( PLL()->posts ) && is_callable( array( PLL()->posts, 'create_media_translation' ) ) ) {
				$tr_id = PLL()->posts->create_media_translation( $new_id, $lang );
			} elseif ( isset( PLL()->filters_media ) && is_callable( array( PLL()->filters_media, 'create_media_translation' ) ) ) {
				// Fix for older Polylang Pro -> polylang/modules/media/admin-advanced-media.php // classname: PLL_Admin_Advanced_Media
				remove_action( 'add_attachment', array( PLL()->advanced_media, 'duplicate_media' ), 20 ); // After default add (20)
				$tr_id = PLL()->filters_media->create_media_translation( $new_id, $lang );
				add_action( 'add_attachment', array( PLL()->advanced_media, 'duplicate_media' ), 20 ); // After default add (20)
			}

			if ( $tr_id ) {
				$new_id = $tr_id;
				/*$post   = get_post( $tr_id );
				$new_id = $post->ID;
				if ( $post ) {
					wp_maybe_generate_attachment_metadata( $post );
				}*/
			}
		}

		remove_filter( 'pll_enable_duplicate_media', '__return_false', 99 );

		return $new_id;
	}
}
