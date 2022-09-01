<?php

namespace Ainsys\Connector\Master\WP;


use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Logger;

class Process_Users implements Hooked {

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
		add_action( 'user_register', array( $this, 'process_new_user' ), 10, 2 );
		add_action( 'profile_update', array( $this, 'send_user_details_update_to_ainsys' ), 10, 3 );
	}

	/**
	 * We send a new user details to AINSYS
	 *
	 * @param int $user_id
	 * @param array $userdata
	 *
	 * @return
	 */
	public function process_new_user( $user_id, $userdata ) {
		$request_action = 'CREATE';

		$fields = apply_filters( 'ainsys_new_user_fields', $this->prepare_user_data( $user_id, $userdata ), $userdata );

		$request_data = array(
			'entity'  => [
				'id'   => $user_id,
				'name' => 'user'
			],
			'action'  => $request_action,
			'payload' => $fields
		);

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}

		$this->logger::save_log_information( $user_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return;
	}

	/**
	 * Prepare WP user data. Add ACF fields if we have
	 *
	 * @param int $user_id
	 * @param array $data
	 *
	 * @return array
	 */
	private function prepare_user_data( $user_id, $data ) {
		//$data['id'] = $user_id;
		/// Get ACF fields
		$acf_fields = apply_filters( 'ainsys_prepare_extra_user_data', array(), $user_id );

		return array_merge( $data, $acf_fields );
	}


	/**
	 * We send an updated user details to AINSYS
	 *
	 * @param int $user_id
	 * @param array $old_user_data
	 * @param array $userdata
	 *
	 * @return
	 */
	public function send_user_details_update_to_ainsys( $user_id, $old_user_data, $userdata ) {
		$request_action = 'UPDATE';

		$fields = apply_filters( 'ainsys_user_details_update_fields', $this->prepare_user_data( $user_id, $userdata ), $userdata );

		$request_data = array(
			'entity'  => [
				'id'   => $user_id,
				'name' => 'user'
			],
			'action'  => $request_action,
			'payload' => $fields
		);

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
		}

		$this->logger->save_log_information( $user_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return;
	}


}