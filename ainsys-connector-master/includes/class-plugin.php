<?php

namespace Ainsys\Connector\Master;

use Ainsys\Connector\Master\Settings\Admin_UI;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\Webhooks\Handle_Comment;
use Ainsys\Connector\Master\Webhooks\Handle_User;
use Ainsys\Connector\Master\WP\Process_Comments;
use Ainsys\Connector\Master\WP\Process_Users;

defined( 'ABSPATH' ) || die();

class Plugin implements Hooked {
	use Is_Singleton;
	use Plugin_Common;


	/**
	 * Key is __FILE__ of respective plugin and value is fully qualified
	 * with namespace Class name of plugin to be instantiated.
	 * @var array
	 */
	public $child_plugin_classes = array();

	public $child_plugins = array();

	/**
	 * @var DI_Container;
	 */
	public $di_container;

	/**
	 * Plugin constructor.
	 *
	 */
	private function __construct() {

		$this->init_plugin_metadata();

		define( 'AINSYS_CONNECTOR_BASENAME', plugin_basename( $this->plugin_file_name_path ) ); // not used legacy constant.
		define( 'AINSYS_CONNECTOR_VERSION', $this->version );
		define( 'AINSYS_CONNECTOR_PLUGIN', $this->plugin_file_name_path );
		define( 'AINSYS_CONNECTOR_PLUGIN_DIR', untrailingslashit( dirname( $this->plugin_file_name_path ) ) ); // not used legacy constant.
		define( 'AINSYS_CONNECTOR_URL', $this->plugin_dir_url );

		$this->di_container = DI_Container::get_instance();
		/**
		 * Inject here all components needed for plugin.
		 * It's good to follow same logic in child plugins if it has multiple classes which share functionality
		 * among the plugin.
		 */

		$this->components['settings']          = $this->di_container->resolve( Settings::class );
		$this->components['logger']            = $this->di_container->resolve( Logger::class );
		$this->components['settings_admin_ui'] = $this->di_container->resolve( Admin_UI::class );
		$this->components['core']              = $this->di_container->resolve( Core::class );
		$this->components['utm_handler']       = $this->di_container->resolve( UTM_Handler::class );
		$this->components['process_users']     = $this->di_container->resolve( Process_Users::class );
		$this->components['process_comments']  = $this->di_container->resolve( Process_Comments::class );
		$this->components['webhooks']          = $this->di_container->resolve( Webhook_Listener::class );
		$this->components['webhook_user']      = $this->di_container->resolve( Handle_User::class );
		$this->components['webhook_comment']   = $this->di_container->resolve( Handle_Comment::class );


	}

	/**
	 *
	 */
	public function init_hooks() {
		register_activation_hook( $this->plugin_file_name_path, array( $this, 'activate' ) );
		register_deactivation_hook( $this->plugin_file_name_path, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_action( 'plugins_loaded', array( $this, 'load_child_plugins' ) );
		/*
		 * Initialize hooks for all inner plugin's components.
		 */
		foreach ( $this->components as $component ) {
			if ( $component instanceof Hooked ) {
				$component->init_hooks();
			}
		}

	}


	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), AINSYS_CONNECTOR_TEXTDOMAIN );
		unload_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN );
		load_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN, WP_LANG_DIR . '/plugins/ainsys-connector-' . $locale . '.mo' );
		load_plugin_textdomain( AINSYS_CONNECTOR_TEXTDOMAIN, false, dirname( $this->plugin_file_name_path ) . '/languages/' );
	}


	public function load_child_plugins() {
		/**
		 * After all core components are loaded, we can load child plugins.
		 */
		$this->child_plugin_classes = apply_filters( 'ainsys_child_plugins_to_be_loaded', array() );

		foreach ( $this->child_plugin_classes as $child_plugin_class_name ) {
			if ( class_exists( $child_plugin_class_name ) ) {

				$this->child_plugins[ $child_plugin_class_name ] = $this->di_container->resolve( $child_plugin_class_name );
			}
		}

		// now lets init their hooks as well.

		foreach ( $this->child_plugins as $child_plugin ) {
			if ( $child_plugin instanceof Hooked ) {
				$child_plugin->init_hooks();
			}
		}

		// now our child plugins got linked to WP.
	}

	/**
	 * Action for plugin activation.
	 */
	public function activate() {
		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'activate' ) ) {
				$component->activate();
			}
		}
		foreach ( $this->child_plugins as $component ) {
			if ( method_exists( $component, 'activate' ) ) {
				$component->activate();
			}
		}
	}


	/**
	 * Action on plugin deactivation.
	 * Cleans up everything.
	 */
	public function deactivate() {

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'deactivate' ) ) {
				$component->deactivate();
			}
		}


	}


	public static function uninstall() {

		$instance = static::get_instance();

		foreach ( $instance->components as $component ) {
			if ( method_exists( $component, 'uninstall' ) ) {
				$component->uninstall();
			}
		}
	}

}