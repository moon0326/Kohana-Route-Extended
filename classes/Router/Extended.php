<?php defined('SYSPATH') or die('No direct script access.');

class Router_Extended
{

	protected $filters = [];

	protected $in_group_closure = false;
	protected $in_group_config = null;

	protected function validate_and_get_configuration($config)
	{
		/**
		 * If $config is not an array, we assume that it is Controller@actionName format
		 * set the $config to 'uses' and put it into an array
		 */
		if ( !is_array($config) )
		{
			$config = array('uses'=>$config);
		}

		/**
		 * If callee didn't provid a name for this route, use a uniqid
		 */
		$name = isset($config['as']) ? $config['as'] : uniqid();

		/**
		 * See if 'uses' is a callable object
		 * if not, seprate a controller and an action by @
		 */
		if (is_callable($config['uses']))
		{
			$controller_and_action = [$config['uses'], null];
		}
		else
		{
			$controller_and_action = explode('@', $config['uses']);
		}

		/**
		 * throw an exception if we can't find a controller and an action pair
		 */
		if (count($controller_and_action) !== 2)
		{
			throw new InvalidArgumentException('Unable to parse a controller and an action from given uses');
		}

		return array(
			'controller' => $controller_and_action[0],
			'action'     => $controller_and_action[1],
			'name'       => $name,
			'regex'      => array_key_exists('regex', $config) ? $config['regex'] : null,
			'before'     => array_key_exists('before', $config) ? array($config['before']) : array(),
			'after'      => array_key_exists('after', $config) ? array($config['after']) : array(),
		);
	}

	public static function methodFilter($route, $params, $request, $params)
	{
		/**
		 * HTML form only supports POST and GET for HTTP request
		 * This is a workaround.
         * @todo do we really need it? consider removing it
		 */
		$unsupported_methods = array('delete', 'put', 'patch');
		$_method = $request->query('_method', null);

		$requested_method = $request->method();

		if (in_array($_method, $unsupported_methods))
		{
			$requested_method = strtoupper($_method);
		}

		if ($requested_method !== $params['method'])
		{
			return false;
		}

		return true;
	}

	protected function register_route($uri, $config, $method)
	{
		$configs = $this->validate_and_get_configuration($config);

		/**
		 * @todo  any better ways?
		 */
		if ($this->in_group_closure && array_key_exists('prefix', $this->in_group_config))
		{
			$uri = $this->in_group_config['prefix'].'/'.$uri;
		}


		$route = Route::set($configs['name'], $uri, $configs['regex'])->defaults(array(
			'controller' => $configs['controller'],
			'action'     => $configs['action']
		))->filter('Router_Extended::methodFilter', ['method'=>$method]);

		/**
		 * If current conext of this object is in a group's closure,
		 * see if we have a before or after filter to apply
		 */
		if ($this->in_group_closure === true)
		{

			$configs['before'] = array_merge($this->in_group_config['before'], $configs['before']);
			$configs['after'] = array_merge($this->in_group_config['after'], $configs['after']);

			$this->apply_route_filters($route, $configs);
		}
		else
		{
			$this->apply_route_filters($route, $configs);
		}


		return $route;
	}

	public function has_filter($name)
	{
		return array_key_exists($name, $this->filters);
	}

	protected function apply_route_filters($route, $config)
	{

		$availableFilters = array('before', 'after');

		foreach ($availableFilters as $availableFilter)
		{

			if (array_key_exists($availableFilter, $config) && $config[$availableFilter] !== null)
			{

				$method_on_route = $availableFilter . '_filter';

				if (!is_array($config[$availableFilter]))
				{
					$config[$availableFilter] = array($config[$availableFilter]);
				}


				foreach ($config[$availableFilter] as $filter_name)
				{


					if ($this->has_filter($filter_name))
					{
						$route->$method_on_route($this->filters[$filter_name]);
					}
					else
					{
						throw new InvalidArgumentException("{$filter_name} filter doesn't exist.");
					}
				}

			}

		}

	}

	public function filter($name, $callback)
	{
		if (array_key_exists($name, $this->filters))
		{
			throw new InvalidArgumentException("{$name} is already registered as a filter.");
		}

		$this->filters[$name] = $callback;
	}

	public function group($config, $callback)
	{

		if (!array_key_exists('before', $config))
		{
			$config['before'] = array();
		}

		if (!is_array($config['before']))
		{
			$config['before'] = array($config['before']);
		}

		if (!array_key_exists('after', $config))
		{
			$config['after'] = array();
		}

		if (!is_array($config['after']))
		{
			$config['after'] = array($config['after']);
		}

		$this->in_group_closure = true;
		$this->in_group_config = $config;

		call_user_func($callback, $this);

		$this->in_group_config = null;
		$this->in_group_closure = false;
	}

	/**
	 * Creates a restful routes
	 * @param  string $resource   name of the resource
	 * @param  string $controller a controller that defines rest actions
	 */
	public function restful($resource, $controller, array $config = array())
	{
		$actions = array(

			'index'   => array(null, 'get'),
			'store'  => array(null, 'post'),
			'create'   => array('/create', 'get'),
			'show'    => array('/<id>', 'get'),
			'edit'    => array('/<id>/edit', 'get'),
			'update'  => array('/<id>', 'put'),
			'destroy' => array('/<id>', 'delete'),

		);

		if (array_key_exists('except', $config) && is_array($config['except']))
		{
			$actions = array_diff_key($actions, array_fill_keys($config['except'], null));
		}

		if (array_key_exists('only', $config) && is_array($config['only']))
		{
			$actions = array_intersect_key($actions, array_fill_keys($config['only'], null));
		}

		if (array_key_exists('namePrefix', $config))
		{
			$namePrefix = $config['namePrefix'];
		}
		else
		{
			$namePrefix = $resource;
		}

		foreach ($actions as $action => $action_config)
		{
			$this->$action_config[1]($resource . $action_config[0], array(
				'as'    => $namePrefix . '.' . $action,
				'uses'  => $controller . '@' . $action,
				'regex' => array_key_exists('regex', $config) ? $config['regex'] : null
			));
		}
	}

	public function get($uri, $config)
	{
		return $this->register_route($uri, $config, HTTP_Request::GET);
	}

	public function post($uri, $config)
	{
		return $this->register_route($uri, $config, HTTP_Request::POST);
	}

	public function delete($uri, $config)
	{
		return $this->register_route($uri, $config, HTTP_Request::DELETE);
	}

	public function put($uri, $config)
	{
		return $this->register_route($uri, $config, HTTP_Request::PUT);
	}

}