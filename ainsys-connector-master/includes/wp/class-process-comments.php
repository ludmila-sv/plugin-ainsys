<?php

namespace Ainsys\Connector\Master\WP;


use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;

class Process_Comments implements Hooked {

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @var Logger
	 */
	private $logger;

	public function __construct( Core $core, Logger $logger ) {
		$this->core   = $core;
		$this->logger = $logger;
	}


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'comment_post', array( $this, 'send_new_comment_to_ainsys' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'send_update_comment_to_ainsys' ), 10, 2 );
	}

	/**
	 * We send an updated WP comment details to AINSYS
	 *
	 * @param int $comment_id
	 * @param object $data
	 *
	 * @return
	 */
	public function send_new_comment_to_ainsys( $comment_id, $comment_approved, $data ) {
		$request_action = 'CREATE';

		$fields = apply_filters( 'ainsys_new_comment_fields', $this->prepare_comment_data( $comment_id, $data ), $data );

		$request_data = array(
			'object_id'      => $comment_id,
			'request_action' => $request_action,
			'request_data'   => $fields
		);

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}

		$this->logger->save_log_information( $comment_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return;
	}

	/**
	 * Prepare WP comment data. Add ACF fields if we have
	 *
	 * @param int $comment_id
	 * @param array $data
	 *
	 * @return array
	 */
	private function prepare_comment_data( $comment_id, $data ) {
		$data['id'] = $comment_id;
		/// Get ACF fields
		$acf_fields = apply_filters( 'ainsys_prepare_extra_comment_data', array(), $comment_id );

		return array_merge( $data, $acf_fields );
	}

	/**
	 * We send an updated WP comment details to AINSYS
	 *
	 * @param int $comment_id
	 * @param array $data
	 *
	 * @return
	 */
	public function send_update_comment_to_ainsys( $comment_id, $data ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_update_comment_fields', $this->prepare_comment_data( $comment_id, $data ), $data );

		$request_data = array(
			'object_id'      => $comment_id,
			'request_action' => $request_action,
			'request_data'   => $fields
		);

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}
		$this->logger->save_log_information( $comment_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return;
	}

}