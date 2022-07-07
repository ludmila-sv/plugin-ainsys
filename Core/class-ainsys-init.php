<?php

namespace Ainsysconnector\Master\Core;

/**
 * AINSYS connector init.
 *
 * @class          AINSYS connector initialization
 * @version        1.0.0
 * @author         AINSYS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Ainsys_Init' ) ) {
	class Ainsys_Init {

		use \Ainsysconnector\Master\Traits\PluginCommon;

		static $ainsys_log_table   = 'ainsys_log';
		static $settings_page_name = 'ainsys_settings';

		/**
		 * init
		 *
		 * @return
		 */
		static function init() {
			register_activation_hook( AINSYS_CONNECTOR_PLUGIN, array( __CLASS__, 'activation' ) );
			register_deactivation_hook( AINSYS_CONNECTOR_PLUGIN, array( __CLASS__, 'deactivation' ) );
			register_uninstall_hook( AINSYS_CONNECTOR_PLUGIN, array( __CLASS__, 'uninstall' ) );

			if ( is_admin() ) {
				add_action( 'admin_menu', array( __CLASS__, 'add_ansys_admin_menu' ) );
				add_filter(
					'plugin_action_links_ainsysconnector-master/ainsysconnector.php',
					array(
						__CLASS__,
						'generate_links_to_plugin_bar',
					)
				);
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
			}
		}

		/**
		 * Activation
		 *
		 */
		public static function activation() {

			/* 1. Check php version */

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( version_compare( PHP_VERSION, '7.2.0' ) < 0 ) {
				add_action( 'admin_notices', 'ainsys_connector_error' );
				deactivate_plugins( AINSYS_CONNECTOR_BASENAME );
			}
			function ainsys_connector_error() {
				$class    = 'notice notice-error is-dismissible';
				$message1 = __( 'Upgrade your PHP version. Minimum version - 7.2+. Your PHP version ', 'AINSYS_CONNECTOR_TEXTDOMAIN' );
				$message2 = __( '! If you don\'t know how to upgrade PHP version, just ask in your hosting provider! If you can\'t upgrade - delete this plugin!', 'AINSYS_CONNECTOR_TEXTDOMAIN' );

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message1 . PHP_VERSION . $message2 ) );
			}

			/* 2. Localization */
			function ainsys_connector_load_textdomain() {
				$locale = apply_filters( 'plugin_locale', get_locale(), 'AINSYS_CONNECTOR_TEXTDOMAIN' );
				unload_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN );
				load_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN, WP_LANG_DIR . '/plugins/ainsys_connector-' . $locale . '.mo' );
				load_plugin_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			}
			add_action( 'init', 'ainsys_connector_load_textdomain' );

			/* 3. Create log table */
			global $wpdb;
			$wpdb->hide_errors();

			$charset_collate = '';
			if ( $wpdb->has_cap( 'collation' ) ) {
				$charset_collate = $wpdb->get_charset_collate();
			}

			$table_name = $wpdb->prefix . self::$ainsys_log_table;

			//Check to see if the table exists already, if not, then create it
			if ( $wpdb->get_var( $wpdb->prepare( 'show tables like %s', $table_name ) ) !== $table_name ) {
				$sql = "CREATE TABLE $table_name (
					log_id bigint unsigned NOT NULL AUTO_INCREMENT,
					object_id bigint NOT NULL,
					request_action varchar(100) NOT NULL,
					request_data text DEFAULT NULL,
					serrver_responce text DEFAULT NULL,
					incoming_call smallint NOT NULL,
					creation_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY  (log_id),
					KEY object_id (object_id)
				) $charset_collate;";

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				dbDelta( $sql );
			}

			flush_rewrite_rules();
		}

		/**
		 * Deactivation.
		 *
		 * @return
		 */
		public static function deactivation() {
			remove_menu_page( self::$settings_page_name );
			flush_rewrite_rules();
		}


		/**
		 * Uninstall.
		 *
		 * @return
		 */
		public static function uninstall() {
			if ( (int) self::get_option( 'full_uninstall' ) ) {
				global $wpdb;
				$table_name = $wpdb->prefix . self::$ainsys_log_table;
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %s', $table_name ) );

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
				delete_option( self::get_setting_name( 'debug_log' ) );

				delete_option( 'ainsys-webhook_url' );
			}
		}

		/**
		 * Register settings page in menu
		 *
		 */
		public static function add_ansys_admin_menu() {
			add_menu_page(
				__( 'AINSYS connector integration', 'AINSYS_CONNECTOR_TEXTDOMAIN' ),
				__( 'AINSYS connector', 'AINSYS_CONNECTOR_TEXTDOMAIN' ),
				'administrator',
				self::$settings_page_name,
				array( __CLASS__, 'include_settings_page' ),
				'dashicons-randomize',
				10
			);
		}

		/**
		 * Include settings page
		 *
		 */
		public static function include_settings_page() {
			include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/settings-page.php';
		}

		/**
		 * Add links to settings and ainsys portal
		 *
		 * @param $links
		 *
		 * @return mixed
		 */
		public static function generate_links_to_plugin_bar( $links ) {
			$settings_url = esc_url(
				add_query_arg(
					'page',
					self::$settings_page_name,
					get_admin_url() . 'admin.php'
				)
			);

			$settings_link = '<a href="' . $settings_url . '">' . __( 'Settings' ) . '</a>';
			$plugin_link   = '<a target="_blank" href="https://app.ainsys.com/en/settings/workspaces">AINSYS dashboard</a>';

			array_push( $links, $settings_link, $plugin_link );

			return $links;
		}

		/**
		 * Enqueue admin styles and scripts
		 *
		 * @return
		 */
		public static function admin_enqueue_scripts() {
			wp_enqueue_script( 'ainsys_connector_admin_handle', plugins_url( 'assets/js/ainsys_connector_admin.js', AINSYS_CONNECTOR_PLUGIN ), array( 'jquery' ), '2.0.0', true );

			if ( isset( $_GET['page'] ) && self::$settings_page_name === $_GET['page'] ) {
				//wp_enqueue_script('jquery-ui-sortable');
				wp_enqueue_style( 'ainsys_connector_style_handle', plugins_url( 'assets/css/ainsys_connector_style.css', AINSYS_CONNECTOR_PLUGIN ) );
				wp_enqueue_style( 'font-awesome_style_handle', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );

				wp_enqueue_script( 'ainsys_connector_admin_handle', plugins_url( 'assets/js/ainsys_connector_admin.js', AINSYS_CONNECTOR_PLUGIN ), array( 'jquery' ), '2.0.0', true );
				wp_localize_script(
					'ainsys_connector_admin_handle',
					'ainsys_connector_params',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'ansys_admin_menu_nonce' ),
					)
				);
			}

			return;
		}

	}
}
