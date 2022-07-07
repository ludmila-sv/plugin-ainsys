<?php

if ( ! function_exists( 'ainsysconnector_autoloader' ) ) {

	function ainsysconnector_autoloader( $class_name ) {

		if ( false !== strpos( $class_name, 'Ainsysconnector' ) ) {

			$parts = explode( '\\', $class_name );
			if ( count( $parts ) < 2 ) {
				return false;
			}

			$family_slug                  = strtolower( $parts[0] );
			$plugin_slug                  = str_replace( '_', '-', strtolower( $parts[1] ) );
			$plugin_slug                  = $plugin_slug;
			$parts[ count( $parts ) - 1 ] = 'class-' . str_replace( '_', '-', strtolower( $parts[ count( $parts ) - 1 ] ) );
			unset( $parts[0] );
			unset( $parts[1] );

			// parse the rest as is without any lowercase modifications.

			$relative_path_part = implode( DIRECTORY_SEPARATOR, $parts ) . '.php';

			$class_file_path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $family_slug . '-' . $plugin_slug . DIRECTORY_SEPARATOR . $relative_path_part;

			if ( file_exists( $class_file_path ) ) {

				require_once $class_file_path;
				return true;
			}

			return false;

		}

	}

	spl_autoload_register( 'ainsysconnector_autoloader' );
}
