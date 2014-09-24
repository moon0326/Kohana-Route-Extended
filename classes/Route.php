<?php defined('SYSPATH') or die('No direct script access.');

class Route extends Kohana_Route
{

	protected $_before_filters = [];
	protected $_after_filters = [];


	/**
	 * Filters to be run before route parameters are returned:
	 *
	 *     $route->filter(
	 *         function(Route $route, $params, Request $request)
	 *         {
	 *             if ($request->method() !== HTTP_Request::POST)
	 *             {
	 *                 return FALSE; // This route only matches POST requests
	 *             }
	 *             if ($params AND $params['controller'] === 'welcome')
	 *             {
	 *                 $params['controller'] = 'home';
	 *             }
	 *
	 *             return $params;
	 *         }
	 *     );
	 *
	 * To prevent a route from matching, return `FALSE`. To replace the route
	 * parameters, return an array.
	 *
	 * [!!] Default parameters are added before filters are called!
	 *
	 * @throws  Kohana_Exception
	 * @param   array   $callback   callback string, array, or closure
	 * @return  $this
	 */
	public function filter($callback, $params = array())
	{
		if ( ! is_callable($callback))
		{
			throw new Kohana_Exception('Invalid Route::callback specified');
		}

		$this->_filters[] = [
			'callback' => $callback,
			'params'   => $params
		];

		return $this;
	}

	/**
	 * Tests if the route matches a given URI. A successful match will return
	 * all of the routed parameters as an array. A failed match will return
	 * boolean FALSE.
	 *
	 *     // Params: controller = users, action = edit, id = 10
	 *     $params = $route->matches('users/edit/10');
	 *
	 * This method should almost always be used within an if/else block:
	 *
	 *     if ($params = $route->matches($uri))
	 *     {
	 *         // Parse the parameters
	 *     }
	 *
	 * @param   string  $uri    URI to match
	 * @return  array   on success
	 * @return  FALSE   on failure
	 */
	public function matches(Request $request)
	{
		// Get the URI from the Request
		$uri = trim($request->uri(), '/');


		if ( ! preg_match($this->_route_regex, $uri, $matches))
			return FALSE;


		$params = array();
		foreach ($matches as $key => $value)
		{
			if (is_int($key))
			{
				// Skip all unnamed keys
				continue;
			}

			// Set the value for all matched keys
			$params[$key] = $value;
		}

		foreach ($this->_defaults as $key => $value)
		{
			if ( ! isset($params[$key]) OR $params[$key] === '')
			{
				// Set default values for any key that was not matched
				$params[$key] = $value;
			}
		}


		if ( ! empty($params['controller']) && !is_callable($params['controller']))
		{
			// PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
			$params['controller'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['controller'])));
		}

		if ( ! empty($params['directory']))
		{
			// PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
			$params['directory'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['directory'])));
		}


		if ($this->_filters)
		{
			foreach ($this->_filters as $callback)
			{

				// Execute the filter giving it the route, params, and request
				$return = call_user_func($callback['callback'], $this, $params, $request, $callback['params']);

				if ($return === FALSE)
				{
					// Filter has aborted the match
					return FALSE;
				}
				elseif (is_array($return))
				{
					// Filter has modified the parameters
					$params = $return;
				}
			}
		}

		return $params;
	}

	public function execute_before_filter($request, $response)
	{
		foreach ($this->_before_filters as $filter)
		{
			$result = call_user_func_array($filter, array($request, $response));

			if ($result === false)
			{
				return $result;
			}
		}
	}

	public function execute_after_filter($request, $response)
	{
		foreach ($this->_after_filters as $filter)
		{
			$result = call_user_func_array($filter, array($request, $response));

			if ($result)
			{
				return $result;
			}

		}
	}

	public function before_filter($callback)
	{
		$this->_before_filters[] = $callback;
		return $this;
	}

	public function after_filter($callback)
	{
		$this->_after_filters[] = $callback;
		return $this;
	}


}