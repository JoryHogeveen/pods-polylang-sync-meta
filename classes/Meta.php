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

		add_filter( 'add_post_metadata', array( $this, 'filter_add_post_metadata' ), 99999, 5 );
		add_filter( 'update_post_metadata', array( $this, 'filter_update_post_metadata' ), 99999, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'filter_delete_post_metadata' ), 99999, 5 );

		add_filter( 'add_term_metadata', array( $this, 'filter_add_term_metadata' ), 99999, 5 );
		add_filter( 'update_term_metadata', array( $this, 'filter_update_term_metadata' ), 99999, 5 );
		add_filter( 'delete_term_metadata', array( $this, 'filter_delete_term_metadata' ), 99999, 5 );

		// The above filters only handle prefixed values (_pods_). This filter handles the actual values.
		add_action( 'pods_api_save_relationships', array( $this, 'action_pods_api_save_relationships' ), 10, 4 );
	}

	/**
	 * Gets the translator class.
	 * @return \Pods_Polylang_Sync_Meta\Translator|null
	 */
	public function translator() {
		if ( ! $this->translator ) {
			$this->translator = pods_polylang_sync_meta()->translator();
		}
		return $this->translator;
	}

	/**
	 * @param int                       $id          ID of item.
	 * @param array                     $related_ids ID(s) for items to save.
	 * @param array|\Pods\Whatsit\Pod   $pod         The Pod object.
	 * @param array|\Pods\Whatsit\Field $field       The Field object.
	 * @return void
	 */
	public function action_pods_api_save_relationships( $id, $related_ids, $field, $pod ) {
		if ( self::$avoid_recursion || ! $this->check_meta( $pod, $field ) ) {
			return;
		}
		self::$avoid_recursion = true;

		$pod_obj = pods( $pod['name'], $id );
		if ( ! $pod_obj ) {
			return;
		}

		$translations = $this->translator()->get_meta_translations( $related_ids, $pod_obj, $field, false );

		foreach ( $translations as $id => $value ) {
			pods_api()->save_relationships( $id, $value, $pod, $field );
		}

		self::$avoid_recursion = false;
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

	/**
	 * @param \Pods|\Pods\Whatsit\Pod|array $pod
	 * @param \Pods\Whatsit\Field|string|array $field
	 *
	 * @return bool
	 */
	public function check_meta( $pod, $field ) {
		if ( ! $this->is_pod_translatable( $pod ) ) {
			return false;
		}

		if ( is_string( $field ) ) {
			$field = $pod->fields( $field );
		}

		if ( ! $field || ! $this->is_field_sync_enabled( $field ) ) {
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
		self::$avoid_recursion = true;

		$type         = $this->get_pod_type( $pod );
		$translations = $this->translator()->get_meta_translations( $meta_value, $pod, $meta_key, false );

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
		self::$avoid_recursion = true;

		$type         = $this->get_pod_type( $pod );
		$translations = $this->translator()->get_meta_translations( $meta_value, $pod, $meta_key, false );

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
		self::$avoid_recursion = true;

		$type = $this->get_pod_type( $pod );
		if ( $meta_value ) {
			$translations = $this->translator()->get_meta_translations( $meta_value, $pod, $meta_key, false );
		} else {
			$translations = $this->get_obj_translations( $pod->id(), $type );
			$translations = array_fill_keys( $translations, '' );
		}

		foreach ( $translations as $id => $value ) {
			delete_metadata( $type, $id, $meta_key, $value, $delete_all );
		}
		self::$avoid_recursion = false;
	}
}
