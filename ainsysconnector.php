<?php

//namespace Ainsysconnector\Master;

/**
 * @link              https://github.com/ainsys/ainsys-wp-connector-plugin
 * @since             1.0.0
 * @package           Ainsysconnector
 *
 * @wordpress-plugin
 * Plugin Name:       AINSYS connector
 * Plugin URI: https://app.ainsys.com/
 * Description: AINSYS connector master.
 * Version:           3.0.0
 * Author:            AINSYS
 * Author URI:        https://app.ainsys.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     Ainsysconnector
 * Domain Path:     /languages
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access allowed' );
}

define( 'AINSYS_CONNECTOR_BASENAME', plugin_basename( __FILE__ ) );
define( 'AINSYS_CONNECTOR_VERSION', '3.0' );
define( 'AINSYS_CONNECTOR_PLUGIN', __FILE__ );
define( 'AINSYS_CONNECTOR_PLUGIN_DIR', untrailingslashit( dirname( AINSYS_CONNECTOR_PLUGIN ) ) );
define( 'AINSYS_CONNECTOR_TEXTDOMAIN', 'ainsys_connector' );
define( 'AINSYS_CONNECTOR_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/autoloader.php';

use Ainsysconnector\Master\Core\Ainsys_Init;
Ainsys_Init::init();

use Ainsysconnector\Master\Settings\Ainsys_Settings;
Ainsys_Settings::init();

use Ainsysconnector\Master\Settings\Ainsys_Html;

//include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/class-ainsys-settings.php';
//include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/ainsys-html.php';
include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/ainsys-core.php';
include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/ainsys-webhook-listener.php';
include_once AINSYS_CONNECTOR_PLUGIN_DIR . '/includes/utm-hendler.php';
