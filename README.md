# tobscure/json-api-server

[![Build Status](https://img.shields.io/travis/com/tobscure/json-api-server.svg?style=flat)](https://travis-ci.com/tobscure/json-api-server)
[![Pre Release](https://img.shields.io/packagist/vpre/tobscure/json-api-server.svg?style=flat)](https://github.com/tobscure/json-api-server/releases)
[![License](https://img.shields.io/packagist/l/tobscure/json-api-server.svg?style=flat)](https://packagist.org/packages/tobscure/json-api-server)

**A fully automated framework-agnostic [JSON:API](http://jsonapi.org) server implementation in PHP.**  
Define your schema, plug in your models, and we'll take care of the rest. 🍻

```bash
composer require tobscure/json-api-server
```

```php
use Tobscure\JsonApiServer\Api;
use Tobscure\JsonApiServer\Adapter\EloquentAdapter;
use Tobscure\JsonApiServer\Schema\Builder;

$api = new Api('http://example.com/api');

$api->resource('articles', new EloquentAdapter(new Article), function (Builder $schema) {
    $schema->attribute('title');
    $schema->hasOne('author', 'people');
    $schema->hasMany('comments');
});

$api->resource('people', new EloquentAdapter(new User), function (Builder $schema) {
    $schema->attribute('firstName');
    $schema->attribute('lastName');
    $schema->attribute('twitter');
});

$api->resource('comments', new EloquentAdapter(new Comment), function (Builder $schema) {
    $schema->attribute('body');
    $schema->hasOne('author', 'people');
});

/** @var Psr\Http\Message\ServerRequestInterface $request */
/** @var Psr\Http\Message\Response $response */
try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

Assuming you have a few [Eloquent](https://laravel.com/docs/5.7/eloquent) models set up, the above code will serve a **complete JSON:API that conforms to the [spec](https://jsonapi.org/format/)**, including support for:

- **Showing** individual resources (`GET /api/articles/1`)
- **Listing** resource collections (`GET /api/articles`)
- **Sorting**, **filtering**, **pagination**, and **sparse fieldsets**
- **Compound documents** with inclusion of related resources
- **Creating** resources (`POST /api/articles`)
- **Updating** resources (`PATCH /api/articles/1`)
- **Deleting** resources (`DELETE /api/articles/1`)
- **Error handling**

The schema definition is extremely powerful and lets you easily apply [permissions](#visibility), [getters](#getters), [setters](#setters-savers), [validation](#validation), and custom [filtering](#filtering) and [sorting](#sorting) logic to build a fully functional API in minutes.

### Handling Requests

```php
use Tobscure\JsonApiServer\Api;

$api = new Api('http://example.com/api');

try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

`Tobscure\JsonApiServer\Api` is a [PSR-15 Request Handler](https://www.php-fig.org/psr/psr-15/). Instantiate it with your API's base URL. Convert your framework's request object into a [PSR-7 Request](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface) implementation, then let the `Api` handler take it from there. Catch any exceptions and give them back to `Api` if you want a JSON:API error response.

### Defining Resources

Define your API's resources using the `resource` method. The first argument is the [resource type](https://jsonapi.org/format/#document-resource-object-identification). The second is an implementation of `Tobscure\JsonApiServer\Adapter\AdapterInterface` which will allow the handler to interact with your models. The third is a closure in which you'll build the schema for your resource.

```php
use Tobscure\JsonApiServer\Schema\Builder;

$api->resource('comments', $adapter, function (Builder $schema) {
    // define your schema
});
```

We provide an `EloquentAdapter` to hook your resources up with Laravel [Eloquent](https://laravel.com/docs/5.7/eloquent) models. Set it up with an instance of the model that your resource represents. You can [implement your own adapter](https://github.com/tobscure/json-api-server/blob/master/src/Adapter/AdapterInterface.php) if you use a different ORM.

```php
use Tobscure\JsonApiServer\Adapter\EloquentAdapter;

$adapter = new EloquentAdapter(new User);
```

### Attributes

Define an [attribute field](https://jsonapi.org/format/#document-resource-object-attributes) on your resource using the `attribute` method:

```php
$schema->attribute('firstName');
```

By default the attribute will correspond to the property on your model with the same name. (`EloquentAdapter` will `snake_case` it automatically for you.) If you'd like it to correspond to a different property, provide it as a second argument:

```php
$schema->attribute('firstName', 'fname');
```

### Relationships

Define [relationship fields](https://jsonapi.org/format/#document-resource-object-relationships) on your resource using the `hasOne` and `hasMany` methods:

```php
$schema->hasOne('user');
$schema->hasMany('comments');
```

By default the [resource type](https://jsonapi.org/format/#document-resource-object-identification) that the relationship corresponds to will be derived from the relationship name. In the example above, the `user` relationship would correspond to the `users` resource type, while `comments` would correspond to `comments`. If you'd like to use a different resource type, provide it as a second argument:

```php
$schema->hasOne('author', 'people');
```

Like attributes, the relationship will automatically read and write to the relation on your model with the same name. If you'd like it to correspond to a different relation, provide it as a third argument.

Has-one relationships are available for [inclusion](https://jsonapi.org/format/#fetching-includes) via the `include` query parameter. You can include them by default, if the `include` query parameter is empty, by calling the `included` method:

```php
$schema->hasOne('user')
    ->included();
```

Has-many relationships must be explicitly made available for inclusion via the `includable` method. This is because pagination of included resources is not supported, so performance may suffer if there are large numbers of related resources.

```php
$schema->hasMany('comments')
    ->includable();
```

### Getters

Use the `get` method to define custom retrieval logic for your field, instead of just reading the value straight from the model property. (Of course, if you're using Eloquent, you could also define [casts](https://laravel.com/docs/5.7/eloquent-mutators#attribute-casting) or [accessors](https://laravel.com/docs/5.7/eloquent-mutators#defining-an-accessor) on your model to achieve a similar thing.)

```php
$schema->attribute('firstName')
    ->get(function ($model, $request) {
        return ucfirst($model->first_name);
    });
```

### Visibility

You can specify logic to restrict the visibility of a field using any one of the `visible`, `visibleIf`, `hidden`, and `hiddenIf` methods:

```php
$schema->attribute('email')
    // Make a field always visible (default)
    ->visible()

    // Make a field visible only if certain logic is met
    ->visibleIf(function ($model, $request) {
        return $model->id == $request->getAttribute('userId');
    })

    // Always hide a field (useful for write-only fields like password)
    ->hidden()

    // Hide a field only if certain logic is met
    ->hiddenIf(function ($model, $request) {
        return $request->getAttribute('userIsSuspended');
    });
```

You can also restrict the visibility of the whole resource using the `scope` method. This will allow you to modify the query builder object provided by your adapter:

```php
$schema->scope(function ($query, $request) {
    $query->where('user_id', $request->getAttribute('userId'));
});
```

### Making Fields Writable

By default, fields are read-only. You can allow a field to be written to using any one of the `writable`, `writableIf`, `readonly`, and `readonlyIf` methods:

```php
$schema->attribute('email')
    // Make an attribute writable
    ->writable()

    // Make an attribute writable only if certain logic is met
    ->writableIf(function ($model, $request) {
        return $model->id == $request->getAttribute('userId');
    })

    // Make an attribute read-only (default)
    ->readonly()

    // Make an attribute writable *unless* certain logic is met
    ->readonlyIf(function ($model, $request) {
        return $request->getAttribute('userIsSuspended');
    });
```

### Default Values

You can provide a default value for a field to be used when creating a new resource if there is no value provided by the consumer. Pass a value or a closure to the `default` method:


```php
$schema->attribute('joinedAt')
    ->default(new DateTime);

$schema->attribute('ipAddress')
    ->default(function ($request) {
        return $request->getServerParams()['REMOTE_ADDR'] ?? null;
    });
```

If you're using Eloquent, you could also define [default attribute values](https://laravel.com/docs/5.7/eloquent#default-attribute-values) to achieve a similar thing, although you wouldn't have access to the request object.

### Validation

You can ensure that data provided for a field is valid before it is saved. Provide a closure to the `validate` method, and call the first argument if validation fails:

```php
$schema->attribute('email')
    ->validate(function ($fail, $email) {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Invalid email');
        }
    });

$schema->hasMany('groups')
    ->validate(function ($fail, $groups) {
        foreach ($groups as $group) {
            if ($group->id === 1) {
                $fail('You cannot assign this group');
            }
        }
    });
```

See [Macros](#macros) below to learn how to use Laravel's [Validation](https://laravel.com/docs/5.7/validation) component in your schema.

### Setters & Savers

Use the `set` method to define custom mutation logic for your field, instead of just setting the value straight on the model property. (Of course, if you're using Eloquent, you could also define [casts](https://laravel.com/docs/5.7/eloquent-mutators#attribute-casting) or [mutators](https://laravel.com/docs/5.7/eloquent-mutators#defining-a-mutator) on your model to achieve a similar thing.)

```php
$schema->attribute('firstName')
    ->set(function ($model, $value, $request) {
        return $model->first_name = strtolower($value);
    });
```

If your attribute corresponds to some other form of data storage rather than a simple property on your model, you can use the `save` method to provide a closure to be run _after_ your model is saved:

```php
$schema->attribute('locale')
    ->save(function ($model, $value, $request) {
        $model->preferences()->update(['value' => $value])->where('key', 'locale');
    });
```

### Filtering

You can define a field as `filterable` to allow the resource index to be [filtered](https://jsonapi.org/recommendations/#filtering) by the field's value. This works for both attributes and relationships:

```php
$schema->attribute('firstName')
    ->filterable();

$schema->hasMany('groups')
    ->filterable();
    
// e.g. GET /api/users?filter[firstName]=Toby&filter[groups]=1,2,3
```

You can optionally pass a closure to customize how the filter is applied to the query builder object provided by your adapter:

```php
$schema->attribute('minPosts')
    ->hidden()
    ->filterable(function ($query, $value, $request) {
        $query->where('postCount', '>=', $value);
    });
```

### Sorting

You can define an attribute as `sortable` to allow the resource index to be [sorted](https://jsonapi.org/format/#fetching-sorting) by the attribute's value:

```php
$schema->attribute('firstName')
    ->sortable();
    
$schema->attribute('lastName')
    ->sortable();
    
// e.g. GET /api/users?sort=lastName,firstName
```

### Pagination

By default, resources are automatically [paginated](https://jsonapi.org/format/#fetching-pagination) with 20 records per page. You can change this limit using the `paginate` method on the schema builder:

```php
$schema->paginate(50);
```

### Creating Resources

By default, resources are not [creatable](https://jsonapi.org/format/#crud-creating) (i.e. `POST` requests will return `403 Forbidden`). You can allow them to be created using the `creatable`, `creatableIf`, `notCreatable`, and `notCreatableIf` methods on the schema builder:

```php
$schema->creatableIf(function ($request) {
    return $request->getAttribute('isAdmin');
});
```

### Deleting Resources

By default, resources are not [deletable](https://jsonapi.org/format/#crud-deleting) (i.e. `DELETE` requests will return `403 Forbidden`). You can allow them to be deleted using the `deletable`, `deletableIf`, `notDeletable`, and `notDeletableIf` methods on the schema builder:

```php
$schema->deletableIf(function ($request) {
    return $request->getAttribute('isAdmin');
});
```

### Macros

You can define macros on the `Tobscure\JsonApiServer\Schema\Attribute` class to aid construction of your API schema. Below is an example that sets up a `rules` macro which will add a validator to validate the attribute value using Laravel's [Validation](https://laravel.com/docs/5.7/validation) component:

```php
use Tobscure\JsonApiServer\Schema\Attribute;

Attribute::macro('rules', function ($rules) use ($validator) {
    $this->validate(function ($fail, $value) use ($validator, $rules) {
        $key = $this->name;
        $validation = Validator::make([$key => $value], [$key => $rules]);

        if ($validation->fails()) {
            $fail((string) $validation->messages());
        }
    });
});
```

```php
$schema->attribute('username')
    ->rules(['required', 'min:3', 'max:30']);
```

## Examples

- [Flarum](https://github.com/flarum/core/tree/master/src/Api) is forum software that uses tobscure/json-api-server to power its API.

## Contributing

Feel free to send pull requests or create issues if you come across problems or have great ideas. See the [Contributing Guide](https://github.com/tobscure/json-api-server/blob/master/CONTRIBUTING.md) for more information.

### Running Tests

```bash
$ vendor/bin/phpunit
```

## License

This code is published under the [The MIT License](LICENSE). This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.
