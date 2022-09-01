<?php

namespace Ainsys\Connector\Master\Settings;


use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\WP\Process_Comments;

class Admin_UI implements Hooked {


	/**
	 * Storage for admin notices.
	 *
	 * @var array
	 */
	static $notices = [];

	static $nonce_title = 'ansys_admin_menu_nonce';

	/**
	 * @var Settings
	 */
	public $settings;

	/**
	 * @var Core
	 */
	public $core;

	/**
	 * @var Logger
	 */
	public $logger;

	public function __construct( Settings $settings, Core $core, Logger $logger ) {
		$this->settings = $settings;
		$this->core     = $core;
		$this->logger   = $logger;
	}


	public function init_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_filter( 'plugin_action_links_ainsys-connector-master/plugin.php',
				array(
					$this,
					'generate_links_to_plugin_bar'
				)
			);
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
		// let's register ajax handlers as it's a part of admin UI. NB: they were a part of Core originally.
		add_action( 'wp_ajax_remove_ainsys_integration', array( $this, 'remove_ainsys_integration' ) );

		add_action( 'wp_ajax_save_entiti_settings', array( $this, 'save_entities_settings' ) );

		add_action( 'wp_ajax_reload_log_html', array( $this, 'reload_log_html' ) );
		add_action( 'wp_ajax_toggle_logging', array( $this, 'toggle_logging' ) );
		add_action( 'wp_ajax_clear_log', array( $this, 'clear_log' ) );

	}

	/**
	 * Register setting page in menu
	 *
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'AINSYS connector integration', AINSYS_CONNECTOR_TEXTDOMAIN ),
			__( 'AINSYS connector', AINSYS_CONNECTOR_TEXTDOMAIN ),
			'administrator',
			__FILE__,
			[ $this, 'include_setting_page' ]
		);
	}

	/**
	 * Include settings page
	 *
	 */
	public function include_setting_page() {
		// NB: inside template we inherit $this which gives access to it's deps.
		include_once __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'settings.php';
	}

	/**
	 * Add links to settings and ainsys portal
	 *
	 * @param $links
	 *
	 * @return mixed
	 */
	public function generate_links_to_plugin_bar( $links ) {
		$settings_url = esc_url( add_query_arg(
			'page',
			plugin_basename( __FILE__ ),
			get_admin_url() . 'options-general.php'
		) );

		$settings_link = '<a href="' . $settings_url . '">' . __( 'Settings' ) . '</a>';
		$plugin_link   = '<a target="_blank" href="https://app.ainsys.com/en/settings/workspaces">AINSYS dashboard</a>';

		array_push( $links, $settings_link, $plugin_link );

		return $links;
	}


	/**
	 * Enqueue admin styles and scripts
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {

		wp_enqueue_script( 'ainsys_connector_admin_handle', plugins_url( 'assets/js/ainsys_connector_admin.js', AINSYS_CONNECTOR_PLUGIN ), array( 'jquery' ), '2.0.0', true );

		if ( false !== strpos( $_GET["page"] ?? '', 'ainsys-connector-master' ) ) {
			//wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_style( 'ainsys_connector_style_handle', plugins_url( "assets/css/ainsys_connector_style.css", AINSYS_CONNECTOR_PLUGIN ) );
			wp_enqueue_style( 'font-awesome_style_handle', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" );

			wp_enqueue_script( 'ainsys_connector_admin_handle', plugins_url( 'assets/js/ainsys_connector_admin.js', AINSYS_CONNECTOR_PLUGIN ), array( 'jquery' ), '2.0.0', true );
			wp_localize_script( 'ainsys_connector_admin_handle', 'ainsys_connector_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::$nonce_title ),
			) );


		}

		return;
	}

	/**
	 * Handshake with server, get AINSYS integration
	 *
	 */
	public function check_connection_to_server() {

		$ainsys_url = $this->settings::get_option( 'ansys_api_key' ); //https://user-api.ainsys.com/api/v0/workspace-management/workspaces/13/connectors/144/handshake/5ec1a0c99d428601ce42b407ae9c675e0836a8ba591c8ca6e2a2cf5563d97ff0/

		if ( ! empty( $ainsys_url ) && empty( $this->settings::get_option( 'webhook_url' ) ) ) {
			//new connector
			$response = '';
			try {
				$response = $this->core->curl_exec_func( [ 'hook_url' => $this->settings::get_option( 'ansys_api_key' ) ] );
			} catch ( \Exception $exception ) {

			}
			$webhook_data = ! empty( $response ) ? json_decode( $response ) : array();
			if ( ! empty( $response ) && isset( $webhook_data->webhook_url ) ) {
				$this->settings::set_option( 'webhook_url', $webhook_data->webhook_url );
			}

			// old connector
			//          $connectors = ainsys_settings::get_option('connectors');
//            if (empty($connectors)){
//                $server_url = empty(ainsys_settings::get_option('server')) ? 'https://user-api.ainsys.com/' : ainsys_settings::get_option('server');
//                $workspace = empty(ainsys_settings::get_option('workspace')) ? 14 : ainsys_settings::get_option('workspace');
//                $url = $server_url . 'api/v0/workspace-management/workspaces/' . $workspace . '/connectors/';
//                $sys_id = empty((int)ainsys_settings::get_option('sys_id')) ? 3 : (int)ainsys_settings::get_option('sys_id');
//                $post_fields = array(
//                    "name" => 'string',
//                    "system" => $sys_id,
//                    "workspace" => 14,
//                    "created_by" => 0);
//                $connectors_responce = self::curl_exec_func( $post_fields, $url );
//                $connectors_array = !empty($connectors_responce) ? json_decode($connectors_responce) : '';
//                if ( !empty($connectors_array) && isset($connectors_array->id) ){
//                    ainsys_settings::set_option('connectors', $connectors_array->id);
//                    $url = $server_url . 'api/v0/workspace-management/workspaces/'. $workspace . '/connectors/'. $connectors_array->id . '/handshake-url/';
//                    $url_responce = self::curl_exec_func('', $url );
//                    $url_array = !empty($url_responce) ? json_decode($url_responce) : '';
//                    if ( !empty($url_array) && isset($url_array->url) ){
//                        ainsys_settings::set_option('handshake_url', $url_array->url);
//                        $webhook_call = self::curl_exec_func( ['webhook_url' => ainsys_settings::get_option('hook_url')], $url_array->url );
//                        $webhook_array = !empty($webhook_call) ? json_decode($webhook_call) : '';
//                        if (! empty($webhook_call) && isset($webhook_array->webhook_url)){
//                            ainsys_settings::set_option('webhook_url', $webhook_array->webhook_url);
//                        }
//                    }
//                }
//            }
		}
	}


	public function admin_notices( $message, $status = 'success' ) {
		if ( self::$notices ) {
			foreach ( self::$notices as $notice ) {
				?>
				<div class="notice notice-<?php echo esc_attr( $notice['status'] ); ?>" is-dismissible>
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
				<?php
			}
		}
	}

	public function add_admin_notice( $message, $status = 'success' ) {
		self::$notices[] = [
			'message' => $message,
			'status'  => $status
		];
	}


	/**
	 * Check if AINSYS integration active
	 *
	 * @param string $actions
	 *
	 * @return string[]
	 */
	public function is_ainsys_integration_active( $actions = '' ) {

		$this->check_connection_to_server();

		$webhook_url = $this->settings::get_option( 'ansys_api_key' );

		// TODO check commented out code -  it's legacy copied as is.
//		if ( ! empty( $webhook_url ) && ! empty( get_option( 'ainsys-webhook_url' ) ) ) {
//			return array( 'status' => 'success' );
//		}
//
//		$request_to_ainsys = wp_remote_post( $webhook_url, [
//			'sslverify' => false,
//			'body'      => [
//				'webhook_url' => get_option( 'ansys_connector_woocommerce_hook_url' )
//			]
//		] );

//		if ( is_wp_error( $request_to_ainsys ) ) {
//			return array( 'status' => 'none' );
//		}

//		$parsed_response = json_decode( $request_to_ainsys['body'] );

		if ( $webhook_url ) {
			$this->add_admin_notice( 'Соединение с сервером Ainsys установлено. Webhook_url получен.' );

			return array( 'status' => 'success' );
		}

		return array( 'status' => 'none' );
	}


	//#region AJAX related parts.

	/**
	 * Remove ainsys integration information
	 *
	 * @return
	 */
	public function remove_ainsys_integration() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::$nonce_title ) ) {
			$this->settings::set_option( 'connectors', '' );
			$this->settings::set_option( 'ansys_api_key', '' );
			$this->settings::set_option( 'handshake_url', '' );
			$this->settings::set_option( 'webhook_url', '' );
			$this->settings::set_option( 'debug_log', '' );

			delete_option( 'ainsys-webhook_url' );
		}

		return;
	}


	/**
	 * Regenerate log HTML
	 *
	 */
	public function save_entities_settings() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::$nonce_title ) ) {
			$fields      = $_POST;
			$entiti      = isset( $_POST["entiti"] ) ? $_POST["entiti"] : '';
			$seting_name = $_POST["seting_name"] ? $_POST["seting_name"] : '';
			if ( ! $entiti && ! $seting_name ) {
				echo false;
				die();
			}

			$fields = $this->sanitise_fields_to_save( $fields );

			global $wpdb;
			$entiti_saved_settings = $this->settings::get_saved_entity_settings_from_db( ' WHERE entiti="' . $entiti . '" setting_key="saved_field" AND setting_name="' . $seting_name . '"' );
			$response              = '';
			if ( empty( $entiti_saved_settings ) ) {
				$response      = $wpdb->insert( $wpdb->prefix . Settings::$ainsys_entities_settings_table,
					array(
						'entiti'       => $entiti,
						'setting_name' => $seting_name,
						'setting_key'  => 'saved_field',
						'value'        => serialize( $fields )
					)
				);
				$field_data_id = $wpdb->insert_id;
			} else {
				$response      = $wpdb->update( $wpdb->prefix . $this->settings::$ainsys_entities_settings_table,
					array( 'value' => serialize( $fields ) ),
					array( 'id' => $entiti_saved_settings["id"] )
				);
				$field_data_id = $entiti_saved_settings["id"];
			}

			$request_action = 'field/' . $entiti . '/' . $seting_name;

			$fields = apply_filters( 'ainsys_update_entiti_fields', $fields );

			$request_data = array(
				'entity'  => [
					'id' => $field_data_id,
				],
				'action'  => $request_action,
				'payload' => $fields
			);

			try {
				$server_response = $this->core->curl_exec_func( $request_data );
			} catch ( \Exception $e ) {
				$server_response = 'Error: ' . $e->getMessage();
			}

			$this->logger->save_log_information( (int) $field_data_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

			echo $field_data_id ?? 0;
			die();
		}
		echo false;
		die();
	}

	public function sanitise_fields_to_save( $fields ) {
		// clear empty fields
//        foreach ($fields as $field => $val){
//            if (empty($val))
//                unset($fields[$field]);
//        }
		unset( $fields["action"], $fields["entiti"], $fields["nonce"], $fields["seting_name"], $fields["id"] );

		/// exclude 'constant' variables
		foreach ( $this->settings::get_entities_settings() as $item => $setting ) {
			if ( isset( $fields[ $item ] ) && $setting["type"] === 'constant' ) {
				unset( $fields[ $item ] );
			}
		}

		return $fields;
	}

	/**
	 * Regenerate log HTML
	 *
	 */
	public function reload_log_html() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::$nonce_title ) ) {
			echo $this->logger->generate_log_html();
		}
		die();
	}

	/**
	 * Toggle logging on/of. Set up time till log will be saved if $_POST["time"] specified
	 *
	 */
	public function toggle_logging() {
		if ( isset( $_POST["command"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::$nonce_title ) ) {
			/// Set time till log will be saved, 0 if infinity
			if ( isset( $_POST["time"] ) ) {

				$current_date_time = date( "Y-m-d H:i:s" );
				$time              = intval( $_POST["time"] ?? 0 );
				$end_time          = $time;
				if ( $time > 0 ) {
					$end_time = strtotime( $current_date_time . '+' . $time . ' hours' );
				}
				$this->settings::set_option( 'log_until_certain_time', $end_time );

			}
			if ( $_POST['command'] === 'start_loging' ) {
				$this->settings::set_option( 'do_log_transactions', 1 );
			} else {
				$this->settings::set_option( 'do_log_transactions', 0 );
			}
			echo $_POST['command'] === 'start_loging' ? '#stop_loging' : '#start_loging';
		}
		die();
	}

	/**
	 * Clear log DB
	 *
	 */
	public function clear_log() {
		if ( isset( $_POST["action"] ) && isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], self::$nonce_title ) ) {
			$this->logger->truncate_log_table();
			echo $this->logger->generate_log_html();
		}
		die();
	}

	//#endregion

	//#region HTML generation logic based on ainsys_html class.
	/**
	 * Generate entities HTML placeholder.
	 *
	 * @return string
	 */
	public function generate_entities_html() {

		$entities_html = $collapsed = $collapsed_text = $first_active = $inner_fields_header = '';

		$entities_list = $this->settings::get_entities();

		$properties = $this->settings::get_entities_settings();

		foreach ( $properties as $item => $settings ) {
			$checker_property    = $settings['type'] === 'bool' || $item === 'api' ? 'small_property' : '';
			$inner_fields_header .= '<div class="properties_field_title ' . $checker_property . '">' . $settings['nice_name'] . '</div>';
		}

		foreach ( $entities_list as $entiti => $title ) {

			$properties = $this->settings::get_entities_settings( $entiti );

			$entities_html .= '<div class="entities_block">';

			$get_fields_functions = $this->settings::get_entity_fields_handlers();

			$section_fields = array();
			$fields_getter  = $get_fields_functions[ $entiti ];
			if ( is_callable( $fields_getter ) ) {
				$section_fields = $fields_getter();
			} else {
				throw new \Exception( 'No fields getter registered for Entity: ' . $entiti );
			}

			if ( ! empty( $section_fields ) ) {
				$collapsed      = $collapsed ? ' ' : ' active';
				$collapsed_text = $collapsed_text ? 'expand' : 'collapse';
				$entities_html  .= '<div class="entiti_data ' . $entiti . '_data' . $collapsed . '"> ';

				$entities_html .= '<div class="entiti_block_header"><div class="entiti_title">' . $title . '</div>'
				                  . $inner_fields_header . '<a class="button expand_entiti_contaner">'
				                  . $collapsed_text . '</a></div>';
				foreach ( $section_fields as $field_slug => $field_content ) {
					$first_active          = $first_active ? ' ' : ' active';
					$field_name            = empty( $field_content["nice_name"] ) ? $field_slug : $field_content["nice_name"];
					$entiti_saved_settings = array_merge( $field_content, $this->settings::get_saved_entity_settings_from_db( ' WHERE entiti="' . $entiti . '" AND setting_name="' . $field_slug . '"' ) );

					if ( ! empty( $field_content["children"] ) ) {

						$data_fields = 'data-seting_name="' . esc_html( $field_slug ) . '" data-entiti="' . esc_html( $entiti ) . '"';
						foreach ( $properties as $name => $prop_val ) {
							$prop_val_out = $name === 'id' ? $field_slug : $this->get_property( $name, $prop_val, $entiti_saved_settings );
							$data_fields  .= 'data-' . $name . '="' . esc_html( $prop_val_out ) . '" ';
						}
						$entities_html .= '<div id="' . $field_slug . '" class="entities_field multiple_filds ' . $first_active . '" ' .
						                  $data_fields . '><div class="entities_field_header"><i class="fa fa-sort-desc" aria-hidden="true"></i>' . $field_name . '</div>'
						                  . $this->generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) .
						                  '<i class="fa fa-floppy-o"></i><div class="loader_dual_ring"></div></div>';

						foreach ( $field_content["children"] as $inner_field_slug => $inner_field_content ) {
							$field_name            = empty( $inner_field_content["description"] ) ? $inner_field_slug : $inner_field_content["discription"];
							$field_slug_inner      = $field_slug . '_' . $inner_field_slug;
							$entiti_saved_settings = array_merge( $field_content, $this->settings::get_saved_entity_settings_from_db( ' WHERE entiti="' . $entiti . '" AND setting_name="' . $field_slug_inner . '"' ) );

							$data_fields = 'data-seting_name="' . esc_html( $field_slug ) . '" data-entiti="' . esc_html( $entiti ) . '"';
							foreach ( $properties as $name => $prop_val ) {
								$prop_val_out = $name === 'id' ? $field_slug_inner : $this->get_property( $name, $prop_val, $entiti_saved_settings );
								$data_fields  .= 'data-' . $name . '="' . esc_html( $prop_val_out ) . '" ';
							}
							$entities_html .= '<div id="' . $entiti . '_' . $inner_field_slug . '" class="entities_field multiple_filds_children ' . $first_active . '" ' .
							                  $data_fields . '><div class="entities_field_header"><i class="fa fa-angle-right" aria-hidden="true"></i>' . $field_name . '</div>'
							                  . $this->generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) .
							                  '<i class="fa fa-floppy-o"></i><div class="loader_dual_ring"></div></div>';
						}
					} else {
						$data_fields = 'data-seting_name="' . esc_html( $field_slug ) . '" data-entiti="' . esc_html( $entiti ) . '"';
						foreach ( $properties as $name => $prop_val ) {
							$prop_val_out = $this->get_property( $name, $prop_val, $entiti_saved_settings );
							$data_fields  .= 'data-' . $name . '="' . esc_html( $prop_val_out ) . '" ';
						}
						$entities_html .= '<div id="' . $field_slug . '" class="entities_field ' . $first_active . '" ' . $data_fields . '><div class="entities_field_header">' . $field_name . '</div>'
						                  . $this->generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) .
						                  '<i class="fa fa-floppy-o"></i><div class="loader_dual_ring"></div></div>';
					}
				}
				/// close //// div class="entiti_data"
				$entities_html .= '</div>';
			}
			/// close //// div class="entities_block"
			$entities_html .= '</div>';
		}

		return '<div class="entitis_table">
                ' . $entities_html .
		       '</div>';

	}

	/**
	 * Get property from array.
	 *
	 * @return string
	 */
	public function get_property( $name, $prop_val, $entiti_saved_settings ) {
		if ( is_array( $prop_val['default'] ) ) {
			return isset( $entiti_saved_settings[ strtolower( $name ) ] ) ? $entiti_saved_settings[ strtolower( $name ) ] : array_search( '1', $prop_val['default'] );
		}

		return isset( $entiti_saved_settings[ strtolower( $name ) ] ) ?
			$entiti_saved_settings[ strtolower( $name ) ] : $prop_val['default'];
	}

	/**
	 * Generate properties for entity field.
	 *
	 * @return string
	 */
	public function generate_inner_fields( $properties, $entiti_saved_settings, $field_slug ) {

		$inner_fields = '';
		if ( empty( $properties ) ) {
			return '';
		}

		foreach ( $properties as $item => $settings ) {
			$checker_property = $settings['type'] === 'bool' || $item === 'api' ? 'small_property' : '';
			$inner_fields     .= '<div class="properties_field ' . $checker_property . '">';
			$field_value      = $item === 'id' ? $field_slug : $this->get_property( $item, $settings, $entiti_saved_settings );
			switch ( $settings['type'] ) {
				case 'constant':
					$field_value  = $field_value ? $field_value : '<i>' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</i>';
					$inner_fields .= $item === 'api' ? '<div class="entiti_settings_value constant ' . $field_value . '"></div>' : '<div class="entiti_settings_value constant">' . $field_value . '</div>';
					break;
				case 'bool':
					$checked      = (int) $field_value ? 'checked="" value="1"' : ' value="0"';
					$checked_text = (int) $field_value ? __( 'On', AINSYS_CONNECTOR_TEXTDOMAIN ) : __( 'Off', AINSYS_CONNECTOR_TEXTDOMAIN );
					$inner_fields .= '<input type="checkbox"  class="editor_mode entiti_settings_value " id="' . $item . '" ' . $checked . '/> ';
					$inner_fields .= '<div class="entiti_settings_value">' . $checked_text . '</div> ';
					break;
				case 'int':
					$inner_fields .= '<input size="10" type="text"  class="editor_mode entiti_settings_value" id="' . $item . '" value="' . $field_value . '"/> ';
					$field_value  = $field_value ? $field_value : '<i>' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</i>';
					$inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
					break;
				case 'select':
					$inner_fields .= '<select id="' . $item . '" class="editor_mode entiti_settings_value" name="' . $item . '">';
					$state_text   = '';
					foreach ( $settings["default"] as $option => $state ) {
						$selected     = $option === $field_value ? 'selected="selected"' : '';
						$state_text   = $option === $field_value ? $option : $state_text;
						$inner_fields .= '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
					}
					$inner_fields .= '</select>';
					$inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
					break;
				default:
					$field_length = $item === 'description' ? 20 : 8;
					$inner_fields .= '<input size="' . $field_length . '" type="text" class="editor_mode entiti_settings_value" id="' . $item . '" value="' . $field_value . '"/>';
					$field_value  = $field_value ? $field_value : '<i>' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</i>';
					$inner_fields .= '<div class="entiti_settings_value">' . $field_value . '</div>';
			}
			/// close //// div class="properties_field"
			$inner_fields .= '</div>';
		}

		return $inner_fields;
	}


	/**
	 * Generate debug log HTML.
	 *
	 * @return string
	 */
	public function generate_debug_log() {

		if ( ! (int) $this->settings::get_option( 'display_debug' ) ) {
			return;
		}

		$html = '
        <div style="color: grey; padding-top: 20px">
        !!Debug info!!
            <ul>
                <li>' . 'connector #' . $this->settings::get_option( 'connectors' ) . '</li>
                <li>' . 'handshake_url - ' . $this->settings::get_option( 'handshake_url' ) . '</li>
                <li>' . 'webhook_url - ' . $this->settings::get_option( 'webhook_url' ) . '</li>
                <li>' . 'debug_log - ' . $this->settings::get_option( 'debug_log' ) . '</li>
            </ul>
        </div>';

		return $html;
	}
	//#endregion
}
