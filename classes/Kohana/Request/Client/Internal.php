<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Request_Client_Internal extends Request_Client
{

	protected function get_not_found_exception(Request $request)
	{
		return HTTP_Exception::factory(404,
			'The requested URL :uri was not found on this server.',
			array(':uri' => $request->uri())
		)->request($request);
	}

	public function execute_request(Request $request, Response $response)
	{
		// Create the class prefix
		$prefix = 'Controller_';
		// Directory
		$directory = $request->directory();

		// Controller
		$controller = $request->controller();

		if ($directory)
		{
			// Add the directory name to the class prefix
			$prefix .= str_replace(array('\\', '/'), '_', trim($directory, '/')).'_';
		}

		if (Kohana::$profiling)
		{
			// Set the benchmark name
			$benchmark = '"'.$request->uri().'"';

			if ($request !== Request::$initial AND Request::$current)
			{
				// Add the parent request uri
				$benchmark .= ' « "'.Request::$current->uri().'"';
			}

			// Start benchmarking
			$benchmark = Profiler::start('Requests', $benchmark);
		}

		// Store the currently active request
		$previous = Request::$current;

		// Change the current request to this request
		Request::$current = $request;

		// Is this the initial request
		$initial_request = ($request === Request::$initial);

		try
		{

			$route = $request->route();

			// execute befoter filters
			$before_filter_result = $route->execute_before_filter($request, $response);

			if ($before_filter_result === false)
			{
				throw $this->get_not_found_exception($request);
			}

			if (is_callable($controller))
			{
				$response->body(call_user_func_array($controller, array($request, $response)));
			}
			else
			{

				if ( ! class_exists($prefix.$controller))
				{
					throw $this->get_not_found_exception($request);
				}

				// Load the controller using reflection
				$class = new ReflectionClass($prefix.$controller);

				if ($class->isAbstract())
				{
					throw new Kohana_Exception(
						'Cannot create instances of abstract :controller',
						array(':controller' => $prefix.$controller)
					);
				}

				// Create a new instance of the controller
				$controller = $class->newInstance($request, $response);

				// Run the controller's execute() method
				$response = $class->getMethod('execute')->invoke($controller);

				if ( ! $response instanceof Response)
				{
					// Controller failed to return a Response.
					throw new Kohana_Exception('Controller failed to return a Response');
				}

			}

			$route->execute_after_filter($request, $response);

		}
		catch (HTTP_Exception $e)
		{
			// Get the response via the Exception
			$response = $e->get_response();
		}
		catch (Exception $e)
		{
			// Generate an appropriate Response object
			$response = Kohana_Exception::_handler($e);
		}

		// Restore the previous request
		Request::$current = $previous;

		if (isset($benchmark))
		{
			// Stop the benchmark
			Profiler::stop($benchmark);
		}

		// Return the response
		return $response;
	}

}