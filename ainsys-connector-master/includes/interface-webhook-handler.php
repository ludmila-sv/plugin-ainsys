<?php

namespace Ainsys\Connector\Master;

interface Webhook_Handler {

	public function register_webhook_handler( array $handlers );

	/**
	 * @param string $action
	 * @param array $data
	 * @param int $object_id
	 *
	 * @return int|string|bool
	 */
	public function handler( string $action, array $data, int $object_id );

}