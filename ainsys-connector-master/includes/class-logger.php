<?php

namespace Ainsys\Connector\Master;

use Ainsys\Connector\Master\Settings\Settings;

class Logger implements Hooked {

	public static $do_log_transactions = false;
	private static $log_table_name = 'ainsys_log';

	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings            = $settings;
		self::$do_log_transactions = $this->settings::get_option( 'do_log_transactions' );
	}

	public function init_hooks() {

	}

	/**
	 * Save each update transactions to log
	 *
	 * @param int $object_id
	 * @param string $request_action
	 * @param string $request_data
	 * @param string $serrver_responce
	 * @param int $incoming_call
	 *
	 * @return string
	 */
	public static function save_log_information( $object_id, $request_action, $request_data, $serrver_responce = '', $incoming_call = 0 ) {
		global $wpdb;

		if ( ! self::$do_log_transactions ) {
			return false;
		}

		$result = $wpdb->insert( $wpdb->prefix . self::$log_table_name,
			array(
				'object_id'        => $object_id,
				'request_action'   => $request_action,
				'request_data'     => $request_data,
				'serrver_responce' => $serrver_responce,
				'incoming_call'    => $incoming_call
			)
		);

		return $result;
	}

	/**
	 * Generate server data transactions HTML.
	 *
	 * @return string
	 */
	public static function generate_log_html( $where = '' ) {

		global $wpdb;

		$log_html        = '<div id="connection_log"><table class="form-table">';
		$log_html_body   = '';
		$log_html_header = '';
		$query           = "SELECT * 
        FROM " . $wpdb->prefix . self::$log_table_name . $where;
		$output          = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $output ) ) {
			return '<div class="empty_tab"><h3>' . __( 'No transactions to display', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</h3></div>';
		}

		foreach ( $output as $item ) {
			$log_html_body .= '<tr valign="top">';
			$header_full   = empty( $log_html_header ) ? true : false;
			foreach ( $item as $name => $value ) {
				$log_html_header .= $header_full ? '<th>' . strtoupper( str_replace( '_', ' ', $name ) ) . '</th>' : '';
				$log_html_body   .= '<td class="' . $name . '">';
				if ( $name === 'incoming_call' ) {
					$value = (int) $value === 0 ? 'No' : 'Yes';
				}
				if ( $name === 'request_data' ) {
					$value = maybe_unserialize( $value );
					if ( empty( $value["request_data"] ) ) {
						$log_html_body .= $value ? '<div class="gray_header">' . __( 'empty', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</div>' : $value;
						continue;
					}
					if ( is_array( $value ) ) {
						if ( count( $value["request_data"] ) > 2 ) {
							$log_html_body .= '<div class="request_data_contaner"> <a class="button expand_data_contaner">more</a>';
						}
						foreach ( $value["request_data"] as $title => $param ) {
							if ( $title === "products" && ! empty( $param ) ) {
								foreach ( $param as $prod_id => $product ) {
									$log_html_body .= '</br> <strong>Prod# ' . $prod_id . '</strong>';
									foreach ( $product as $param_title => $poduct_param ) {
										if ( is_array( $poduct_param ) ) {
											continue;
										}
										$log_html_body .= '<div><span class="gray_header">' . $param_title . ' : </span>' . maybe_serialize( $poduct_param ) . '</div>';
									}
								}
							} else {
								$log_html_body .= '<div><span class="gray_header">' . $title . ' : </span>' . maybe_serialize( $param ) . '</div>';
							}
						}
						$log_html_body .= '</div>';
					}
				} else {
					$log_html_body .= $value;
				}
				$log_html_body .= '</td>';
			}
			$log_html_body .= '</tr>';
		}
		$log_html .= '<thead><tr>' . $log_html_header . '</tr></thead>' . $log_html_body . '</table> </div>';

		return $log_html;
	}

	/**
	 * Truncate log table.
	 *
	 */
	public function truncate_log_table() {
		global $wpdb;
		$sql = "TRUNCATE TABLE " . $wpdb->prefix . self::$log_table_name;
		$wpdb->query( $sql );

	}

	/**
	 * Install tables
	 */
	public function activate() {
		ob_start();
		global $wpdb;

		$wpdb->hide_errors();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $this->get_schema() );

		ob_get_clean();
	}

	public function deactivate() {
		if ( intval( $this->settings::get_option( 'full_uninstall' ) ) ) {
			$this->uninstall();
		}
	}

	public function uninstall() {
		global $wpdb;
		$wpdb->query( sprintf( "DROP TABLE IF EXISTS %s",
			$wpdb->prefix . self::$log_table_name ) );
	}

	/**
	 * Get Table schema.
	 *
	 * @return string
	 */
	private function get_schema() {
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

		$table_log = $wpdb->prefix . self::$log_table_name;

		$tables = "CREATE TABLE {$table_log} (
                `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `object_id` bigint NOT NULL,
                `request_action` varchar(100) NOT NULL,
                `request_data` text DEFAULT NULL,
                `serrver_responce` text DEFAULT NULL,
                `incoming_call` smallint NOT NULL,
                `creation_date` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (log_id),
                KEY object_id (object_id)
            ) $collate;";

		return $tables;
	}
}
