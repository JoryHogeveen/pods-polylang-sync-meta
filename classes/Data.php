<?php

namespace Pods_Polylang_Sync_Meta;

abstract class Data extends Plugin
{
	public $pod_field_sync_option = 'pods_polylang_sync_meta';

	public $translatable_field_types = array(
		'pick',
		'file',
	);

	public $translatable_pod_types = array(
		'post_type',
		'taxonomy',
		'media',
	);

	/**
	 * @param int    $id
	 * @param string $type
	 * @return bool|\Pods
	 */
	public function get_pod( $id, $type ) {
		switch ( $type ) {
			case 'post':
				$type = get_post_type( $id );
			break;
			case 'term':
				$obj  = get_term( $id );
				$type = $obj->taxonomy;
			break;
		}

		return pods( $type, $id );
	}

	/**
	 * Get Pod object type.
	 * @param  \Pods  $pod
	 * @return string
	 */
	public function get_pod_type( $pod ) {
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
	 * Get Pod fields.
	 * @param  \Pods  $pod
	 * @return \Pods\Whatsit\Field|array
	 */
	public function get_pod_fields( $pod ) {
		if ( is_callable( array( $pod->pod_data, 'get_fields' ) ) ) {
			return $pod->pod_data->get_fields();
		} else {
			// Fallback. @todo Remove object fields.
			return $pod->fields();
		}
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return array
	 */
	public function get_obj_metadata( $id, $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type( $type );

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
	 * @param string $field
	 * @return string
	 */
	public function get_obj_language( $id, $type = '', $field = 'slug' ) {
		$type = ( $type ) ? $type : $this->get_pod_type( $type );
		return parent::get_obj_language( $id, $type, $field );
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return array
	 */
	public function get_obj_translations( $id, $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type( $type );
		return parent::get_obj_translations( $id, $type );
	}

	/**
	 * @param int    $id
	 * @param string $type
	 * @return mixed
	 */
	public function update_obj_meta( $id, $key, $value, $prev = '', $type = '' ) {
		$type = ( $type ) ? $type : $this->get_pod_type( $type );

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
			if ( $type instanceof \WP_Post ) {
				$obj_type = 'post_type';
				$type     = $type->post_type;
			} elseif ( $type instanceof \WP_Post_Type ) {
				$obj_type = 'post_type';
				$type     = $type->name;
			} elseif ( $type instanceof \WP_Term ) {
				$obj_type = 'taxonomy';
				$type     = $type->taxonomy;
			} elseif ( $type instanceof \WP_Taxonomy ) {
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
			break;
		}

		return parent::is_translation_enabled( $type, $obj_type );
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
	 * @param string|array|\Pods $pod
	 * @return bool
	 */
	public function is_pod_translatable( $pod ) {
		$type = ( is_string( $pod ) ) ? $pod : $this->get_pod_type( $pod );

		return in_array( $type, $this->translatable_pod_types, true );
	}

	/**
	 * @param string|array|\Pods\Whatsit\Field $field
	 * @return bool
	 */
	public function is_field_translatable( $field ) {
		$type = ( is_string( $field ) ) ? $field : pods_v( 'type', $field, '' );

		return in_array( $type, $this->translatable_field_types, true );
	}
}
