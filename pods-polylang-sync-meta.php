<?php
/**
 * Requires: Pods and Polylang 1.9.2+
 *
 * Plugin Name: Pods Polylang Sync Meta
 * Plugin URI: https://github.com/JoryHogeveen/pods-polylang-sync-meta/
 * Version: 0.2.3
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

	//public $file_meta_keys = array( ); //'_thumbnail_id'
	//public $sync_post_types = array( 'product' ); //'post', 'page',
	public $pod_field_sync_option = 'pods_polylang_sync_meta';

	/**
	 * @var Pods
	 */
	public $cur_pod = null;

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

		if ( ! is_admin() ) {
			return;
		}

		if (
			! function_exists( 'PLL' )
			|| ! property_exists( PLL(), 'model' )
			|| ! property_exists( PLL(), 'filters_media' )
			|| ! function_exists( 'pll_get_post_translations' )
			|| ! function_exists( 'pll_is_translated_post_type' )
			|| ! function_exists( 'pods' )
		) {
			return;
		}

		// @todo Redo save_post handler?
		//add_filter( 'save_post', array( $this, 'maybe_sync_meta' ), 99999, 3 );

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
		add_filter( 'pll_copy_post_metas', array( $this, 'maybe_sync_post_meta' ), 99999, 5 );
		add_filter( 'pll_copy_term_metas', array( $this, 'maybe_sync_term_meta' ), 99999, 5 );

		add_filter( 'pods_admin_setup_edit_field_options', array( $this, 'pods_edit_field_options' ), 12, 2 );

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
	 * @param array $options
	 * @param array $pod
	 * @return array
	 */
	public function pods_edit_field_options( $options, $pod ) {

		if ( in_array( $this->get_pod_type( $pod ), array( 'post_type', 'taxonomy', 'media' ) ) ) {
			//$analysis_field_types = $this->get_sync_field_types();

			if ( ! $this->is_translation_enabled( $pod ) ) {
				return $options;
			}

			$pod_major_version = substr( PODS_VERSION, 0, 3 );

			if ( version_compare( $pod_major_version, '2.8', '>=' ) ) {

				$options['advanced']['polylang'] = array(
					'name' => 'polylang',
					'label' => __( 'Polylang', self::DOMAIN ),
					'type' => 'heading',
				);

				$options['advanced'][ $this->pod_field_sync_option ] = array(
					'label' => __( 'Enable meta field sync', self::DOMAIN ),
					'name'  => $this->pod_field_sync_option,
					'type'  => 'boolean',
					'help'  => '',
					/*'depends-on' => array(
						'type' => $analysis_field_types,
					),*/
				);

			} else {
				// Pre 2.8.
				$options['advanced'][ __( 'Polylang', self::DOMAIN ) ] = array(
					$this->pod_field_sync_option => array(
						'label' => __( 'Enable meta field sync', self::DOMAIN ),
						'type'  => 'boolean',
						'help'  => '',
						/*'depends-on' => array(
							'type' => $analysis_field_types,
						),*/
					),
				);
			}
		}
		return $options;
	}

	/**
	 * Get Pod object type.
	 * @param  Pods  $pod
	 * @return string
	 */
	public function get_pod_type( $pod = null ) {
		if ( ! $pod ) {
			$pod = $this->cur_pod;
		}
		if ( ! $pod ) {
			return '';
		}

		$type = pods_v( 'type', $pod, '' );
		if ( ! $type ) {
			$type = ( isset( $pod->pod_data ) ) ? pods_v( 'type', $pod->pod_data, '' ) : '';
		}

		return (string) $type;
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return array
	 */
	public function get_obj_metadata( $id, $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type();
		switch( $type ) {
			case 'post':
			case 'post_type':
				return get_post_meta( $id );
				break;
			case 'term':
			case 'taxonomy':
				return get_term_meta( $id );
				break;
		}
		return array();
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return string
	 */
	public function get_obj_language( $id, $field = 'slug', $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type();
		switch( $type ) {
			case 'post':
			case 'post_type':
				return pll_get_post_language( $id, $field );
				break;
			case 'term':
			case 'taxonomy':
				return pll_get_term_language( $id, $field );
				break;
		}
		return '';
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return array
	 */
	public function get_obj_translations( $id, $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type();
		switch( $type ) {
			case 'post':
			case 'post_type':
				return pll_get_post_translations( $id );
				break;
			case 'term':
			case 'taxonomy':
				return pll_get_term_translations( $id );
				break;
		}
		return array();
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return mixed
	 */
	public function update_obj_meta( $id, $key, $value, $prev = '', $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type();
		switch( $type ) {
			case 'post':
			case 'post_type':
				return update_post_meta( $id, $key, $value, $prev );
				break;
			case 'term':
			case 'taxonomy':
				return update_term_meta( $id, $key, $value, $prev );
				break;
		}
		return null;
	}

	/**
	 * @param  int|object|array $type
	 * @param  string           $obj_type
	 * @return bool
	 */
	public function is_translation_enabled( $type, $obj_type = null ) {
		if ( ! is_scalar( $type ) ) {
			if ( $type instanceof WP_Post ) {
				$obj_type = 'post_type';
				$type     = $type->post_type;
			} elseif ( $type instanceof WP_Post_Type ) {
				$obj_type = 'post_type';
				$type     = $type->name;
			} elseif ( $type instanceof WP_Term ) {
				$obj_type = 'taxonomy';
				$type     = $type->taxonomy;
			} elseif ( $type instanceof WP_Taxonomy ) {
				$obj_type = 'taxonomy';
				$type     = $type->name;
			} else {
				// Pods.
				$obj_type = $this->get_pod_type( $type );
				$type     = pods_v( 'name', $type, '' );
			}
		}

		if ( 'attachment' === $type ) {
			$obj_type = $type;
		}

		switch( $obj_type ) {
			case 'post':
			case 'post_type':
				if ( is_numeric( $type ) ) {
					$post = get_post( $type );
					if ( ! $post ) {
						return false;
					}
					$type = $post->post_type;
				}
				if ( pll_is_translated_post_type( $type ) ) {
					return true;
				}
				break;
			case 'term':
			case 'taxonomy':
				if ( is_numeric( $type ) ) {
					$term = get_term( $type );
					if ( ! $term ) {
						return false;
					}
					$type = $term->taxonomy;
				}
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

	/**
	 * @param array $field_data
	 * @return bool
	 */
	public function is_field_sync_enabled( $field_data ) {
		if ( pods_v( $this->pod_field_sync_option, $field_data, false ) ) {
			return true;
		}
		$options = pods_v( 'options', $field_data, false );
		if ( pods_v( $this->pod_field_sync_option, $options, false ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array  $keys
	 * @param bool   $sync
	 * @param int    $from
	 * @param int    $to
	 * @param string $lang
	 * @return mixed
	 */
	public function maybe_sync_post_meta( $keys, $sync, $from, $to, $lang ) {
		remove_filter( 'pll_copy_post_metas', array( $this, 'maybe_sync_post_meta' ), 99999 );
		$return = $this->maybe_sync_meta( 'post', $keys, $sync, $from, $to, $lang );
		add_filter( 'pll_copy_post_metas', array( $this, 'maybe_sync_post_meta' ), 99999, 5 );
		return $return;
	}

	/**
	 * @param array  $keys
	 * @param bool   $sync
	 * @param int    $from
	 * @param int    $to
	 * @param string $lang
	 * @return mixed
	 */
	public function maybe_sync_term_meta( $keys, $sync, $from, $to, $lang ) {
		remove_filter( 'pll_copy_term_metas', array( $this, 'maybe_sync_term_meta' ), 99999 );
		$return = $this->maybe_sync_meta( 'term', $keys, $sync, $from, $to, $lang );
		add_filter( 'pll_copy_term_metas', array( $this, 'maybe_sync_term_meta' ), 99999, 5 );
		return $return;
	}

	/**
	 * @param array  $keys
	 * @param bool   $sync
	 * @param int    $from
	 * @param int    $to
	 * @param string $lang
	 * @return mixed
	 */
	protected function maybe_sync_meta( $type, $keys, $sync, $from, $to, $lang ) {

		if ( 'post' === $type && get_post( $from ) ) {
			$this->cur_pod = pods( get_post_type( $from ), $from );
		}
		elseif ( 'term' === $type && $obj = get_term( $from ) ) {
			$this->cur_pod = pods( $obj->taxonomy, $from );
		}
		/*elseif ( get_user_by( 'ID', $from ) ) {
			// You can't translate users with Polylang.
			return $keys;
		}
		elseif ( get_comment( $from ) ) {
			// You can't translate comments with Polylang.
			return $keys;
		}*/

		// Not a Pods object
		if ( ! $this->cur_pod ) {
			return $keys;
		}

		$fields = $this->cur_pod->fields();
		$file_meta = array();
		$pick_meta = array();
		foreach ( $fields as $key => $data ) {
			if ( ! $this->is_field_sync_enabled( $data ) ) {
				// No options available or sync is turned off for this field.
				unset( $keys[ $key ] );
				continue;
			}
			// Only non-relationship keys are added to the "regular" keys, relationship types (file/pick) are removed.
			switch( pods_v( 'type', $data, '' ) ) {
				case 'file':
					$file_meta[] = $key;
					unset( $keys[ $key ] );
					break;
				case 'pick':
					$pick_meta[] = $key;
					unset( $keys[ $key ] );
					break;
				default:
					$keys[] = $key;
					break;
			}
		}
		// @todo Merge them for now, maybe add separate handling in future.
		$rel_keys = array_merge( $file_meta, $pick_meta );

		if ( $rel_keys ) {
			//$this->sync_meta_translations( $post_id, $post, $update, $to_lang = false, $from_post_id = false );
			$this->sync_meta_translations( $rel_keys, $sync, $from, $to, $lang );
		}

		return $keys;
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

		if ( isset( PLL()->advanced_media ) ) {
			// Fix for Polylang Pro -> polylang/modules/media/admin-advanced-media.php // classname: PLL_Admin_Advanced_Media
			remove_action( 'add_attachment', array( PLL()->advanced_media, 'duplicate_media' ), 20 ); // After default add (20)
		}

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

		if ( isset( PLL()->advanced_media ) ) {
			// Fix for Polylang Pro -> polylang/modules/media/admin-advanced-media.php // classname: PLL_Admin_Advanced_Media
			add_action( 'add_attachment', array( PLL()->advanced_media, 'duplicate_media' ), 20 ); // After default add (20)
		}

	}

	/**
	 * @param  int|int[]  $meta_val
	 * @param  string     $lang
	 * @param  array      $pod_field
	 * @return array|mixed|null
	 */
	public function get_meta_translations( $meta_val, $lang, $pod_field ) {
		if ( ! $this->is_field_sync_enabled( $pod_field ) ) {
			return null;
		}
		// Is it a single field or an array?
		$single = false;
		if ( ! is_array( $meta_val ) ) {
			$single = true;
			// We need an array for the foreach loop.
			$meta_val = array( $meta_val );
		}

		$type = '';
		if ( 'file' === pods_v( 'type', $pod_field, '' ) ) {
			$type = 'post_type';
		} elseif ( 'pick' === pods_v( 'type', $pod_field, '' ) ) {
			$type = pods_v( 'pick_object', $pod_field, '' );
		}

		// Loop through all meta values.
		$new_meta_val = array();
		foreach( $meta_val as $rel_id ) {

			// Get the translations.
			$translations = $this->get_obj_translations( $rel_id, $type );
			if ( ! empty( $translations[ $lang ] ) ) {
				// This translation exists, add it to the new array.
				$new_meta_val[] = $translations[ $lang ];

			} else {

				// This translation does not exists.
				if ( 'post_type' === $type ) {

					if ( 'attachment' === get_post_type( $rel_id ) ) {
						// create attachment translation.
						//$attachment = get_post( $rel_id );
						//$new_meta_val[] = $this->translate_attachment( $rel_id, $lang, $attachment->post_parent );
						$new_meta_val[] = $this->maybe_translate_media( $rel_id, $lang );
					} else {
						// @todo Create new post??
						$new_meta_val[] = $this->maybe_translate_post( $rel_id, $lang, $translations );
					}

				} elseif ( 'taxonomy' === $type ) {
					// @todo Create new term??
					$new_meta_val[] = $this->maybe_translate_term( $rel_id, $lang, $translations );
				} else {
					// Just use regular one
					$new_meta_val[] = $rel_id;
				}
			}
		}

		// This meta field was a single field, return only the first result.
		if ( $single ) {
			return reset( $new_meta_val );
		}
		// No new data found, just return originals (non translated).
		if ( empty( $new_meta_val ) ) {
			$new_meta_val = $meta_val;
		}
		return $new_meta_val;
	}

	/*function add_meta_translation( $post_id, $lang ) {
		$cur_post = (array) get_post( $post_id );
		$cur_post_meta = get_post_custom( $post_id );

		$post = $cur_post;
		unset($post['ID']);
		unset($post['post_parent']);

		foreach ( $cur_post_meta as $meta_key => $meta_value ) {
			update_post_meta( $new_post_id, $meta_key, $meta_value );
		}
	}*/

	/**
	 * Get the translated post ID, will auto-create a translation if needed.
	 * @param int    $from_id
	 * @param string $lang
	 * @param array  $translations
	 * @return int|null
	 */
	public function maybe_translate_post( $from_id, $lang, $translations = array() ) {

		if ( empty( $translations ) ) {
			$translations = $this->get_obj_translations( $from_id, 'post_type' );
		}
		if ( ! empty( $translations[ $lang ] ) ) {
			// Already translated.
			return $translations[ $lang ];
		}

		$new_id = $from_id;
		$from = get_post( $from_id );

		if ( $from instanceof WP_Post ) {

			if ( ! $this->is_translation_enabled( $from ) ) {
				return $from_id;
			}

			$data = get_object_vars( $from );

			unset( $data['ID'] );
			unset( $data['id'] );

			// Get parent translation.
			if ( $data['parent'] ) {
				$data['parent'] = $this->maybe_translate_post( $data['parent'], $lang );
			}

			$new_id = wp_insert_post( $data );

			// Save the translations.
			pll_set_post_language( $new_id, $lang );
			$translations[ $lang ] = $new_id;
			pll_save_post_translations( $translations );
		}
		return $new_id;
	}

	/**
	 * Get the translated term ID, will auto-create a translation if needed.
	 * @param  int     $from_id
	 * @param  string  $lang
	 * @param  array   $translations
	 * @return int|null
	 */
	public function maybe_translate_term( $from_id, $lang, $translations = array() ) {

		if ( empty( $translations ) ) {
			$translations = $this->get_obj_translations( $from_id, 'taxonomy' );
		}
		if ( ! empty( $translations[ $lang ] ) ) {
			// Already translated.
			return $translations[ $lang ];
		}

		$new_id = $from_id;
		$from = get_term( $from_id );

		if ( $from instanceof WP_Term ) {

			if ( ! $this->is_translation_enabled( $from ) ) {
				return $from_id;
			}

			$data = get_object_vars( $from );
			unset( $data['term_id'] );

			if ( $data['parent'] ) {
				$data['parent'] = $this->maybe_translate_term( $data['parent'], $lang );
			}
			if ( $data['slug'] ) {
				$data['slug'] .= '-' . $lang;
			}

			// Remove unnecessary data.
			$data = array_intersect_key( $data, array(
				'alias_of'    => 1,
				'description' => 1,
				'parent'      => 1,
				'slug'        => 1,
			) );

			$new = wp_insert_term( $from->name . ' ' . $lang, $from->taxonomy, $data );

			if ( ! empty( $new['term_id'] ) ) {
				$new_id = $new['term_id'];
				// Save the translations.
				pll_set_term_language( $new_id, $lang );
				$translations[ $lang ] = $new_id;
				pll_save_post_translations( $translations );
			}
		}
		return $new_id;
	}

	/**
	 * Get the translated media ID, will auto-create a translation if needed.
	 * @param  int     $from_id
	 * @param  string  $lang
	 * @param  array   $translations
	 * @return int
	 */
	public function maybe_translate_media( $from_id, $lang, $translations = array() ) {
		$type = 'attachment';

		if ( $type !== get_post_type( $from_id ) ) {
			return $this->maybe_translate_post( $from_id, $lang, $translations );
		}

		if ( ! $this->is_translation_enabled( $from_id, $type ) ) {
			return $from_id;
		}

		if ( empty( $translations ) ) {
			$translations = $this->get_obj_translations( $from_id, 'post_type' );
		}
		if ( ! empty( $translations[ $lang ] ) ) {
			// Already translated.
			return $translations[ $lang ];
		}

		// source -> polylang/modules/media/admin-advanced-media.php
		$src_language = PLL()->model->post->get_language( $from_id );

		$new_id = $from_id;

		if ( ! empty( $src_language ) && $lang !== $src_language->slug ) {
			$tr_id  = PLL()->filters_media->create_media_translation( $new_id, $lang );
			$post   = get_post( $tr_id );
			$new_id = $post->ID;
			wp_maybe_generate_attachment_metadata( $post );
		}
		return $new_id;
	}

}
