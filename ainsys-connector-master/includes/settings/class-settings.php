<?php

namespace Ainsys\Connector\Master\Settings;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Listener;

/**
 * AINSYS connector core.
 *
 * @class          AINSYS connector settings
 * @version        1.0.0
 * @author         AINSYS
 */
class Settings implements Hooked {

	// AINSYS log table name.
	static $ainsys_entities_settings_table = 'ainsys_entitis_settings';

	/**
	 * Connect to wp hooks.
	 *
	 */
	public function init_hooks() {

		add_action( 'admin_init', array( $this, 'register_options' ) );

		add_action( 'init', array( $this, 'check_to_auto_disable_logging' ) );
//		echo get_option( 'plugin_error' );

	}


	/**
	 * Autodisables logging.
	 *
	 * @return void
	 */
	public function check_to_auto_disable_logging() {
		$logging_enabled = (int) self::get_option( 'do_log_transactions' );
		// Generate log until time settings
		$current_time = time();
		$limit_time   = (int) self::get_option( 'log_until_certain_time' );

		// make it really infinite as in select infinite option is -1;
		if ( $limit_time < 0 ) {
			return;
		}

		if ( $logging_enabled && $limit_time && ( $current_time < $limit_time ) ) {
			self::set_option( 'do_log_transactions', 1 );
		} else {
			self::set_option( 'do_log_transactions', 0 );
			self::set_option( 'log_until_certain_time', 0 );
		}
	}
	///////////////////////////////////////

	/**
	 * Get options value by name
	 *
	 * @param $name
	 *
	 * @return mixed|void
	 */
	public static function get_option( $name ) {
		return get_option( self::get_option_name( $name ) );
	}

	/**
	 * Get full options name
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public static function get_option_name( $name ) {
		return self::get_plugin_name() . '_' . $name;
	}

	//////////////////////////////

	/**
	 * Get plugin uniq name to setting
	 *
	 * @return string
	 */
	public static function get_plugin_name() {
		return 'ansys_connector_woocommerce';//strtolower( str_replace( '\\', '_', __NAMESPACE__ ) );
	}


	/**
	 * Install tables
	 *
	 * @return
	 */
	public static function activate() {
		global $wpdb;

		update_option( self::get_plugin_name(), AINSYS_CONNECTOR_VERSION );

		flush_rewrite_rules();
		ob_start();

		$wpdb->hide_errors();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( self::get_schema() );

		update_option( self::get_plugin_name() . '_db_version', AINSYS_CONNECTOR_VERSION );

		ob_get_clean();

		return;
	}

	/**
	 * Get Table schema.
	 *
	 * @return string
	 */
	private static function get_schema() {
		global $wpdb;
		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of WordPress 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 *
		 * This may cause duplicate index notices in logs due to https://core.trac.wordpress.org/ticket/34870 but dropping
		 * indexes first causes too much load on some servers/larger DB.
		 */

		$table_entitis_settings = $wpdb->prefix . self::$ainsys_entities_settings_table;

		$tables = "CREATE TABLE {$table_entitis_settings} (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `entiti` text DEFAULT NULL,
                `setting_name` text DEFAULT NULL,
                `setting_key` text DEFAULT NULL,
                `value` text DEFAULT NULL,
                `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (`id`)
            ) $collate;";

		return $tables;
	}

	/**
	 * Remove logs, settings etc.
	 *
	 * @return
	 */
	public static function deactivate() {
		if ( (int) self::get_option( 'full_uninstall' ) ) {
			self::uninstall();
		}

		return;
	}

	public static function uninstall() {
		delete_option( self::get_setting_name( 'ansys_api_key' ) );
		delete_option( self::get_setting_name( 'handshake_url' ) );
		delete_option( self::get_setting_name( 'webhook_url' ) );
		delete_option( self::get_setting_name( 'connectors' ) );
		delete_option( self::get_setting_name( 'server' ) );
		delete_option( self::get_setting_name( 'workspace' ) );
		delete_option( self::get_setting_name( 'hook_url' ) );
		delete_option( self::get_setting_name( 'backup_email' ) );
		delete_option( self::get_setting_name( 'do_log_transactions' ) );
		delete_option( self::get_setting_name( 'log_until_certain_time' ) );
		delete_option( self::get_setting_name( 'display_debug' ) );
		delete_option( self::get_setting_name( 'full_uninstall' ) );
		delete_option( self::get_plugin_name() );
		delete_option( self::get_plugin_name() . '_db_version' );

		delete_option( self::get_setting_name( 'debug_log' ) );

		delete_option( 'ainsys-webhook_url' );

		global $wpdb;
		$wpdb->query( sprintf( "DROP TABLE IF EXISTS %s",
			$wpdb->prefix . self::$ainsys_entities_settings_table ) );
	}

	/**
	 * Get full options name
	 *
	 * @param $name
	 *
	 * @return string
	 */
	public static function get_setting_name( $name ) {
		return self::get_plugin_name() . '_' . $name;
	}

	/**
	 * Get options value by name
	 *
	 * @param $name
	 *
	 * @return mixed|void
	 */
	public static function set_option( $name, $value ) {
		return update_option( self::get_option_name( $name ), $value );
	}


	/**
	 * Get saved email or admin email
	 *
	 * @return bool|mixed|void
	 */
	public static function get_backup_email() {
		if ( ! empty( self::get_option( 'backup_email' ) ) ) {
			return self::get_option( 'backup_email' );
		}

		if ( ! empty( get_option( 'admin_email' ) ) ) {
			return get_option( 'admin_email' );
		}

		return false;
	}


	/**
	 * Register options
	 *
	 */
	public static function register_options() {
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'ansys_api_key' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'handshake_url' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'webhook_url' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'server' ), [ 'default' => 'https://user-api.ainsys.com/' ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'sys_id' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'connectors' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'workspace' ), [ 'default' => 14 ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'hook_url' ), [
			Webhook_Listener::class,
			'get_webhook_url'
		] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'backup_email' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'do_log_transactions' ), [ 'default' => 1 ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'log_until_certain_time' ) );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'display_debug' ), [ 'default' => 0 ] );
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'full_uninstall' ), [ 'default' => 0 ] );

		/*  DEBUG   */
		register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'debug_log' ) );
	}


	/**
	 * Generate list of Entities.
	 *
	 * @return array
	 */
	static function get_entities() {
		/// Get Wordpress pre installed entities.
		$entities = array(
			'user'     => __( 'User / fields', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'comments' => __( 'Comments / fields', AINSYS_CONNECTOR_TEXTDOMAIN )
		);

		return apply_filters( 'ainsys_get_entities_list', $entities );
	}

	/**
	 * Used to give ability to provide specific to entity type fields getters supplied by other plugins.
	 *
	 * @return array
	 */
	public static function get_entity_fields_handlers() {
		$field_getters = array(
			'user'     => array( static::class, 'get_user_fields' ),
			'comments' => array( static::class, 'get_comments_fields' ),
		);

		return apply_filters( 'ainsys_get_entity_fields_handlers', $field_getters );
	}


	/**
	 * Generate list of settings for entity field with default values
	 * $entiti param used for altering settins depending on entity
	 *
	 * @param string $entity
	 *
	 * @return array
	 */
	static function get_entities_settings( $entity = '' ) {

		$default_apis = apply_filters(
			'ainsys_default_apis_for_entities',
			array(
				'wordpress' => '',
			)
		);

		return array(
			'id'          => array(
				'nice_name' => __( 'Id', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '',
				'type'      => 'constant',
			),
			'api'         => array(
				'nice_name' => __( 'API', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => $default_apis,
				'type'      => 'constant',
			),
			'read'        => array(
				'nice_name' => __( 'Read', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '1',
				'type'      => 'bool'
			),
			'write'       => array(
				'nice_name' => __( 'Write', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '0',
				'type'      => 'bool'
			),
			'required'    => array(
				'nice_name' => __( 'Required', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '0',
				'type'      => 'bool'
			),
			'unique'      => array(
				'nice_name' => __( 'Unique', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '0',
				'type'      => 'bool'
			),
			'data_type'   => array(
				'nice_name' => __( 'Data type', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => array(
					'string' => '1',
					'int'    => '',
					'bool'   => '',
					'mixed'  => ''
				),
				'type'      => $entity === 'acf' ? 'constant' : 'select'
			),
			'description' => array(
				'nice_name' => __( 'Description', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '',
				'type'      => 'string'
			),
			'sample'      => array(
				'nice_name' => __( 'Sample', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'default'   => '',
				'type'      => 'string'
			)
		);
	}

	//#region Refactored

	/**
	 * Get entiti field settings from DB.
	 *
	 * @param string $where
	 * @param bool $single
	 *
	 * @return array
	 */
	public static function get_saved_entity_settings_from_db( $where = '', $single = true ) {
		global $wpdb;
		$query   = "SELECT * 
        FROM " . $wpdb->prefix . self::$ainsys_entities_settings_table . $where;
		$resoult = $wpdb->get_results( $query, ARRAY_A );
		if ( isset( $resoult[0]["value"] ) && $single ) {
			$keys = array_column( $resoult, 'setting_key' );
			if ( count( $resoult ) > 1 && isset( array_flip( $keys )['saved_field'] ) ) {
				$saved_settins_id = array_flip( $keys )['saved_field'];
				$data             = maybe_unserialize( $resoult[ $saved_settins_id ]["value"] );
				$data['id']       = $resoult[ $saved_settins_id ]["id"] ?? 0;
			} else {
				$data       = maybe_unserialize( $resoult[0]["value"] );
				$data['id'] = $resoult[0]["id"] ?? 0;
			}
		} else {
			$data = $resoult;
		}

		return $data ?? array();
	}


	/**
	 * Generate fields for COMMENTS entity
	 *
	 * @return array
	 */
	static function get_comments_fields() {
		$prepered_fields = array(
			'comment_ID'           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_post_ID'      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author'       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author_email' => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author_url'   => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_author_IP'    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_date'         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_date_gmt'     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_content'      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_karma'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_approved'     => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_agent'        => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_type'         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'comment_parent'       => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'user_id'              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'children'             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'populated_children'   => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			'post_fields'          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
		);

		$extra_fields = apply_filters( 'ainsys_prepare_extra_comment_fields', array() );

		return array_merge( $prepered_fields, $extra_fields );
	}

	/**
	 * Generate fields for USER entity
	 *
	 * @return array
	 */
	static function get_user_fields() {
		$prepered_fields = array(
			"ID"                   => [
				"nice_name" => __( "{ID}", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_login"           => [
				"nice_name" => __( "User login", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_nicename"        => [
				"nice_name" => __( "Readable name", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_email"           => [
				"nice_name" => __( "User mail", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress",
				"children"  => [
					"primary"   => [
						"nice_name" => __( "Main email", AINSYS_CONNECTOR_TEXTDOMAIN ),
						"api"       => "wordpress"
					],
					"secondary" => [
						"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
						"api"       => "wordpress"
					]
				]
			],
			"user_url"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_registered"      => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_activation_key"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"user_status"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"display_name"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"first_name"           => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"last_name"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"nickname"             => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"nice_name"            => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"rich_editing"         => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"syntax_highlighting"  => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"comment_shortcuts"    => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"admin_color"          => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"use_ssl"              => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"show_admin_bar_front" => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			],
			"locale"               => [
				"nice_name" => __( "", AINSYS_CONNECTOR_TEXTDOMAIN ),
				"api"       => "wordpress"
			]
		);

		$extra_fields = apply_filters( 'ainsys_prepare_extra_user_fields', array() );

		return array_merge( $prepered_fields, $extra_fields );
	}

}
