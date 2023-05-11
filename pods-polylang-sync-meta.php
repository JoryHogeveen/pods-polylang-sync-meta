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
}
