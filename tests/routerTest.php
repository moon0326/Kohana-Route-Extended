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

	public function test_should_allow_naming_a_clousre_route()
	{
		$this->route->get('test', array('as'=>'closure-route', 'uses'=>function()
		{
			return 'hello';
		}));

		$registered_route = Route::get('closure-route');

		$this->assertTrue($registered_route instanceof Route);
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

		$found_invalidate_expected_route = false;

		foreach ($expected_routes as $name=>$expected_route)
		{
			$route = Route::get($name);
			if (!$route)
			{
				$found_invalidate_expected_route = true;
				break;
			}
		}

		$this->assertFalse($found_invalidate_expected_route);
	}

	public function test_groupping_routes_with_prefix()
	{
		$registered_route = null;

		$this->route->group(['prefix'=>'admin'], function($route) use(&$registered_route){

			$registered_route = $route->get('test', array('as'=>'test', 'uses'=>'test@test'));

		});

		$this->assertTrue($registered_route instanceof Route);
	}

	public function test_groupping_routes_with_before_filter()
	{
		$this->route->filter('member-only', function($router)
		{
			return false;
		});

		$registered_route = null;

		$this->route->group(array('before'=>'member-only'), function($router) use (&$registered_route)
		{
			$registered_route = $router->get('test', function()
			{
				return 'you should not receive this';
			});

		});

	 	$request = Request::factory('test', array(), false, array($registered_route))->execute();
		$this->assertEquals(404, $request->status());
	}

	public function test_groupping_routes_with_after_filter()
	{
		$this->route->filter('append-test-at-the-end', function($request, $response)
		{
			$response->body($response->body().'-test');
		});


		$registered_route = null;

		$this->route->group(array('after'=>'append-test-at-the-end'), function($router) use (&$registered_route)
		{
			$registered_route = $router->get('test', function()
			{
				return 'integration';
			});

		});


	 	$request = Request::factory('test', array(), false, array($registered_route))->execute()->body();

	 	$this->assertEquals('integration-test', $request);
	}

	public function test_should_allow_using_a_filter_for_a_route_in_a_group()
	{
		$this->route->filter('append-test2-at-the-end', function($request, $response)
		{
			$response->body($response->body().'test2');
		});

		$this->route->filter('append-test3-at-the-end', function($request, $response)
		{
			$response->body($response->body().'-test3');
		});


		$registered_route = null;

		$this->route->group(array('after'=>'append-test2-at-the-end'), function($router) use (&$registered_route)
		{
			$registered_route = $router->get('test', array('after'=>'append-test3-at-the-end', 'uses'=>function(){

				return 'test-';

			}));

		});

	 	$request = Request::factory('test', array(), false, array($registered_route))->execute()->body();
	 	$this->assertEquals('test-test2-test3', $request);
	}

	public function test_should_not_proceed_if_a_before_filter_returns_false()
	{
		$this->route->filter('you-shall-not-pass-me', function(){
			return false;
		});

		$registered_route = null;

		$this->route->group(array('before'=>'you-shall-not-pass-me'), function($router) use (&$registered_route)
		{
			$registered_route = $router->get('test', function()
			{
				return 'hello';
			});
		});

		$request = Request::factory('test', array(), false, array($registered_route))->execute();

		$this->assertEquals(404, $request->status());
	}

	public function test_should_throw_an_exception_when_using_unregistered_filter()
	{
		$this->setExpectedException('InvalidArgumentException');

		$registered_route = null;

		$this->route->group(array('before'=>'i am not a registered filter'), function($router) use (&$registered_route)
		{
			$registered_route = $router->get('test', function()
			{
				return 'you should not see this';
			});
		});
	}

	public function test_should_allow_multiple_before_filters()
	{
		$this->route->filter('my name', function($request, $response)
		{
			$response->body($response->body().'my name ');
		});

		$this->route->filter('is', function($request, $response)
		{
			$response->body($response->body().'is ');
		});

		$registered_route = null;

		$this->route->group(array('before'=>array('my name', 'is')), function($router) use (&$registered_route)
		{

			$registered_route = $router->get('test', function($request, $response)
			{
				return $response->body($response->body().'test');
			});

		});

		$request_result = Request::factory('test', array(), false, array($registered_route))->execute()->body();

		$this->assertEquals($request_result, 'my name is test');
	}

	public function test_should_allow_multiple_after_filters()
	{
		$this->route->filter('my name', function($request, $response)
		{
			$response->body($response->body().' my name');
		});

		$this->route->filter('is', function($request, $response)
		{
			$response->body($response->body().' is');
		});

		$registered_route = null;

		$this->route->group(array('after'=>array('is', 'my name')), function($router) use (&$registered_route)
		{

			$registered_route = $router->get('test', function($request, $response)
			{
				return 'test';
			});

		});

		$request_result = Request::factory('test', array(), false, array($registered_route))->execute()->body();

		$this->assertEquals($request_result, 'test is my name');
	}

	public function test_except_option_for_restful_controller()
	{
		$this->setExpectedException('Kohana_Exception');

		$this->route->restful('user', 'User', array(
			'except' => array('index')
		));

		Route::get('user.index');
	}

	public function test_only_option_for_restful_controller()
	{
		$this->clear_routes();

		$this->route->restful('user', 'User', array(
			'only' => array('index')
		));

		$route_names = array(
			'user.index',
			'user.create',
			'user.store',
			'user.show',
			'user.edit',
			'user.update',
			'user.destroy'
		);

		$found = 0;

		foreach (Route::all() as $name=>$route)
		{
			if (in_array($name, $route_names))
			{
				$found += 1;
			}
		}

		$this->assertEquals(1, $found);
	}

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

	private function clear_routes()
	{
		$reflector = new ReflectionProperty('Route', '_routes');
		$reflector->setAccessible(true);
		$reflector->setValue(null, array());
	}
}