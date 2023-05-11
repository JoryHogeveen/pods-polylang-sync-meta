<?php

namespace Pods_Polylang_Sync_Meta;

class Meta extends Data
{
	private static $_instance = null;

	private static $avoid_recursion = false;

	private $translator = null;

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

	/**
	 * Gets the translator class.
	 * @return \Pods_Polylang_Sync_Meta\Translator|null
	 */
	public function translator() {
		if ( ! $this->translator ) {
			include 'classes/Translator.php';
			$this->translator = \Pods_Polylang_Sync_Meta\Translator::get_instance();
		}
		return $this->translator;
	}

	public function filter_add_post_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
		if ( ! self::$avoid_recursion ) {
			$pod = $this->get_pod( $object_id, 'post' );
			$this->maybe_add_pod_metadata( $pod, $meta_key, $meta_value, $unique );
		}
		return $check;

	}

	public function filter_update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( ! self::$avoid_recursion ) {
			$pod = $this->get_pod( $object_id, 'post' );
			$this->maybe_update_pod_metadata( $pod, $meta_key, $meta_value, $prev_value );
		}
		return $check;

	}

	public function filter_delete_post_metadata( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
		if ( ! self::$avoid_recursion ) {
			$pod = $this->get_pod( $object_id, 'post' );
			$this->maybe_delete_pod_metadata( $pod, $meta_key, $meta_value, $delete_all );
		}
		return $check;
	}

	public function filter_add_term_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
		if ( ! self::$avoid_recursion ) {
			$pod = $this->get_pod( $object_id, 'term' );
			$this->maybe_add_pod_metadata( $pod, $meta_key, $meta_value, $unique );
		}
		return $check;

	}

	public function filter_update_term_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( ! self::$avoid_recursion ) {
			$pod = $this->get_pod( $object_id, 'term' );
			$this->maybe_update_pod_metadata( $pod, $meta_key, $meta_value, $prev_value );
		}
		return $check;

	}

	public function filter_delete_term_metadata( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
		if ( ! self::$avoid_recursion ) {
			$pod = $this->get_pod( $object_id, 'term' );
			$this->maybe_delete_pod_metadata( $pod, $meta_key, $meta_value, $delete_all );
		}
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

	/**
	 * @param \Pods  $pod
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param bool   $unique
	 *
	 * @return void
	 */
	private function maybe_add_pod_metadata( $pod, $meta_key, $meta_value, $unique ) {
		if ( ! $this->check_meta( $pod, $meta_key ) ) {
			return;
		}

		$type         = $this->get_pod_type( $pod );
		$translations = $this->translator()->get_meta_translations( $meta_value, $pod, $meta_key, false );

		self::$avoid_recursion = true;
		foreach ( $translations as $id => $value ) {
			add_metadata( $type, $id, $meta_key, $value, $unique );
		}
		self::$avoid_recursion = false;
	}

	/**
	 * @param \Pods  $pod
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param mixed  $prev_value
	 *
	 * @return void
	 */
	private function maybe_update_pod_metadata( $pod, $meta_key, $meta_value, $do_prev_value = null ) {
		if ( ! $this->check_meta( $pod, $meta_key ) ) {
			return;
		}

		$type         = $this->get_pod_type( $pod );
		$translations = $this->translator()->get_meta_translations( $meta_value, $pod, $meta_key, false );

		self::$avoid_recursion = true;
		foreach ( $translations as $id => $value ) {
			$prev_value = '';
			if ( $do_prev_value ) {
				$prev_value = get_metadata_raw( $type, $id, $meta_key );
			}
			update_metadata( $type, $id, $meta_key, $value, $prev_value );
		}
		self::$avoid_recursion = false;
	}

	/**
	 * @param \Pods  $pod
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param bool   $delete_all
	 *
	 * @return void
	 */
	private function maybe_delete_pod_metadata( $pod, $meta_key, $meta_value, $delete_all ) {
		if ( ! $this->check_meta( $pod, $meta_key ) ) {
			return;
		}

		$type = $this->get_pod_type( $pod );
		if ( $meta_value ) {
			$translations = $this->translator()->get_meta_translations( $meta_value, $pod, $meta_key, false );
		} else {
			$translations = $this->get_obj_translations( $pod->id(), $type );
			$translations = array_fill_keys( $translations, '' );
		}

		self::$avoid_recursion = true;
		foreach ( $translations as $id => $value ) {
			delete_metadata( $type, $id, $meta_key, $value, $delete_all );
		}
		self::$avoid_recursion = false;
	}
}
