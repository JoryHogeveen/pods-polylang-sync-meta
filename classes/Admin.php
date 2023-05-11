<?php

namespace Pods_Polylang_Sync_Meta;

class Admin extends Data
{
	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		add_filter( 'pods_admin_setup_edit_field_options', array( $this, 'pods_edit_field_options' ), 12, 2 );
	}

	/**
	 * @param array $options
	 * @param array $pod
	 * @return array
	 */
	public function pods_edit_field_options( $options, $pod ) {

		if ( $this->is_pod_translatable( $pod ) ) {
			//$analysis_field_types = $this->get_sync_field_types();

			if ( ! $this->is_translation_enabled( $pod ) ) {
				return $options;
			}

			$pod_major_version = substr( PODS_VERSION, 0, 3 );

			if ( version_compare( $pod_major_version, '2.8', '>=' ) ) {

				$options['advanced']['polylang'] = array(
					'name' => 'polylang',
					'label' => __( 'Polylang', \Pods_Polylang_Sync_Meta::DOMAIN ),
					'type' => 'heading',
				);

				$options['advanced'][ $this->pod_field_sync_option ] = array(
					'label' => __( 'Enable meta field sync', \Pods_Polylang_Sync_Meta::DOMAIN ),
					'name'  => $this->pod_field_sync_option,
					'type'  => 'boolean',
					'help'  => '',
					/*'depends-on' => array(
						'type' => $analysis_field_types,
					),*/
				);

			} else {
				// Pre 2.8.
				$options['advanced'][ __( 'Polylang', \Pods_Polylang_Sync_Meta::DOMAIN ) ] = array(
					$this->pod_field_sync_option => array(
						'label' => __( 'Enable meta field sync', \Pods_Polylang_Sync_Meta::DOMAIN ),
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
}
