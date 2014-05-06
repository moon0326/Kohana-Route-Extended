### Basic Usage

**GET Route**

```php
Router::get('test' 'Test@test');
```

**POST Route**

```php
Router::post('test', 'Test@test');
```

**DELETE Route**

```php
Router::delete('test', 'Test@test');
```

**PUT Route**

```php
Router::put('test', 'Test@test');
```


### Router Filters

A filter can be applied before/after a route.

**Registering a filter**
```php
Router::filter('member-only', function($request, $response)
{
	if (!Auth::instance()->get_user()) 
    {
    	HTTP::redirect("/", 302);
    }
});
```

### Groupped Routes

**Using a before filter for a group of routes**
```php
Router::group(['before'=>'member-only'], function()
{
	Router::get('member/info', 'Member@info');
})
```

**Prefixing a group of routes**

The following route matches admin/dashboard, not dashboard.
```php
Router::group(['prefix'=>'admin'], function()
{
	Router::get('dashboard', 'Admin@dashboard');
});
```