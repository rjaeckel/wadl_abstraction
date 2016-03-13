<?php
/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 12.03.16
 * Time: 20:20
 */

trait callByRegex
{
	/**
	 * @var callable[]
	 */
	private $handlers = [ ];

	/**
	 * @param string   $regex
	 * @param string   $method
	 *
	 * @return $this;
	 */
	protected function addHandler ( string $regex , string $method ) {
		$this->handlers[ $regex ] = $method;
		return $this;
	}

	/**
	 * @param string $name
	 * @param array  $args
	 */
	protected function handleRegex ( string $name , array $args ) {
		foreach ( $this->handlers as $regEx => $cb ) {
			if (
				preg_match ( "/$regEx/" , $name , $matches ) &&
				#fwrite(STDOUT,"handle: $name $regEx \n") &&
				$name == array_shift ( $matches ) &&
				#($cb=[$this,$cb]) &&
				( $res = $this->$cb( ...$matches , ...$args ) )
			) return $res;
		}
	}
}

