<?php

namespace Ainsys\Connector\Woocommerce;

use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\UTM_Handler;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Order;
use Ainsys\Connector\Woocommerce\Webhooks\Handle_Product;

class Plugin implements Hooked {

	use Plugin_Common;

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var UTM_Handler
	 */
	private $UTM_handler;

	/**
	 * @var Settings
	 */
	private $settings;


	public function __construct( Core $core, Logger $logger, UTM_Handler $UTM_handler, Settings $settings ) {
		$this->core        = $core;
		$this->logger      = $logger;
		$this->UTM_handler = $UTM_handler;
		$this->settings    = $settings;

		$this->init_plugin_metadata();

		$this->components['product_webhook'] = new Handle_Product();
		$this->components['order_webhook']   = new Handle_Order();
	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'ainsys_status_list', array( $this, 'add_status_of_component' ), 10, 1 );
		if ( $this->is_woocommerce_active() ) {
			// add hooks.
			add_filter( 'ainsys_get_entities_list', array( $this, 'add_entity_to_list' ), 10, 1 );
			add_filter( 'ainsys_get_entity_fields_handlers', array( $this, 'add_fields_getters_for_entities' ), 10, 1 );
			add_filter( 'ainsys_default_apis_for_entities',
				array( $this, 'add_default_api_for_entities_option' ),
				10, 1
			);

			add_action( 'woocommerce_checkout_order_processed', array( $this, 'new_order_processed' ) );
			add_action( 'woocommerce_order_status_changed', array( $this, 'send_order_status_update_to_ainsys' ) );
			add_action( 'woocommerce_update_product', array( $this, 'send_update_product_to_ainsys' ), 10, 2 );

			foreach ( $this->components as $component ) {
				if ( $component instanceof Hooked ) {
					$component->init_hooks();
				}
			}
		}
	}

	public function add_status_of_component( $status_items = array() ) {

		$status_items['woocommerce'] = array(
			'title'  => __( 'WooCommerce', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'active' => $this->is_woocommerce_active()
		);

		return $status_items;
	}

	/**
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return $this->is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	public function add_entity_to_list( $entities_list = array() ) {
		/// Get Woocommerce entities;
		$entities_list['order']   = __( 'Order / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
		$entities_list['product'] = __( 'Product / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
		if ( function_exists( 'wc_coupons_enabled' ) ) {
			if ( wc_coupons_enabled() ) {
				$entities_list['coupons'] = __( 'Coupons / fields', AINSYS_CONNECTOR_TEXTDOMAIN );
			}
		}


		return $entities_list;
	}

	public function add_fields_getters_for_entities( $getters = array() ) {
		$getters['product'] = array( $this, 'get_product_fields' );
		$getters['order']   = array( $this, 'get_order_fields' );
		$getters['coupons'] = array( $this, 'get_coupons_fields' );

		return $getters;
	}

	public function add_default_api_for_entities_option( $default_apis ) {
		$default_apis['woocommerce'] = '';

		return $default_apis;
	}


	/**
	 * We send a new order to AINSYS
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 */
	public function new_order_processed( $order_id = 0 ) {
		if ( ! $order_id ) {
			return false;
		}

		$request_action = 'CREATE';

		$order = new \WC_Order( $order_id );
		$data  = $order->get_data();
		if ( empty( $data ) ) {
			return false;
		}

		//self::save_log_information($order_id, 'settings dump', serialize($data), '', 0);

		// Prepare order data
		$fields   = $this->prepare_fields( $data );
		$utm_data = $this->get_utm_fields();

		//Prepare products
		if ( isset( $data['line_items'] ) && ! empty( $data['line_items'] ) ) {
			$products = $this->prepare_products( $data['line_items'] );
		} else {
			$products = [];
		}

		$fields_filtered = apply_filters( 'ainsys_new_order_fields', $fields, $order );

		$this->sanitize_aditional_order_fields( array_diff( $fields_filtered, $fields ), $data['id'] );

		$order_data = array(
			'entity'  => [
				'id'   => $order_id,
				'name' => 'order'
			],
			'action'  => $request_action,
			'payload' => array_merge( $fields_filtered, $utm_data, [ 'products' => $products ] )
		);

		try {
			$server_response = $this->core->curl_exec_func( $order_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}

		$this->logger->save_log_information( $order_id, $request_action, serialize( $order_data ), serialize( $server_response ), 0 );

		return true;
	}

	/**
	 * We send an updated order status to AINSYS
	 *
	 * @param int $order_id
	 *
	 * @return
	 */
	public function send_order_status_update_to_ainsys( int $order_id ) {
		$host = false;
		if ( ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$host = $_SERVER['SERVER_NAME'];
		}

		$request_action = 'update/order';

		$order  = new \WC_Order( $order_id );
		$status = $order->get_status() ?? false;

		$order_data = array(
			'object_id'      => $order_id,
			'request_action' => $request_action,
			'request_data'   => array(
				'status'   => strtoupper( trim( $status ) ),
				'hostname' => $host
			)
		);

		try {
			$server_response = $this->core->curl_exec_func( $order_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}

		$this->logger->save_log_information( $order_id, $request_action, serialize( $order_data ), serialize( $server_response ), 0 );

		return;
	}

	/**
	 * We send an updated WC product details to AINSYS
	 *
	 * @param int $product_id
	 * @param object $product
	 *
	 * @return
	 */
	public function send_update_product_to_ainsys( $product_id, $product ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_update_product_fields', $this->prepare_single_product( $product ), $product );

		$request_data = array(
			'entity'  => [
				'id'   => $product_id,
				'name' => 'product'
			],
			'action'  => $request_action,
			'payload' => $fields
		);
		$message='';
		foreach ($fields as $key => $value) {
			$message .= $key . '=>' . $value . ', ';
		}

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}

		$this->logger->save_log_information( $product_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return;
	}

	/**
	 * Preparing order fields
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	private function prepare_fields( $data = [] ) {
		$all_fields = WC()->checkout->get_checkout_fields();

		$prepare_data = [];
		if ( ! empty( $data['id'] ) ) {
			$prepare_data['id'] = $data['id'];
		}

		if ( ! empty( $data['currency'] ) ) {
			$prepare_data['currency'] = $data['currency'];
		}

		if ( ! empty( $data['customer_id'] ) ) {
			$prepare_data['customer_id'] = $data['customer_id'];
		}

		if ( ! empty( $data['shipping_total'] ) ) {
			$prepare_data['shipping_total'] = $data['shipping_total'];
		}

		if ( ! empty( $data['payment_method_title'] ) ) {
			$prepare_data['payment_method_title'] = $data['payment_method_title'];
		}

		if ( ! empty( $data['transaction_id'] ) ) {
			$prepare_data['transaction_id'] = $data['transaction_id'];
		}

		if ( ! empty( $data['customer_note'] ) ) {
			$prepare_data['customer_note'] = $data['customer_note'];
		}


		$prepare_data['date'] = date( "Y-m-d H:i:s" );

		// get applaed cupons
		$coupons = WC()->cart->get_coupons();
		if ( ! empty( $coupons ) ) {
			foreach ( $coupons as $coupon ) {
				$prepare_data[ 'coupon_' . $coupon->get_code() ] = wc_format_decimal( $coupon->get_amount(), 2 ) . ' ' . $coupon->get_discount_type();
			}
		}

		//billing
		if ( ! empty( $data['billing'] ) ) {
			foreach ( $data['billing'] as $billing_key => $billing_value ) {
				if ( ! empty( $billing_value ) ) {
					$prepare_data[ 'billing_' . $billing_key ] = $billing_value;
				}
				unset( $all_fields["billing"][ 'billing_' . $billing_key ] );
			}
		}

		/// search for custom fields
		if ( ! empty( $all_fields["billing"] ) ) {
			$prepare_data = array_merge( $prepare_data, $this->sanitize_aditional_order_fields( $all_fields["billing"], $data['id'] ) );
		}

		//shipping
		if ( ! empty( $data['shipping'] ) ) {
			foreach ( $data['shipping'] as $shipping_key => $shipping_value ) {
				if ( ! empty( $shipping_value ) ) {
					$prepare_data[ 'shipping_' . $shipping_key ] = $shipping_value;
				}
				unset( $all_fields["shipping"][ 'shipping_' . $shipping_key ] );
			}
		}
		/// search for custom fields
		if ( ! empty( $all_fields["shipping"] ) ) {
			$prepare_data = array_merge( $prepare_data, $this->sanitize_aditional_order_fields( $all_fields["shipping"], $data['id'] ) );
		}

		return $prepare_data;
	}

	/**
	 * Grab UTM fields
	 *
	 * @return array
	 */
	private function get_utm_fields() {
		$data = [];
		if ( ! empty( $this->UTM_handler::get_referer_url() ) ) {
			$data['REFERER'] = $this->UTM_handler::get_referer_url();
		}

		if ( ! empty( $this->UTM_handler::get_my_host_name() ) ) {
			$data['HOSTNAME'] = $this->UTM_handler::get_my_host_name();
		}

		if ( ! empty( $this->UTM_handler::get_my_ip() ) ) {
			$data['USER_IP'] = $this->UTM_handler::get_my_ip();
		}

		if ( ! empty( $this->UTM_handler::get_roistat() ) ) {
			$data['ROISTAT_VISIT_ID'] = $this->UTM_handler::get_roistat();
		}

		return $data;
	}

	/**
	 * Preparing WC order products
	 *
	 * @param object $products
	 *
	 * @return array
	 */
	private function prepare_products( $products = [] ) {
		$prepare_data = [];
		if ( empty( $products ) ) {
			return $prepare_data;
		}

		foreach ( $products as $item_id => $item ) {
			$product       = $item->get_product();
			$regular_price = $product->get_regular_price();
			$price         = $product->get_price();
			$sku           = $product->get_sku();
			$product_id    = $product->get_id();

			$prepare_data[ $item_id ]['name']     = $item->get_name();
			$prepare_data[ $item_id ]['quantity'] = $item->get_quantity();
			$prepare_data[ $item_id ]['price']    = $price;
			$prepare_data[ $item_id ]['id']       = $product_id;
			if ( ! empty( $sku ) ) {
				$prepare_data[ $item_id ]['sku'] = $sku;
			}

			//If discounted
			if ( $price != $regular_price ) {
				$prepare_data[ $item_id ]['discount_type_id'] = 1;
				$prepare_data[ $item_id ]['discount_sum']     = $regular_price - $price;
			}
		}

		return $prepare_data;
	}

	/**
	 * Preparing WC product fields
	 *
	 * @param object $product
	 *
	 * @return array
	 */
	private function prepare_single_product( $product ) {
		if ( empty( $product ) ) {
			return array();
		}

		return array(
			'title'              => $product->get_name(),
			'id'                 => $product->get_id(),
			'created_at'         => (array) $product->get_date_created(),
			'updated_at'         => (array) $product->get_date_modified(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'downloadable'       => $product->is_downloadable(),
			'virtual'            => $product->is_virtual(),
			'permalink'          => $product->get_permalink(),
			'sku'                => $product->get_sku(),
			'price'              => wc_format_decimal( $product->get_price(), 2 ),
			'regular_price'      => wc_format_decimal( $product->get_regular_price(), 2 ),
			'sale_price'         => $product->get_sale_price() ? wc_format_decimal( $product->get_sale_price(), 2 ) : null,
			'price_html'         => $product->get_price_html(),
			'taxable'            => $product->is_taxable(),
			'tax_status'         => $product->get_tax_status(),
			'tax_class'          => $product->get_tax_class(),
			'managing_stock'     => $product->managing_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'in_stock'           => $product->is_in_stock(),
			'backorders_allowed' => $product->backorders_allowed(),
			'backordered'        => $product->is_on_backorder(),
			'sold_individually'  => $product->is_sold_individually(),
			'purchaseable'       => $product->is_purchasable(),
			'featured'           => $product->is_featured(),
			'visible'            => $product->is_visible(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'on_sale'            => $product->is_on_sale(),
			'weight'             => $product->get_weight() ? wc_format_decimal( $product->get_weight(), 2 ) : null,
			'dimensions'         => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
				'unit'   => get_option( 'woocommerce_dimension_unit' ),
			),
			'shipping_required'  => $product->needs_shipping(),
			'shipping_taxable'   => $product->is_shipping_taxable(),
			'shipping_class'     => $product->get_shipping_class(),
			'shipping_class_id'  => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
			'description'        => apply_filters( 'the_content', $product->get_description() ),
			'short_description'  => apply_filters( 'woocommerce_short_description', $product->get_short_description() ),
			'reviews_allowed'    => $product->get_reviews_allowed(),
			'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'       => $product->get_rating_count(),
			'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
			'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
			'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
			'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ),
			'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
			//'images'             => $this->get_images( $product ),
			'featured_src'       => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
			//'attributes'         => $this->get_attributes( $product ),
			//'downloads'          => $this->get_downloads( $product ),
			'download_limit'     => $product->get_download_limit(),
			'download_expiry'    => $product->get_download_expiry()
			//'download_type'      => 'standard',
			//'purchase_note'      => apply_filters( 'the_content', $product->get_purchase_note() )
			//'total_sales'        => $product->get_total_sales(),
		);
	}


	/**
	 * Sanitize additional order fields from current order and save them to order entity
	 *
	 * @param array $aditional_fields
	 * @param string $prefix
	 *
	 * @return array
	 */
	private function sanitize_aditional_order_fields( $aditional_fields, $order_id ) {
		global $wpdb;
		$prepare_data = [];
		foreach ( $aditional_fields as $field_name => $fields ) {
			$field_value = get_post_meta( $order_id, '_' . $field_name, true );
			if ( ! empty( $field_value ) ) {
				//$field_slug = empty(self::translit($field["label"])) ? $field_name : $prefix . self::translit($field["label"]);
				$prepare_data[ $field_name ] = $field_value;

				/// Saving Order field to DB
				$entity_saved_settings = $this->settings::get_saved_entity_settings_from_db( ' WHERE entiti="order" AND setting_key="extra_field" AND setting_name="' . $field_name . '"' );
				$response              = '';
				if ( empty( $entity_saved_settings ) ) {
					$response      = $wpdb->insert( $wpdb->prefix . $this->settings::$ainsys_entities_settings_table,
						array(
							'entiti'       => 'order',
							'setting_name' => $field_name,
							'setting_key'  => 'extra_field',
							'value'        => serialize( $fields )
						)
					);
					$field_data_id = $wpdb->insert_id;

					/// Save new field to log
					$this->logger->save_log_information( $field_data_id, $field_name, 'order_cstom_field_saved', '', 0 );
				} else {
					$response = $wpdb->update( $wpdb->prefix . $this->settings::$ainsys_entities_settings_table,
						array( 'value' => serialize( $fields ) ),
						array( 'id' => $entity_saved_settings["id"] )
					);
				}
			}
		}

		return $prepare_data;
	}


	public function get_coupons_fields() {
		return array(
			'code'                        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'discount_type'               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'amount'                      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'date_expires'                => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'individual_use'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'product_ids'                 => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'excluded_product_ids'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'usage_limit'                 => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'usage_limit_per_user'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'limit_usage_to_x_items'      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'free_shipping'               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'product_categories'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'excluded_product_categories' => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'exclude_sale_items'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'minimum_amount'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'maximum_amount'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			'email_restrictions'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
		);
	}

	/**
	 * Generate fields for USER entity
	 *
	 * @return array
	 */
	public function get_product_fields() {
		return array(
			"title"              => [
				"nice_name"   => __( 'Title', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"         => "woocommerce",
				"description" => "Product title"
			],
			"id"                 => [
				"nice_name" => __( '{ID}', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"created_at"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"updated_at"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"type"               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"status"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"downloadable"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"virtual"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"permalink"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"sku"                => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"price"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"regular_price"      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"sale_price"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"price_html"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"taxable"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"tax_status"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"tax_class"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"managing_stock"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"stock_quantity"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"in_stock"           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"backorders_allowed" => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"backordered"        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"sold_individually"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"purchaseable"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"featured"           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"visible"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"catalog_visibility" => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"on_sale"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"weight"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"dimensions"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_required"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_taxable"   => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_class"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"shipping_class_id"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"nice_name"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"short_nice_name"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"reviews_allowed"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"average_rating"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"rating_count"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"related_ids"        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"upsell_ids"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"cross_sell_ids"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"categories"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"tags"               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			//"images"
			"featured_src"       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			//"attributes"
			//"downloads"
			"download_limit"     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"download_expiry"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			//"download_type"
			//"purchase_note"
			//"total_sales"
		);
	}

	/**
	 * Generate fields for ORDER entity
	 *
	 * @return array
	 */
	public function get_order_fields() {
		$prepared_fields = [
			"id"                   => [
				"nice_name" => __( '{ID}', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"currency"             => [
				"nice_name" => __( 'Currency', AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"customer_id"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"payment_method_title" => [
				"nice_name" => __( "Payment", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"date"                 => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"referer"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"hostname"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"user_ip"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			],
			"products"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "woocommerce"
			]
		];

		$order_fields = WC()->checkout->get_checkout_fields();

		foreach ( $order_fields as $category => $fields ) {
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field_slug => $settings ) {
					$prepared_fields[ $field_slug ] = [
						"nice_name"   => $settings["label"] ?? '',
						"description" => $settings["label"] ?? '',
						"api"         => "woocommerce",
						"required"    => isset( $settings["required"] ) && $settings["required"] ? 1 : 0,
						"sample"      => isset( $settings["placeholder"] ) ? $settings["placeholder"] : ''
					];
				}
			} else {
				$prepared_fields[ $category ] = [
					"api" => "woocommerce",
				];
			}
		}

		$order_saved_settings = $this->settings::get_saved_entity_settings_from_db( ' WHERE entiti="order" AND setting_key="extra_field"', false );
		$order_extra_fields   = [];
		if ( ! empty( $order_saved_settings ) ) {
			foreach ( $order_saved_settings as $saved_setting ) {
				//preg_match('/(?<cat>\S+)_/', $saved_setting["setting_name"], $matches);
				$order_extra_fields[ $saved_setting["setting_name"] ]        = maybe_unserialize( $saved_setting["value"] );
				$order_extra_fields[ $saved_setting["setting_name"] ]['api'] = 'mixed';
			}
		}
		$prepared_fields = array_merge(
			$prepared_fields,
			apply_filters( 'ainsys_woocommerce_extra_fields_for_order', $order_extra_fields )
		);

		return $prepared_fields;
	}


}