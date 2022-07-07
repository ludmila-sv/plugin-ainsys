<?php

namespace Ainsysconnector\Master\Traits;

if ( ! trait_exists( 'PluginCommon' ) ) {
	trait PluginCommon {

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
		 * @param  string  $name
		 *
		 * @return string
		 */
		public static function get_option_name( $name ) {
			return self::get_plugin_name() . '_' . $name;
		}

		/**
		 * Get plugin uniq name to setting
		 *
		 * @return string
		 */
		public static function get_plugin_name() {
			return 'ansys_connector';
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
		 * Is plugin active
		 *
		 * @param  string  $plugin
		 *
		 * @return bool
		 */
		public static function is_plugin_active( $plugin ) {
			return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
		}

	}
}
