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
	public $admin = null;

	/**
	 * @var Pods_Polylang_Sync_Meta\Translator
	 */
	public $translator = null;

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

		include 'classes/data.php';

		/**
		 * Admin options.
		 */
		include 'classes/admin.php';
		$this->admin = Pods_Polylang_Sync_Meta\Admin::get_instance();

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
		 * -- Docs from Polylang --
		 * Filter a meta value before is copied or synchronized
		 *
		 * @since 2.3
		 *
		 * @param mixed  $value Meta value
		 * @param string $key   Meta key
		 * @param string $lang  Language of target
		 * @param int    $from  Id of the source
		 * @param int    $to    Id of the target
		 */
		add_filter( 'pll_translate_post_metas', array( $this, 'filter_pll_translate_post_metas' ), 99999, 5 );
		add_filter( 'pll_translate_term_metas', array( $this, 'filter_pll_translate_term_metas' ), 99999, 5 );
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
			include 'classes/translator.php';
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
		$post = get_post( $from );
		$pod = pods( $post );

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
		$term = get_term( $from );
		$pod = pods( $term );

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

		foreach ( $pod->fields() as $key => $data ) {
			if ( ! $this->translator()->is_field_sync_enabled( $data ) ) {
				// No options available or sync is turned off for this field.
				unset( $keys[ $key ] );
			}
		}
		return $keys;
	}

	/**
	 * @param mixed  $value Meta value
	 * @param string $key   Meta key
	 * @param string $lang  Language of target
	 * @param int    $from  Id of the source
	 * @param int    $to    Id of the target
	 *
	 * @return mixed
	 */
	public function filter_pll_translate_post_metas( $value, $key, $lang, $from, $to ) {
		$post = get_post( $from );
		$pod = pods( $post );

		if ( $pod->exists() ) {
			$value = $this->translate_meta( $value, $key, $pod, $lang );
		}
		return $value;
	}

	/**
	 * @param mixed  $value Meta value
	 * @param string $key   Meta key
	 * @param string $lang  Language of target
	 * @param int    $from  Id of the source
	 * @param int    $to    Id of the target
	 *
	 * @return mixed
	 */
	public function filter_pll_translate_term_metas( $value, $key, $lang, $from, $to ) {
		$term = get_term( $from );
		$pod = pods( $term );

		if ( $pod->exists() ) {
			$value = $this->translate_meta( $value, $key, $pod, $lang );
		}
		return $value;
	}

	/**
	 * @param mixed  $value Meta value
	 * @param string $key   Meta key
	 * @param Pods   $pod  The pod object.
	 * @param string $lang  Language of target
	 *
	 * @return mixed
	 */
	public function translate_meta( $value, $key, $pod, $lang ) {

		$field = $pod->fields( $key );
		if ( $field && $this->translator()->is_field_sync_enabled( $field ) ) {
			$value = $this->translator()->get_meta_translations( $value, $lang, $field );
		}

		return $value;
	}
}
