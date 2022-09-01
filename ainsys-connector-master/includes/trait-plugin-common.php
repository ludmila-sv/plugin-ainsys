<?php
/**
 * Plugin_Common trait.
 *
 * @package ainsys-connector-master
 */

namespace Ainsys\Connector\Master;

trait Plugin_Common {

	/**
	 * @var array  which contains all components instances to be used.
	 */
	public $components = array();

	/**
	 * Version of plugin from metadata.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Path of Plugin file.
	 *
	 * @var string
	 */
	public $plugin_file_name_path;

	/**
	 * Plugin's directory.
	 *
	 * @var string
	 */
	public $plugin_dir_path;

	/**
	 * Plugin's directory URL.
	 *
	 * @var string
	 */
	public $plugin_dir_url;

	/**
	 * @var string
	 */
	public $text_domain_path;

	/**
	 * @var string
	 */
	public $text_domain;

	/**
	 * Inits plugin's metadata for class based plugin.
	 *
	 * @param string $plugin_file_path Path of plugin's file.
	 */
	private function init_plugin_metadata( $plugin_file_path = '' ) {

		if ( empty( $plugin_file_path ) ) {
			// let's resolve it by naming conventions.
			$namespace_parts = explode( '\\', static::class );
			array_pop( $namespace_parts );// remove last one as it's class name.
			$plugin_file_path = WP_PLUGIN_DIR
			                    . DIRECTORY_SEPARATOR
			                    . strtolower( str_replace( '_', '-', implode( '-', $namespace_parts ) ) )
			                    . DIRECTORY_SEPARATOR
			                    . 'plugin.php';
		}

		$this->plugin_file_name_path = $plugin_file_path;
		$this->plugin_dir_path       = plugin_dir_path( $this->plugin_file_name_path );
		$this->plugin_dir_url        = plugin_dir_url( $this->plugin_file_name_path );
		$plugin_data                 = get_file_data( $this->plugin_file_name_path,
			array(
				'Version'     => 'Version',
				'Text Domain' => 'Text Domain',
				'Domain Path' => 'Domain Path',
			),
			'plugin'
		);

		$this->version          = $plugin_data['Version'] ?? '1.0';
		$this->text_domain_path = $plugin_data['Domain Path'] ?? '/languages';
		$this->text_domain      = $plugin_data['Text Domain'] ?? '';

	}


	/**
	 * Returns base plugin name based on it's main Namespace - useful for localize script variable names.
	 *
	 * @return string
	 */
	public static function get_short_name() {

		$namespace_parts = explode( '\\', static::class );

		return $namespace_parts[0] . $namespace_parts[1] ?? '';

	}

	/**
	 * Is plugin active
	 *
	 * @param string $plugin
	 *
	 * @return bool
	 */
	public function is_plugin_active( $plugin ) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
	}

}
