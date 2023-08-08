<?php
/**
 * Requires: Pods and Polylang 1.9.2+
 *
 * Plugin Name: Pods Polylang Sync Meta
 * Plugin URI: https://github.com/JoryHogeveen/pods-polylang-sync-meta/
 * Version: 1.0-beta
 * Author: Jory Hogeveen
 * Author uri: https://www.keraweb.nl
 * Description: Syncs relationship meta fields and automatically creates translation if this is needed
 * Text Domain: pods-polylang-sync-meta
 * Domain Path: /languages
 */

! defined( 'ABSPATH' ) and die();

function pods_polylang_sync_meta() {
	return Pods_Polylang_Sync_Meta::get_instance();
}
pods_polylang_sync_meta();

class Pods_Polylang_Sync_Meta
{
	CONST DOMAIN = 'pods-polylang-sync-meta';

	private static $instance = null;

	/**
	 * @var Pods_Polylang_Sync_Meta\Admin
	 */
	private $admin = null;

	/**
	 * @var Pods_Polylang_Sync_Meta\Meta
	 */
	private $meta = null;

	/**
	 * @var Pods_Polylang_Sync_Meta\Translator
	 */
	private $translator = null;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_loaded', array( $this, 'init' ) );
	}

	/**
	 * Set hooks and check plugins.
	 */
	public function init() {

		if ( ! $this->validate() ) {
			return;
		}

		include 'classes/Plugin.php';
		include 'classes/Data.php';

		/**
		 * Admin options.
		 */
		include 'classes/Admin.php';
		$this->admin = Pods_Polylang_Sync_Meta\Admin::get_instance();

		/**
		 * Meta handlers.
		 */
		include 'classes/Meta.php';
		$this->meta = Pods_Polylang_Sync_Meta\Meta::get_instance();

		/**
		 * -- Docs from Polylang --
		 * Filter the custom fields to copy or synchronize
		 *
		 * @since 0.6
		 * @since 1.9.2 The `$from`, `$to`, `$lang` parameters were added.
		 *
		 * @param array  $keys list of custom fields names
		 * @param bool   $sync true if it is synchronization, false if it is a copy
		 * @param int    $from id of the post from which we copy information
		 * @param int    $to   id of the post to which we paste information
		 * @param string $lang language slug
		 */
		add_filter( 'pll_copy_post_metas', array( $this, 'filter_pll_copy_post_metas' ), 99999, 5 );
		add_filter( 'pll_copy_term_metas', array( $this, 'filter_pll_copy_term_metas' ), 99999, 5 );

		/**
		 * Filter used to store draft metadata when creating a new post translation.
		 */
		add_filter( 'use_block_editor_for_post', array( $this, 'new_post_translation' ), 99999 ); // After polylang.
	}

	/**
	 * Check is the functionality should be activated.
	 * @return bool
	 */
	public function validate() {

		// @todo Also run on front-end?
		if ( ! is_admin() ) {
			return false;
		}

		if (
			! function_exists( 'PLL' )
			|| ! property_exists( PLL(), 'model' )
			|| ! property_exists( PLL(), 'filters_media' )
			|| ! function_exists( 'pll_get_post_translations' )
			|| ! function_exists( 'pll_is_translated_post_type' )
			|| ! function_exists( 'pods' )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the translator class.
	 * @return \Pods_Polylang_Sync_Meta\Translator|null
	 */
	public function translator() {
		if ( ! $this->translator ) {
			include 'classes/Translator.php';
			$this->translator = Pods_Polylang_Sync_Meta\Translator::get_instance();
		}
		return $this->translator;
	}

	/**
	 * @param array  $keys list of custom fields names
	 * @param bool   $sync true if it is synchronization, false if it is a copy
	 * @param int    $from id of the post from which we copy information
	 * @param int    $to   id of the post to which we paste information
	 * @param string $lang language slug
	 *
	 * @return array
	 */
	public function filter_pll_copy_post_metas( $keys, $sync, $from, $to, $lang ) {
		$pod = $this->translator()->get_pod( $from, 'post' );

		if ( $pod->exists() ) {
			return $this->remove_pods_meta_keys( $keys, $pod );
		}

		return $keys;
	}

	/**
	 * @param array  $keys list of custom fields names
	 * @param bool   $sync true if it is synchronization, false if it is a copy
	 * @param int    $from id of the post from which we copy information
	 * @param int    $to   id of the post to which we paste information
	 * @param string $lang language slug
	 *
	 * @return array
	 */
	public function filter_pll_copy_term_metas( $keys, $sync, $from, $to, $lang ) {
		$pod = $this->translator()->get_pod( $from, 'term' );

		if ( $pod->exists() ) {
			return $this->remove_pods_meta_keys( $keys, $pod );
		}

		return $keys;
	}

	/**
	 * @param array  $keys list of custom fields names
	 * @param Pods  $pod  The pod object.
	 *
	 * @return array
	 */
	public function remove_pods_meta_keys( $keys, $pod ) {
		$fields = $this->translator()->get_pod_fields( $pod );
		if ( ! $fields ) {
			return $keys;
		}

		foreach ( $fields as $key => $data ) {
			// Do not let Polylang handle meta sync for Pods fields.
			unset( $keys[ $key ] );
			$value = array_search( $key, $keys, true );
			if ( $value ) {
				unset( $keys[ $value ] );
			}
		}

		return $keys;
	}

	/**
	 * Copy Pods fields when creating a new translation.
	 * @see \PLL_Admin_Sync::new_post_translation()
	 * @param array $data An array of slashed post data.
	 * @return array
	 */
	public function new_post_translation( $is_block_editor ) {
		global $post;
		static $done = array();

		if ( $this->translator()->is_new_post_page() && $this->translator()->is_translation_enabled( $post->post_type, 'post_type' ) ) {
			check_admin_referer( 'new-post-translation' );

			$from_post_id = (int) $_GET['from_post'];
			if ( ! empty( $done[ $from_post_id ] ) ) {
				return $is_block_editor;
			}
			$done[ $from_post_id ] = true; // Avoid a second duplication in the block editor. Using an array only to allow multiple phpunit tests.

			$pod = pods( $post->post_type, $from_post_id );
			if ( ! $pod || ! $pod->exists() ) {
				return $is_block_editor;
			}

			$fields = $this->translator()->get_pod_fields( $pod ) ;
			if ( $fields ) {
				foreach ( $fields as $field ) {
					if ( $this->translator()->is_field_sync_enabled( $field ) ) {

						$single = null;
						if ( is_callable( array( $field, 'is_multi_value' ) ) ) {
							$single = ! $field->is_multi_value();
						} else if ( is_callable( array( $field, 'get_limit' ) ) ) {
							$single = 1 === $field->get_limit();
						}

						$value = $pod->field( $field->get_name(), (bool) $single, array( 'raw' => true, 'output' => 'ids' ) );
						$translated_value = $this->translator()->get_meta_translation( $value, $_GET['new_lang'], $field );

						if ( null === $single && is_array( $translated_value ) && 1 === count( $translated_value ) ) {
							$translated_value = reset( $translated_value );
						}

						update_post_meta( $post->ID, $field->get_name(), $translated_value );
					}
				}
			}
		}

		return $is_block_editor;
	}
}
