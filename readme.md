## Work in progress
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

### Notes on DELETE and PUT

HTML form doesn't support DELETE/PUT request. You have a couple of options.

1. Use an AJAX call instead.
2. create a hidden input element as **\_METHOD** and assign either DELETE/PUT for its value. The router class will check the value of **_METHOD** if it exists.

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

#####Using a before filter for a group of routes
```php
Router::group(['before'=>'member-only'], function()
{
	Router::get('member/info', 'Member@info');
})
```

#####Using multiple filters for a group of routes

```php
Router::group(['before'=>['member-only','paid-member-only']], function()
{
	Router::get('premium-content', 'Premium@show');
});
```

If one of the filters return **false**, the request stops immediately and 404 not found page will be displayed.


#####Prefixing a group of routes

The following route matches admin/dashboard, and admin/report.
```php
Router::group(['prefix'=>'admin'], function()
{
	Router::get('dashboard', 'Admin@dashboard');
    Router::get('report', 'Admin@Report');
});
```

### Restful Controller

```php
Router::restful('user', 'User');
```
Creates the following routes for User controller.

| Verb   | URI    | Controller Method | Route Name |
|--------|--------|--------|------------|
| GET    | /user  | action_index  | user.index            |
| GET	| /user/create | action_create | user.create |
| POST | /user | action_store | user.store |
| GET 	| /user/{param} | action_show | user.show |
| GET	| /user/{param}/edit	| action_edit | user.edit |
| PUT/PATCH | /user/{param}	|	action_update	|	user.update |
| DELETE	| /user/{param}	|	action_destroy	|	user.destroy|







