<?php

namespace Ainsys\Connector\ACF;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Plugin_Common;

class Plugin implements Hooked {

	use Plugin_Common;


	public function __construct() {
		$this->init_plugin_metadata();

	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'ainsys_status_list', array( $this, 'add_status_of_component' ), 10, 1 );
		if ( $this->is_acf_active() ) {
			// add hooks.
			add_filter( 'ainsys_prepare_extra_comment_data', array( $this, 'prepare_comment_data' ), 10, 2 );
			add_filter( 'ainsys_prepare_extra_user_data', array( $this, 'prepare_user_data' ), 10, 2 );

			add_filter( 'ainsys_prepare_extra_comment_fields', array( $this, 'prepare_comment_fields' ), 10, 1 );
			add_filter( 'ainsys_prepare_extra_user_fields', array( $this, 'prepare_user_fields' ), 10, 1 );

			add_filter( 'ainsys_get_entities_list', array( $this, 'add_entity_to_list' ), 10, 1 );
			add_filter( 'ainsys_get_entity_fields_handlers', array( $this, 'add_fields_getters_for_entities' ), 10, 1 );
			add_filter(
				'ainsys_woocommerce_extra_fields_for_order',
				array( $this, 'generate_extra_fields_for_entity' ),
				10, 1
			);
			add_filter( 'ainsys_default_apis_for_entities',
				array( $this, 'add_default_api_for_entities_option' ),
				10, 1
			);
		}
	}

	public function add_status_of_component( $status_items = array() ) {

		$status_items['acf'] = array(
			'title'  => __( 'ACF', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'active' => $this->is_acf_active()
		);

		return $status_items;
	}

	public function is_acf_active() {
		return $this->is_plugin_active( 'advanced-custom-fields/acf.php' ) || $this->is_plugin_active( 'advanced-custom-fields-pro/acf.php' );
	}


	public function prepare_comment_data( $initial, $comment_id ) {
		$acf_fields = $initial;
		$acf_tmp    = get_field_objects( 'comment_' . $comment_id );
		if ( is_array( $acf_tmp ) ) {
			foreach ( $acf_tmp as $label => $val ) {
				$acf_fields[ $val["key"] ] = $val["value"];
			}
		}


		return $acf_fields;
	}

	public function prepare_user_data( $initial, $user_id ) {
		$acf_fields = $initial;
		$acf_tmp    = get_field_objects( 'user_' . $user_id );
		if ( is_array( $acf_tmp ) ) {
			foreach ( $acf_tmp as $label => $val ) {
				$acf_fields[ $val["key"] ] = $val["value"];
			}

		}

		return $acf_fields;
	}


	public function prepare_comment_fields( $initial = array() ) {

		$get_one_post = get_comments( array(
			'number' => 1,
		) );
		$comment_id   = isset( $get_one_post[0]->comment_ID ) ? $get_one_post[0]->comment_ID : 0;
		$acf_group    = get_field_objects( 'comment_' . $comment_id );

		return $this->generate_extra_fields_for_entity( $acf_group );
	}

	public function prepare_user_fields( $initial = array() ) {
		$acf_group = get_field_objects( 'user_' . get_current_user_id() );

		return $this->generate_extra_fields_for_entity( $acf_group );
	}

	/**
	 * Generate custom ACF fields for entity.
	 *
	 * @param array $acf_fields
	 *
	 * @return array
	 */
	public function generate_extra_fields_for_entity( $acf_fields ) {
		if ( empty( $acf_fields ) ) {
			return array();
		}

		$prepered_fields = [];

		if ( is_array( $acf_fields ) ) {
			foreach ( $acf_fields as $selector => $settings ) {
				$prepered_fields[ $settings["key"] ] = [
					"nice_name"   => $settings["label"] ?? '',
					"description" => $settings["instructions"] ?? '',
					"api"         => $settings["api"] ?? "ACF",
					"read"        => 0,
					"write"       => 0,
					"required"    => $settings["required"] ?? '',
					"sample"      => $settings["placeholder"] ?? '',
					"data_type"   => $settings["type"] ?? ''
				];
			}

		}

		return $prepered_fields;
	}

	public function add_entity_to_list( $entities_list = array() ) {
		/// Get ACF entities;
		$entities_list['acf'] = __( 'ACF  / fields', AINSYS_CONNECTOR_TEXTDOMAIN );

		return $entities_list;
	}

	public function add_fields_getters_for_entities( $getters = array() ) {
		$getters['acf'] = array( $this, 'get_acf_fields' );

		return $getters;
	}

	public function add_default_api_for_entities_option( $default_apis ) {
		$default_apis['ACF'] = '';

		return $default_apis;
	}


	/**
	 * Generate fields for ACF entity
	 *
	 * @return array
	 */
	public function get_acf_fields() {
		$get_one_post = get_posts( array(
			'numberposts' => 1,
			'post_type'   => 'post'
		) );
		$post_id      = isset( $get_one_post[0]->ID ) ? $get_one_post[0]->ID : 0;
		$acf_group    = $acf_group = get_field_objects( $post_id );

		return $this->generate_extra_fields_for_entity( $acf_group );
	}
}