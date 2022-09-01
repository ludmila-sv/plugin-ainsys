<?php

namespace Ainsys\Connector\Master\Webhooks;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Handler;

class Handle_Comment implements Hooked, Webhook_Handler {

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
		$handlers['comment'] = array( $this, 'handler' );

		return $handlers;
	}


	public function handler( $action, $data, $object_id = 0 ) {

		// TODO set proper actions as in initial plugin this code was never executed.
		switch ( $action ) {
			case 'add':

			case 'update':

			case 'delete':

		}

		return 'Action not registered, Please implement actions for Comments.';
	}

	private function update( $data ) {
		// TODO - this function is migrated AS IS from old code - just refactored error messaging handling.
		$data['comment_ID'] = $data['comment_post_ID'];
		$result             = wp_update_comment( $data );

		return is_wp_error( $result ) ? $result->get_error_message() : $result;
	}

}