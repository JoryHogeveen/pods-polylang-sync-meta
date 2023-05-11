<?php

namespace Pods_Polylang_Sync_Meta;

class Meta extends Data
{
	private static $_instance = null;

	private $sync = array();

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		foreach ( array( 'post', 'term' ) as $type ) {
			add_filter( "add_{$type}_metadata", array( $this, "filter_add_{$type}_metadata" ), 1, 3 );
			add_filter( "update_{$type}_metadata", array( $this, "filter_update_{$type}_metadata" ), 1, 3 );
			add_filter( "delete_{$type}_metadata", array( $this, "filter_delete_{$type}_metadata" ), 1, 3 );
		}
	}

	public function filter_add_post_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
		$pod = $this->get_pod( $object_id, 'post' );
		$this->maybe_add_pod_metadata( $pod, $meta_key, $meta_value, $unique );
		return $check;

	}

	public function filter_update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		$pod = $this->get_pod( $object_id, 'post' );
		$this->maybe_update_pod_metadata( $pod, $meta_key, $meta_value, $prev_value );
		return $check;

	}

	public function filter_delete_post_metadata( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
		$pod = $this->get_pod( $object_id, 'post' );
		$this->maybe_delete_pod_metadata( $pod, $meta_key, $meta_value, $delete_all );
		return $check;
	}

	public function filter_add_term_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
		$pod = $this->get_pod( $object_id, 'term' );
		$this->maybe_add_pod_metadata( $pod, $meta_key, $meta_value, $unique );
		return $check;

	}

	public function filter_update_term_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		$pod = $this->get_pod( $object_id, 'term' );
		$this->maybe_update_pod_metadata( $pod, $meta_key, $meta_value, $prev_value );
		return $check;

	}

	public function filter_delete_term_metadata( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
		$pod = $this->get_pod( $object_id, 'term' );
		$this->maybe_delete_pod_metadata( $pod, $meta_key, $meta_value, $delete_all );
		return $check;
	}

	public function check_meta( $pod, $meta_key ) {
		if ( ! $this->is_pod_translatable( $pod ) ) {
			return false;
		}

		$field = $pod->fields( $meta_key );
		if ( ! $field || ! $this->is_field_translatable( $field ) ) {
			return false;
		}

		return true;
	}

	private function maybe_add_pod_metadata( $pod, $meta_key, $meta_value, $unique ) {
		if ( ! $this->check_meta( $pod, $meta_key ) ) {
			return;
		}

	}

	private function maybe_update_pod_metadata( $pod, $meta_key, $meta_value, $prev_value ) {
		if ( ! $this->check_meta( $pod, $meta_key ) ) {
			return;
		}

	}

	private function maybe_delete_pod_metadata( $pod, $meta_key, $meta_value, $delete_all ) {
		if ( ! $this->check_meta( $pod, $meta_key ) ) {
			return;
		}

	}
}
