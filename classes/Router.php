<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Just a proxy for Router_Extended class
 * makes it possible to call methods statically
 */
class Router
{
	private static $router;

	public static function implementation($router)
	{
		static::$router = $router;
	}

	public static function __callStatic($method, $arguments)
	{
		return call_user_func_array(array(static::$router, $method), $arguments);
	}

}

Router::implementation(new Router_Extended());