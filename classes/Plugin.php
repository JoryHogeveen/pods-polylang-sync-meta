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
}
