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
			'regex'      => array_key_exists('regex', $config) ? $config['regex'] : null
		);

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
		))->filter(function($route, $params, $request) use ($method)
		{

			/**
			 * HTML form only supports POST and GET for HTTP request
			 * This is a workaround.
             * @todo do we really need it? consider removing it
			 */
			$unsupported_methods = array('DELETE', 'PUT', 'PATCH');

			if (
				$request->method() === HTTP_Request::POST
				&&
				in_array($method, $unsupported_methods)
				&&
				in_array($request->query('_METHOD'), $unsupported_methods)
			)
			{
				return true;
			}


			if ($request->method() !== $method)
			{
				return false;
			}

			return true;

		});

		/**
		 * If current conext of this object is in a group's closure,
		 * see if we have a before or after filter to apply
		 */
		if ($this->in_group_closure === true)
		{
			$this->apply_group_filters($route);
		}

		return $route;

	}

	public function has_filter($name)
	{
		return array_key_exists($name, $this->filters);
	}

	protected function apply_group_filters(Route $route)
	{

		$attributes = array(
			'before' => 'before_filter',
			'after'  => 'after_filter'
		);

		foreach ($attributes as $attribute=>$route_method)
		{

			if (array_key_exists($attribute, $this->in_group_config))
			{

				if (!is_array($this->in_group_config[$attribute]))
				{
					$this->in_group_config[$attribute] = array($this->in_group_config[$attribute]);
				}

				foreach ($this->in_group_config[$attribute] as $filter_name)
				{
					if (!$this->has_filter($filter_name))
					{
						throw new InvalidArgumentException("{$filter_name} is not a registered filter");
					}

					$route->$route_method($this->filters[$filter_name]);
				}

			}

		}

	}

	public function filter($name, Closure $callback)
	{

		if (array_key_exists($name, $this->filters))
		{
			throw new InvalidArgumentException("{$name} is already registered as a filter.");
		}

		$this->filters[$name] = $callback;

	}

	public function group($config, Closure $callback)
	{
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
	public function restful($resource, $controller)
	{

		$this->get($resource, array(
			'as'   => "{$resource}.index",
			'uses' => "{$controller}@index"
		));

		$this->get($resource.'/create', array(
			'as'   => "{$resource}.create",
			'uses' => "{$controller}@create"
		));

		$this->post($resource, array(
			'as'   => "{$resource}.store",
			'uses' => "{$controller}@store"
		));

		$this->get($resource.'/<resource>', array(
			'as'   => "{$resource}.show",
			'uses' => "{$controller}@show"
		));

		$this->get($resource.'/<resource>/edit', array(
			'as'   => "{$resource}.edit",
			'uses' => "{$controller}@edit"
		));

		$this->put($resource.'/<resource>', array(
			'as'   => "{$resource}.update",
			'uses' => "{$controller}@update"
		));

		$this->delete($resource.'/<resource>', array(
			'as'   => "{$resource}.destroy",
			'uses' => "{$controller}@destroy"
		));

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