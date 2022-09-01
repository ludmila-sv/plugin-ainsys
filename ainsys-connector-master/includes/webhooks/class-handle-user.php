<?php

namespace Ainsys\Connector\Master\Webhooks;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Handler;

class Handle_User implements Hooked, Webhook_Handler {

	public function __construct() {
	}

	/**
	 * Initializes WordPress hooks for component.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'ainsys_webhook_action_handlers', array( $this, 'register_webhook_handler' ), 10, 1 );
	}

	public function register_webhook_handler( $handlers = array() ) {
		$handlers['user'] = array( $this, 'handler' );

		return $handlers;
	}


	public function handler( $action, $data, $object_id = 0 ) {
		$data              = (array) $data;
		$data['user_pass'] = $data['user_pass'] ?? wp_generate_password( 15, true, true );

		switch ( $action ) {
			case 'add':
				$user_id = wp_insert_user( $data );
				if ( ! is_wp_error( $user_id ) ) {
					return $user_id;
				} else {
					return $user_id->get_error_message();
				}
			case 'update':
				return $this->update_user( $data, $object_id );
			case 'delete':
				return wp_delete_user( $object_id );
		}

		return 'Action not registered';
	}

	private function update_user( $data, $object_id = 0 ) {
		$result = wp_update_user( $data );

		return is_wp_error( $result ) ? $result->get_error_message() : $result;
	}

}