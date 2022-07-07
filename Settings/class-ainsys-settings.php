<?php

namespace Ainsysconnector\Master\Settings;

/**
 * AINSYS connector core.
 *
 * @class          AINSYS connector settings
 * @version        1.0.0
 * @author         AINSYS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Ainsys_Settings' ) ) {
	class Ainsys_Settings {

		use \Ainsysconnector\Master\Traits\PluginCommon;

		static $do_log_transactions;

		/**
		 * Class init
		 *
		 * @return
		 */
		static function init() {
			self::generate_log();

			add_action( 'admin_init', array( __CLASS__, 'register_options' ) );

			echo get_option( 'plugin_error' );

			return;
		}


		/**
		 * Generate log using time settings.
		 *
		 */
		static function generate_log() {
			$currant_date = gmdate( 'Y-m-d H:i:s' );
			$limit_date   = (int) self::get_option( 'log_until_certain_time' ) ? gmdate( 'Y-m-d H:i:s', self::get_option( 'log_until_certain_time' ) ) : '';

			if ( ( ! (int) self::get_option( 'log_until_certain_time' ) || $currant_date < $limit_date )
				&& (int) self::get_option( 'do_log_transactions' )
			) {
				self::$do_log_transactions = 1;
			} else {
				self::$do_log_transactions = 0;
			}
		}

		/**
		 * Truncate log table.
		 *
		 */
		static function truncate_log_table() {
			global $wpdb;
			$table_name = $wpdb->prefix . Ainsys_Init::$ainsys_log_table;
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %s', $table_name ) );
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

			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'connectors' ) );
			register_setting(
				self::get_setting_name( 'group' ),
				self::get_setting_name( 'hook_url' ),
				array(
					__CLASS__,
					'generate_hook_url',
				)
			);
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'backup_email' ) );
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'do_log_transactions' ), array( 'default' => 1 ) );
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'log_until_certain_time' ) );
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'full_uninstall' ), array( 'default' => 0 ) );

			/*  DEBUG   */
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'debug_log' ) );

			// not used
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'display_debug' ), array( 'default' => 0 ) );
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'workspace' ), array( 'default' => 14 ) );
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'server' ), array( 'default' => 'https://user-api.ainsys.com/' ) );
			register_setting( self::get_setting_name( 'group' ), self::get_setting_name( 'sys_id' ) );
		}

		/**
		 * Generate hook
		 *
		 * @return string
		 */
		public static function generate_hook_url() {
			return site_url( '/?ainsys_webhook=' . \Ainsysconnector\Master\ainsys_webhook_listener::$request_token, 'https' );
		}

	}
}
