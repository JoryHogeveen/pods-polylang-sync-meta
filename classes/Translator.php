<?php

namespace Pods_Polylang_Sync_Meta;

class Translator extends Data
{
	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {}

	/**
	 * @param mixed $meta_val
	 * @param \Pods $pod
	 * @param \Pods\Whatsit\Field|array|string $field
	 * @param bool $include_current
	 *
	 * @return array
	 */
	public function get_meta_translations( $meta_val, $pod, $field, $include_current = true ) {
		$current_id = $pod->id();

		if ( is_string( $field ) ) {
			$field = $pod->fields( $field );
		}

		$translations = $this->get_obj_translations( $current_id, $this->get_pod_type( $pod ) );
		$translated   = array();
		foreach ( $translations as $lang => $id ) {
			if ( (int) $id === (int) $current_id ) {
				if ( $include_current ) {
					$translated[ $id ] = $meta_val;
				}
				continue;
			}
			$translated[ $id ] = $this->get_meta_translation( $meta_val, $lang, $field );
		}

		return $translated;
	}

	/**
	 * @param  int|int[]                 $meta_val
	 * @param  string                    $lang
	 * @param  \Pods\Whatsit\Field|array $pod_field
	 * @return array|mixed|null
	 */
	public function get_meta_translation( $meta_val, $lang, $pod_field ) {
		if ( ! $this->is_field_translatable( $pod_field ) ) {
			return $meta_val;
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
			if ( 'media' === $type || 'attachment' === $type ) {
				$type = 'post_type';
			}
		}

		// Loop through all meta values.
		$new_meta_val = array();
		foreach ( $meta_val as $rel_id ) {

			// Get the translations.
			$translations = $this->get_obj_translations( $rel_id, $type );
			if ( ! empty( $translations[ $lang ] ) ) {
				// This translation exists, add it to the new array.
				$new_meta_val[] = $translations[ $lang ];

			} else {
				// This translation does not exists.
				switch ( $type ) {
					case 'post':
					case 'post_type':
						if ( 'attachment' === get_post_type( $rel_id ) ) {
							// @todo Always create new attachment??
							$new_meta_val[] = $this->get_media_translation( $rel_id, $lang );
						} else {
							// @todo Always create new post??
							$new_meta_val[] = $this->get_post_translation( $rel_id, $lang, $translations );
						}
						break;

					case 'taxonomy':
					case 'term':
						// @todo Always create new term??
						$new_meta_val[] = $this->get_term_translation( $rel_id, $lang, $translations );
						break;

					default:
						// Just use regular one
						$new_meta_val[] = $rel_id;
						break;
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

	/**
	 * Get the translated post ID, will auto-create a translation if needed.
	 * @param int    $from_id
	 * @param string $lang
	 * @param array  $translations
	 * @return int|null
	 */
	public function get_post_translation( $from_id, $lang, $translations = array() ) {

		$new_id = $from_id;
		$from   = get_post( $from_id );

		if ( $from instanceof \WP_Post ) {

			if ( ! $this->is_translation_enabled( $from ) ) {
				return $from_id;
			}

			if ( empty( $translations ) ) {
				$translations = $this->get_obj_translations( $from_id, 'post_type' );
			}
			if ( ! empty( $translations[ $lang ] ) ) {
				// Already translated.
				return $translations[ $lang ];
			}

			$new_id = $this->create_post_translation( $from, $lang );
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
	public function get_term_translation( $from_id, $lang, $translations = array() ) {

		$new_id = $from_id;
		$from   = get_term( $from_id );

		if ( $from instanceof \WP_Term ) {

			if ( ! $this->is_translation_enabled( $from ) ) {
				return $from_id;
			}

			if ( empty( $translations ) ) {
				$translations = $this->get_obj_translations( $from_id, 'taxonomy' );
			}
			if ( ! empty( $translations[ $lang ] ) ) {
				// Already translated.
				return $translations[ $lang ];
			}

			$new_id = $this->create_term_translation( $from, $lang );
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
	public function get_media_translation( $from_id, $lang, $translations = array() ) {
		$type = 'attachment';
		$attachment = get_post( $from_id );

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

		return $this->create_media_translation( $attachment, $lang );
	}
}
