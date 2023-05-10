<?php

namespace Pods_Polylang_Sync_Meta;

abstract class Data
{
	public $pod_field_sync_option = 'pods_polylang_sync_meta';

	/**
	 * @param $key
	 * @return mixed
	 */
	public function get_pll_option( $key ) {
		$options = PLL()->options;
		return ( isset( $options[ $key ] ) ) ? $options[ $key ] : null;
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

		$meta = array();
		switch ( $type ) {
			case 'post':
			case 'post_type':
				$meta = get_post_meta( $id );
			break;
			case 'term':
			case 'taxonomy':
				$meta = get_term_meta( $id );
			break;
		}
		return $meta;
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return string
	 */
	public function get_obj_language( $id, $field = 'slug', $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type();

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
		$type = ( $type ) ? $type : $this->get_pod_type();

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

	/**
	 * @param int    $id
	 * @param string $type
	 * @return mixed
	 */
	public function update_obj_meta( $id, $key, $value, $prev = '', $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type();

		$success = null;
		switch ( $type ) {
			case 'post':
			case 'post_type':
				$success = update_post_meta( $id, $key, $value, $prev );
			break;
			case 'term':
			case 'taxonomy':
				$success = update_term_meta( $id, $key, $value, $prev );
			break;
		}
		return $success;
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

		switch ( $obj_type ) {
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
}