<?php
/**
 * Dependency injection container for whole family of plugins.
 * @package ainsys
 */

namespace Ainsys\Connector\Master;


class DI_Container {

	use Is_Singleton;

	public $registered_services = array();

	/**
	 * Used to store resolved class names to catch if there's a circular dependency.
	 *
	 * @var array
	 */
	private $currently_resolved_class_names = array();


	/**
	 * Use if you already have some instance of service, so you can store it to be reused by other services/components.
	 *
	 * @param string $class_name
	 * @param object $instance
	 *
	 * @return self
	 */
	public function register_singleton_instance( $class_name, $instance ) {
		$this->registered_services[ $class_name ] = $instance;

		return $this;
	}

	/**
	 * @throws \ReflectionException
	 * @throws \Exception
	 */
	public function resolve( $class_name ) {

		if ( $class_name === __CLASS__ ) {
			return $this;
		}

		if ( ( $this->registered_services[ $class_name ] ?? null ) instanceof $class_name ) {
			return $this->registered_services[ $class_name ];
		}

		if ( ! empty( $this->currently_resolved_class_names ) && in_array( $class_name, $this->currently_resolved_class_names ) ) {
			throw new \Exception( 'Circular Dependency found when resolving class: ' . $class_name );
		}

		$this->currently_resolved_class_names[] = $class_name;

		$reflection  = new \ReflectionClass( $class_name );
		$constructor = $reflection->getConstructor();

		$parameters_to_inject = array();
		if ( $constructor instanceof \ReflectionMethod ) {
			$parameters_to_inject = $constructor->getParameters();
		}

		$param_objects = array();

		foreach ( $parameters_to_inject as $parameter ) {
			$param_class_name = $parameter->getType()->getName();
			$param_objects[]  = $this->resolve( $param_class_name );
		}

		$result_instance = new $class_name( ...$param_objects );

		$this->registered_services[ $class_name ] = $result_instance;
		$this->currently_resolved_class_names     = array(); // restore original clean state.

		return $result_instance;
	}

}