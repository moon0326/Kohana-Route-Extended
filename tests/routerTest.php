<?php

/**
 * Integration test cases
 */
class Router_Test extends Kohana_Unittest_TestCase
{

	public function setUp()
	{
		$this->route = new Router_Extended;
	}

	public function tearDown()
	{
		$this->route = null;
	}

	public function test_should_throw_an_exception_for_an_imcomplete_controller_and_action()
	{

		$this->setExpectedException('InvalidArgumentException');
		$this->route->get('test', 'incomplete');

	}

	public function test_should_register_a_route_without_a_name()
	{

		$found = false;
		$uri = 'testuri';
		$registered_route = $this->route->get($uri, 'Test@test');

		foreach (Route::all() as $route)
		{

			if ($route === $registered_route)
			{
				$found = true;
			}

		}

		$this->assertTrue($found);

	}

	public function test_should_register_a_route_with_a_complete_controller_and_action()
	{

		$this->route->get('test', array(
			'uses'=> 'Test@test',
			'as' => 'test.test'
		));
		$registered_routte = Route::get('test.test');
		$this->assertTrue($registered_routte instanceof Route);

	}

	public function test_should_throw_an_exception_when_registering_a_filter_twice()
	{
		$this->setExpectedException('InvalidArgumentException');

		$this->route->filter('member-only', function(){});
		$this->route->filter('member-only', function(){});
	}

	public function test_groupping_routes_with_prefix()
	{

		$found = false;
		$registered_route = null;

		$this->route->group(['prefix'=>'admin'], function($route) use(&$registered_route){

			$route->get('test', 'test@test');

		});

		foreach (Route::all() as $route)
		{

			$uri = $this->get_protected_property_value($route, '_uri');

			if ($uri === 'admin/test')
			{
				$found = true;
			}

		}

		$this->assertTrue($found);

	}

	public function test_resultful_should_create_seven_routes_for_a_controller()
	{

		$this->route->restful('resource', 'test');

		$expected_routes = array(
			'resource.index' => array(
				'uri'		 => 'resource',
				'controller' => 'test',
				'action'     => 'index',
				'validated'		 => false

			),
			'resource.create' => array(
				'uri'		 => 'resource/create',
				'controller' => 'test',
				'action'     => 'create',
				'validated'		 => false
			),
			'resource.store' => array(
				'uri'		 => 'resource',
				'controller' => 'test',
				'action'     => 'store',
				'validated'		 => false
			),
			'resource.show' => array(
				'uri'		 => 'resource/<resource>',
				'controller' => 'test',
				'action'     => 'show',
				'validated'		 => false
			),
			'resource.edit' => array(
				'uri'		 => 'resource/<resource>/edit',
				'controller' => 'test',
				'action'     => 'edit',
				'validated'		 => false
			),
			'resource.update' => array(
				'uri'		 => 'resource/<resource>',
				'controller' => 'test',
				'action'     => 'update',
				'validated'		 => false
			),
			'resource.destroy' => array(
				'uri'		 => 'resource/<resource>',
				'controller' => 'test',
				'action'     => 'destroy',
				'validated'		 => false
			)
		);

		foreach (Route::all() as $name=>$route)
		{


			if (array_key_exists($name, $expected_routes))
			{

				$validating_route = $expected_routes[$name];

				$uri = $this->get_protected_property_value($route, '_uri');
				$defatuls = $this->get_protected_property_value($route,'_defaults');

				if (
					$validating_route['uri'] === $uri
					&&
					$validating_route['controller'] === $defatuls['controller']
					&&
					$validating_route['action'] === $defatuls['action']
				)
				{
					$expected_routes[$name]['validated'] = true;
				}

			}

		}

		$found_invalidate_expected_route = false;

		foreach ($expected_routes as $expected_route)
		{
			if ($expected_route['validated'] !== true)
			{
				$found_invalidate_expected_route = true;
				break;
			}
		}

		$this->assertFalse($found_invalidate_expected_route);

	}


	/**
	 * @todo how do I test it!?
	 */
	public function test_groupping_routes_with_before_filter() {}

	/**
	 * @todo how do I test it!?
	 */
	public function test_groupping_routes_with_after_filter() {}




	/**
	 * Helper methods
	 */

	private function get_protected_property_value($object, $_property)
	{
		$reflector = new ReflectionClass($object);
		$property = $reflector->getProperty($_property);
		$property->setAccessible(true);
		return $property->getValue($object);

	}


}