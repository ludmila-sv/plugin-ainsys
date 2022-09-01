<?php

namespace Ainsys\Connector\Master;
/**
 * AINSYS webhook listener.
 *
 * @class          AINSYS webhook listener
 * @version        1.0.0
 * @author         AINSYS
 */
class Webhook_Listener implements Hooked {

	use Is_Singleton;

	public function init_hooks() {
		add_action( 'init', array( __CLASS__, 'webhook_listener' ) );
	}

	/**
	 * Listens WebHooks using a specific param 'ainsys_webhook'.
	 *
	 */
	static function webhook_listener() {
		if ( empty( $_SERVER['QUERY_STRING'] ) ) {
			return;
		}

		parse_str( $_SERVER['QUERY_STRING'], $query_vars );

		if ( ! isset( $query_vars['ainsys_webhook'] ) ) {
			return;
		}

		/* by default, we respond with bad request - if it's right action it will be set below. */
		$response_code = 400;
//		if ( self::get_request_token() !== $query_vars['ainsys_webhook'] ) { // was commented out in original code.
//			Core::log( 'Webhook - Token invalid' );
//			wp_send_json( [ 'error' ], 403 );
//			exit;
//		}


		$entityBody = file_get_contents( 'php://input' );
		$request    = json_decode( $entityBody );

		$object_id = $request->entity->id ?? 0;
		$data      = $request->payload ?? [];

		$entityAction = $request->action;
		$entityType   = $request->entity->name;


		switch ( $entityAction ) {
			case 'CREATE':
			case 'DELETE':
			case 'UPDATE':
				$response_code = 200;
				break;
		}

		$response = false;

		$action_handlers = apply_filters( 'ainsys_webhook_action_handlers', array() );

		$handler = $action_handlers[ $entityType ];
		if ( is_callable( $handler ) ) {
			try {
				$response = $handler( $entityAction, $data, $object_id );
			} catch ( \Exception $exception ) {
				$response      = $exception->getMessage();
				$response_code = 500;
			}
		} else {
			$response_code = 404;
		}

		/**
		 * TODO: !!! BEWARE - this will lead to endlessly increased wp_options table  in WP which will lead to loading site forever
		 *      the longer it's used the slower site would become, because it will load all of them upon each request in memory!!!
		 *      RECOMMENDED TO  KEEP THIS COMMENTED OUT - was originally not commented.
		 */
//		update_option( 'last_query_' . time(), $entityBody );

		// wp_send_json `dies` itself, no need to do extra call to die() or exit().
		wp_send_json( [
			'entityType'   => $entityType,
			'request_data' => $data,
			'response'     => $response
		], $response_code );

	}

	/**
	 * Generate hook
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		return site_url( '/?ainsys_webhook=' . self::get_request_token(), 'https' );
	}

	public static function get_request_token() {
		return sha1( $_SERVER["REMOTE_ADDR"] . $_SERVER["SERVER_NAME"] );
	}
}
