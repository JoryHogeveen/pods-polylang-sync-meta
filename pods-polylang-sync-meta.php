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

	public $translatable_field_types = array(
		'pick',
		'file',
	);

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

		/**
		 * Admin options.
		 */
		include 'classes/admin.php';
		$this->admin = Pods_Polylang_Sync_Meta\Admin::get_instance();

		include 'classes/translator.php';
		$this->translator = Pods_Polylang_Sync_Meta\Translator::get_instance();
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
			if ( ! $this->sync->is_field_sync_enabled( $data ) ) {
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
		if ( $field && $this->sync->is_field_sync_enabled( $field ) ) {
			$field_type = pods_v( 'type', $field );
			if ( in_array( $field_type, $this->translatable_field_types, true ) ) {
				$value = $this->sync->get_meta_translations( $value, $lang, $field );
			}
		}

		return $value;
	}

	/**
	 * @param array  $metas
	 * @param bool   $sync
	 * @param int    $from
	 * @param int    $to
	 * @param string $to_lang
	 */
	protected function sync_meta_translations( $metas, $sync, $from, $to, $to_lang = '' ) {
		/*if ( $sync !== true ) {
			return;
		}
		if ( ! in_array( get_post_type( $to ), $this->sync_post_types ) ) {
			return;
		}*/

		$cur_lang = $this->get_obj_language( $from );
		if ( ! $cur_lang ) {
			return;
		}

		// @todo TEMP, only sync from default language!
		/*if ( pll_default_language() != $cur_lang ) {
			return;
		}*/

		$obj_meta = $this->get_obj_metadata( $from ); // Get metadata from post.

		if ( $to_lang && $to ) {
			$translations = array( $to_lang => $to );
		} else {
			$translations = $this->get_obj_translations( $from ); // Get translations from post.
		}

		// Remove the existing language from the loop.
		unset( $translations[ $cur_lang ] );

		// Loop through translations
		foreach ( $translations as $lang => $translation_id ) {
			if ( $to_lang && $lang !== $to_lang ) {
				continue;
			}
			//if ( $translation_id != $to ) {
			foreach ( $metas as $meta ) {
				// Loop through all enabled meta fields.
				if ( isset( $obj_meta[ $meta ] ) ) {
					//echo $meta.'<br>';
					//print_r($obj_meta[ $meta ]);

					$pod_field = $this->cur_pod->fields( $meta );

					// Get metafield translations
					$translation_meta = $this->get_meta_translations( $obj_meta[ $meta ], $lang, $pod_field );

					if ( null === $translation_meta ) {
						continue;
					}

					// @todo Remove?? This is handled by Polylang: Fix for thumbnail (get_posts_custom returns all serialized)
					if ( '_thumbnail_id' === $meta && isset( $translation_meta[0] ) ) {
						$translation_meta = $translation_meta[0];
					}

					// Fix for single rel fields.
					if ( is_array( $translation_meta ) && 1 === count( $translation_meta ) ) {
						$translation_meta = reset( $translation_meta );
					}

					// http://pods.io/docs/code/pods/save/
					//$pod = pods( get_post_type( $to ), $to );
					//$pod->save( $meta, $translation_meta );

					// Update translation with translated metadata
					$this->update_obj_meta( $translation_id, $meta, $translation_meta );
				}
			}
			//}
		}

	}

}
