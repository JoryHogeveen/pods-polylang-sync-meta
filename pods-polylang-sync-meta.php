<?php 
/*
Requires: Pods and Polylang 1.8+

Plugin Name: Pods Polylang Sync Meta
Plugin URI: 
Version: 0.1
Author: Jory Hogeveen
Author uri: https://www.keraweb.nl
Description: Syncs related attachments fields. Also automatically created attachments if this is enabled
Text Domain: pods-polylang-sync-meta
Domain Path: /languages
*/

! defined( 'ABSPATH' ) and die();

class Pods_Polylang_Sync_Meta {

	public $file_meta_keys = array( ); //'_thumbnail_id'
	public $sync_post_types = array( 'product' ); //'post', 'page', 

	function __construct() {
		add_action('wp_loaded', array( $this, 'init' ) );
	}

	function init() {

		if ( is_admin() ) {

			if (   ! function_exists( 'PLL' ) 
				|| ! property_exists( PLL(), 'model' )
				|| ! property_exists( PLL(), 'filters_media' )
				|| ! function_exists( 'pll_get_post_translations' )
				|| ! function_exists( 'pll_is_translated_post_type' )
				|| ! function_exists( 'pods' ) ) {
				return;
			}

			$fields = get_posts( array(
				'posts_per_page' => -1,
				'post_type' => '_pods_field',
				'meta_query' => array(array(
					'key' => 'type',
					'value' => 'file',
				)),
				'suppress_filters' => true 
			) );

			foreach ($fields as $field) {
				$this->file_meta_keys[] = $field->post_name;
			}
			$this->file_meta_keys = apply_filters( 'pods_pll_copy_post_metas_file', $this->file_meta_keys );

			//print_r($this->file_meta_keys);

			//add_filter( 'pll_copy_post_metas', 	array( $this, 'pll_copy_post_metas' ), 		99, 2 );
			//add_action( 'add_meta_boxes', 		array( $this, 'add_meta_boxes' ), 			2, 2 );
			add_filter( 'save_post', 			array( $this, 'sync_meta_translations' ), 	9999, 3 );

		}

	}

	/*
	 * Metafields 
	 */

	function pll_copy_post_metas( $meta_keys, $sync ) {

		// Add related attachments meta when attachments are not translated
		if ( ! pll_is_translated_post_type('attachment') || $sync == false ) {
			$meta_keys = array_merge( $meta_keys, $this->file_meta_keys );
		}
		// Add related pages meta when pages are not translated
		/*if ( ! pll_is_translated_post_type('page') || $sync == false ) {
			$meta_keys = array_merge( $meta_keys, array(
		    	'rel_page',
		    ));
		}*/
	    return $meta_keys;
	}

	function add_meta_boxes( $post_type, $post ){
		// source: polylang/modules/admin-sync.php
		// Is it a new draft and is to be translated?
		if ( 'post-new.php' == $GLOBALS['pagenow'] && isset( $_GET['from_post'], $_GET['new_lang'] ) && pll_is_translated_post_type( $post->post_type ) ) {
			$from_post_id = (int) $_GET['from_post'];
			$lang = PLL()->model->get_language( $_GET['new_lang'] );
			$this->sync_meta_translations( $post->ID, $post, true, $lang->slug, $from_post_id );
		}
	}

	function sync_meta_translations( $post_id, $post, $update, $to_lang = false, $from_post_id = false ) {
		if ( $update !== true ) {
			return;
		}
		if ( ! in_array( get_post_type($post_id), $this->sync_post_types ) ) {
			return;
		}
		$cur_lang = PLL()->model->post->get_language( $post_id )->slug;
		// TEMP, only sync from default language!
		if ( pll_default_language() != $cur_lang ) {
			return;
		}

		if ( isset( PLL()->advanced_media ) ) {
			// Fix for Polylang Pro -> polylang/modules/media/admin-advanced-media.php // classname: PLL_Admin_Advanced_Media
			remove_action( 'add_attachment', array( PLL()->advanced_media, 'duplicate_media' ), 20 ); // After default add (20)
		}

		if ( $to_lang && $from_post_id ) {
			// This is a new draft (not saved yet)
			$post_meta = get_post_custom( $from_post_id ); // Get metadata from orignial post
			//$post_translations = pll_get_post_translations( $from_post_id ); // Get translations from orignial post
			//$post_translations[ $to_lang ] = $post_id; // Add new post to the translations

			$post_translations = array( $to_lang => $post_id ); // Add new post to the translations (only ons language is needed since its a new draft, we don't need to update the other languages (yet))
		} else {
			// This is a save_post action
			$post_meta = get_post_custom(); // Get metadata from post
			$post_translations = pll_get_post_translations( $post_id ); // Get translations from post
		}

		unset( $post_translations[ $cur_lang ] );

		$metas = array();
		if ( pll_is_translated_post_type('attachment') ) {
			// Attachment metafields (array of ID's)
			$metas = array_merge( $metas, $this->file_meta_keys );
		}
		/*if ( pll_is_translated_post_type('page') ) {
			// Related pages metafields
			$metas = array_merge( $metas, array(
		    	'rel_page',
		    ));
		}*/

		// Loop through translations
		foreach ( $post_translations as $lang => $translation_id ) {
			if ( $to_lang != false && $lang != $to_lang ) {
				continue;
			}
			//if ( $translation_id != $post_id ) {
				foreach ( $metas as $meta ) {
					// Loop through all meta fields
					if ( isset( $post_meta[ $meta ] ) ) {
						//echo $meta.'<br>';
						//print_r($post_meta[ $meta ]);

						// Get metafield translations
						$translation_meta = $this->get_meta_translations( $post_meta[ $meta ], $lang );

						//print_r($translation_meta);

						// Fix for thumbnail (get_posts_custom returns all serialized)
						if ( $meta == '_thumbnail_id' && isset( $translation_meta[0] ) ) {
							$translation_meta = $translation_meta[0];
						}

						// http://pods.io/docs/code/pods/save/
						//$pod = pods( get_post_type( $post_id ), $post_id );
						//$pod->save( $meta, $translation_meta );

						// Update translation with translated metadata
						update_post_meta( $translation_id, $meta, $translation_meta );
					}
				}
			//}
		}

		if ( isset( PLL()->advanced_media ) ) {
			// Fix for Polylang Pro -> polylang/modules/media/admin-advanced-media.php // classname: PLL_Admin_Advanced_Media
			add_action( 'add_attachment', array( PLL()->advanced_media, 'duplicate_media' ), 20 ); // After default add (20)
		}

	}

	function get_meta_translations( $meta, $lang ) {
		// Is it a single field or an array?
		$single = false;
		if ( ! is_array( $meta ) ) {
			$single = true;
			//We need an array for the foreach loop
			$meta = array( $meta );
		}

		// Loop through all meta values
		$new_meta = array();
		foreach( $meta as $meta_id ) {
			// Get the translations
			$translations = pll_get_post_translations( $meta_id );
			if ( isset( $translations[ $lang ] ) ) {
				// This translation exists, add it to the new array
				$new_meta[] = $translations[ $lang ];
			} else {
				// This translation does not exists
				if ( get_post_type( $meta_id ) == 'attachment') {
					// create attachment translation
					//$attachment = get_post( $meta_id );
					//$new_meta[] = $this->translate_attachment( $meta_id, $lang, $attachment->post_parent );
					$new_meta[] = $this->duplicate_media( $meta_id, $lang );
				} else {
					// Just use regular one
					$new_meta[] = $meta_id;
				}
			}
		}
		// It this metafield was a single field, returng only the first result
		if ( $single == true && isset( $new_meta[0] ) ) {
			return $new_meta[0];
		}
		// No new data found, just return originals (non translated)
		if ( empty( $new_meta ) ) {
			$new_meta = $meta;
		}
		return $new_meta;
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
	 * Creates media translation
	 *
	 * @param int $post_id
	 */
	public function duplicate_media( $post_id, $new_lang ) {

		// source -> polylang/modules/media/admin-advanced-media.php

		$src_language = PLL()->model->post->get_language( $post_id );

		if ( ! empty( $src_language ) ) {
			if ( $new_lang !== $src_language->slug ) {
				$tr_id = PLL()->filters_media->create_media_translation( $post_id, $new_lang );
				$post = get_post( $tr_id );
				$post_id = $post->ID;
				wp_maybe_generate_attachment_metadata( $post );
			}
		}
		return $post_id;
	}


	// Source: https://github.com/aucor/polylang-translate-existing-media
	/**
	 * Translate attachment
	 *
	 * @param int $attachment_id id of the attachment in original language
	 * @param string $new_lang new language slug
	 * @param int $parent_id id of the parent of the translated attachments (post ID)
	 *
	 * @return int translated id
	 */
	function translate_attachment($attachment_id, $new_lang, $parent_id) {
		$post = get_post($attachment_id);
		$post_id = $post->ID;
		// if there's existing translation, use it
		$existing_translation = pll_get_post($post_id, $new_lang);
		if(!empty($existing_translation)) {
			return $existing_translation; // existing translated attachment
		}
		$post->ID = null; // will force the creation
		$post->post_parent = $parent_id ? $parent_id : 0;
		$tr_id = wp_insert_attachment($post);
		add_post_meta($tr_id, '_wp_attachment_metadata', get_post_meta($post_id, '_wp_attachment_metadata', true));
		add_post_meta($tr_id, '_wp_attached_file', get_post_meta($post_id, '_wp_attached_file', true));
		// copy alternative text to be consistent with title, caption and description copied when cloning the post
		if ($meta = get_post_meta($post_id, '_wp_attachment_image_alt', true)) {
			add_post_meta($tr_id, '_wp_attachment_image_alt', $meta);
		}
		
		// set language of the attachment
		PLL()->model->post->set_language($tr_id, $new_lang);
		
		$translations = PLL()->model->post->get_translations($post_id);
		if (!$translations && $lang = PLL()->model->post->get_language($post_id)) {
			$translations[$lang->slug] = $post_id;
		}
		$translations[$new_lang] = $tr_id;
		PLL()->model->post->save_translations($tr_id, $translations);
		//$this->images_translated++;
		return $tr_id; // newly translated attachment
	}

}

$GLOBALS['pods_polylang_sync_meta'] = new Pods_Polylang_Sync_Meta();
