<?php defined('SYSPATH') or die('No direct script access.');

class Route extends Kohana_Route
{

	protected $_beforeFilters = [];
	protected $_afterFilters = [];

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
				$return = call_user_func($callback, $this, $params, $request);

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
		foreach ($this->_beforeFilters as $filter)
		{
			$result = call_user_func_array($filter, [$request, $response]);

			if ($result)
			{
				return $result;
			}
		}
	}

	public function execute_after_filter($request, $response)
	{
		foreach ($this->_afterFilters as $filter)
		{
			$result = call_user_func_array($filter, [$request, $response]);

			if ($result)
			{
				return $result;
			}

		}
	}

	public function before_filter(Closure $callback)
	{
		$this->_beforeFilters[] = $callback;
		return $this;
	}

	public function after_filter(Closure $callback)
	{
		$this->after_filter[] = $callback;
		return $this;
	}


}