# tobyz/json-api-server

[![Build Status](https://img.shields.io/travis/com/tobyz/json-api-server.svg?style=flat)](https://travis-ci.com/tobyz/json-api-server)
[![Pre Release](https://img.shields.io/packagist/vpre/tobyz/json-api-server.svg?style=flat)](https://github.com/tobyz/json-api-server/releases)
[![License](https://img.shields.io/packagist/l/tobyz/json-api-server.svg?style=flat)](https://packagist.org/packages/tobyz/json-api-server)

**A fully automated framework-agnostic [JSON:API](http://jsonapi.org) server implementation in PHP.**  
Define your schema, plug in your models, and we'll take care of the rest. 🍻

```bash
composer require tobyz/json-api-server
```

```php
use Tobyz\JsonApiServer\Api;
use Tobyz\JsonApiServer\Adapter\EloquentAdapter;
use Tobyz\JsonApiServer\Schema\Builder;

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

Assuming you have a few [Eloquent](https://laravel.com/docs/5.8/eloquent) models set up, the above code will serve a **complete JSON:API that conforms to the [spec](https://jsonapi.org/format/)**, including support for:

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
use Tobyz\JsonApiServer\Api;

$api = new Api('http://example.com/api');

try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

`Tobyz\JsonApiServer\Api` is a [PSR-15 Request Handler](https://www.php-fig.org/psr/psr-15/). Instantiate it with your API's base URL. Convert your framework's request object into a [PSR-7 Request](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface) implementation, then let the `Api` handler take it from there. Catch any exceptions and give them back to `Api` if you want a JSON:API error response.

### Defining Resources

Define your API's resources using the `resource` method. The first argument is the [resource type](https://jsonapi.org/format/#document-resource-object-identification). The second is an instance of `Tobyz\JsonApiServer\Adapter\AdapterInterface` which will allow the handler to interact with your models. The third is a closure in which you'll build the schema for your resource.

```php
use Tobyz\JsonApiServer\Schema\Builder;

$api->resource('comments', $adapter, function (Builder $schema) {
    // define your schema
});
```

We provide an `EloquentAdapter` to hook your resources up with Laravel [Eloquent](https://laravel.com/docs/5.8/eloquent) models. Set it up with an instance of the model that your resource represents. You can [implement your own adapter](https://github.com/tobyz/json-api-server/blob/master/src/Adapter/AdapterInterface.php) if you use a different ORM.

```php
use Tobyz\JsonApiServer\Adapter\EloquentAdapter;

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

#### Relationship Links

Relationships include [`self`](https://jsonapi.org/format/#fetching-relationships) and [`related`](https://jsonapi.org/format/#document-resource-object-related-resource-links) links automatically. For some relationships it may not make sense to have them accessible via their own URL; you may disable these links by calling the `noLinks` method:

```php
$schema->hasOne('mostRelevantPost')
    ->noLinks();
```

> **Note:** Accessing these URLs is not yet implemented. 

#### Relationship Linkage

By default relationships include no [resource linkage](https://jsonapi.org/format/#document-resource-object-linkage). You can toggle this (without forcing the related resources to be included) by calling the `linkage` or `noLinkage` methods.

```php
$schema->hasOne('user')
    ->linkage();
```

> **Warning:** Be careful when enabling linkage on to-many relationships as pagination is not supported.

#### Relationship Inclusion

To make a relationship available for [inclusion](https://jsonapi.org/format/#fetching-includes) via the `include` query parameter, call the `includable` method.

```php
$schema->hasOne('user')
    ->includable();
```

> **Warning:** Be careful when making to-many relationships includable as pagination is not supported.

Relationships included via the `include` query parameter are automatically eager-loaded. However, you may wish to define your own eager-loading logic, or prevent a relationship from being eager-loaded. You can do so using the `loadable` and `notLoadable` methods:

```php
$schema->hasOne('user')
    ->includable()
    ->loadable(function ($models, $request) {
        collect($models)->load(['user' => function () { /* constraints */ }]);
    });

$schema->hasOne('user')
    ->includable()
    ->notLoadable();
```

#### Polymorphic Relationships

Define polymorphic relationships on your resource using the `morphOne` and `morphMany` methods:

```php
$schema->morphOne('commentable');
$schema->morphMany('taggable');
```

Polymorphic relationships do not accept a second argument for the resource type, because it will be automatically derived from each related resource. Nested includes cannot be requested on these relationships.

### Getters

Use the `get` method to define custom retrieval logic for your field, instead of just reading the value straight from the model property. (If you're using Eloquent, you could also define [casts](https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting) or [accessors](https://laravel.com/docs/5.8/eloquent-mutators#defining-an-accessor) on your model to achieve a similar thing.)

```php
$schema->attribute('firstName')
    ->get(function ($model, $request) {
        return ucfirst($model->first_name);
    });
```

### Visibility

#### Resource Visibility

You can restrict the visibility of the whole resource using the `scope` method. This will allow you to modify the query builder object provided by your adapter:

```php
$schema->scope(function ($query, $request, $id = null) {
    $query->where('user_id', $request->getAttribute('userId'));
});
```

The third argument to this callback (`$id`) is only populated if the request is to access a single resource. If the request is to a resource index, it will be `null`.

#### Field Visibility

You can specify logic to restrict the visibility of a field using the `visible` and `hidden` methods:

```php
$schema->attribute('email')
    // Make a field always visible (default)
    ->visible()

    // Make a field visible only if certain logic is met
    ->visible(function ($model, $request) {
        return $model->id == $request->getAttribute('userId');
    })

    // Always hide a field (useful for write-only fields like password)
    ->hidden()

    // Hide a field only if certain logic is met
    ->hidden(function ($model, $request) {
        return $request->getAttribute('userIsSuspended');
    });
```

### Writability

By default, fields are read-only. You can allow a field to be written to via `PATCH` and `POST` requests using the `writable` and `readonly` methods:

```php
$schema->attribute('email')
    // Make an attribute writable
    ->writable()

    // Make an attribute writable only if certain logic is met
    ->writable(function ($model, $request) {
        return $model->id == $request->getAttribute('userId');
    })

    // Make an attribute read-only (default)
    ->readonly()

    // Make an attribute writable *unless* certain logic is met
    ->readonly(function ($model, $request) {
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

If you're using Eloquent, you could also define [default attribute values](https://laravel.com/docs/5.8/eloquent#default-attribute-values) to achieve a similar thing, although you wouldn't have access to the request object.

### Validation

You can ensure that data provided for a field is valid before it is saved. Provide a closure to the `validate` method, and call the first argument if validation fails:

```php
$schema->attribute('email')
    ->validate(function ($fail, $email, $model, $request, $field) {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Invalid email');
        }
    });
```

This works for relationships too – the related models will be retrieved via your adapter and passed into your validation function.

```php
$schema->hasMany('groups')
    ->validate(function ($fail, $groups, $model, $request, $field) {
        foreach ($groups as $group) {
            if ($group->id === 1) {
                $fail('You cannot assign this group');
            }
        }
    });
```

You can easily use Laravel's [Validation](https://laravel.com/docs/5.8/validation) component for field validation with the `rules` function:

```php
use Tobyz\JsonApi\Server\Laravel\rules;

$schema->attribute('username')
    ->validate(rules('required', 'min:3', 'max:30'));
```

### Setters & Savers

Use the `set` method to define custom mutation logic for your field, instead of just setting the value straight on the model property. (Of course, if you're using Eloquent, you could also define [casts](https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting) or [mutators](https://laravel.com/docs/5.8/eloquent-mutators#defining-a-mutator) on your model to achieve a similar thing.)

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
        $model->preferences()
            ->update(['value' => $value])
            ->where('key', 'locale');
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

The `EloquentAdapter` automatically parses and applies `>`, `>=`, `<`, `<=`, and `..` operators on attribute filter values, so you can do:

```
GET /api/users?filter[postCount]=>=10
GET /api/users?filter[postCount]=5..15
```

You can also pass a closure to customize how the filter is applied to the query builder object:

```php
$schema->attribute('name')
    ->filterable(function ($query, $value, $request) {
        $query->where('first_name', $value)
            ->orWhere('last_name', $value);
    });
```

To define filters that do not correspond to an attribute, use the `filter` method:

```php
$schema->filter('minPosts', function ($query, $value, $request) {
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

You can pass a closure to customize how the sort is applied to the query builder object:

```php
$schema->attribute('name')
    ->sortable(function ($query, $direction, $request) {
        $query->orderBy('last_name', $direction)
            ->orderBy('first_name', $direction);
    });
```

You can set a default sort string to be used when the consumer has not supplied one using the `defaultSort` method on the schema builder:

```php
$schema->defaultSort('-updatedAt,-createdAt');
```

### Pagination

By default, resource listings are automatically [paginated](https://jsonapi.org/format/#fetching-pagination) with 20 records per page. You can change this limit using the `paginate` method on the schema builder, or you can remove it by passing `null`:

```php
$schema->paginate(50); // default to listing 50 resources per page
$schema->paginate(null); // default to listing all resources
```

Consumers may request a different limit using the `page[limit]` query parameter. By default the maximum possible limit is capped at 50; you can change this cap using the `limit` method, or you can remove it by passing `null`:

```php
$schema->limit(100); // set the maximum limit for resources per page to 100
$schema->limit(null); // remove the maximum limit for resources per page
```

#### Countability

By default a query will be performed to count the total number of resources in a collection. This will be used to populate a `count` attribute in the document's `meta` object, as well as the `last` pagination link. For some types of resources, or when a query is resource-intensive (especially when certain filters or sorting is applied), it may be undesirable to have this happen. So it can be toggled using the `countable` and `uncountable` methods:

```php
$schema->countable();
$schema->uncountable();
```

### Meta Information

You can add meta information to the document or any relationship field using the `meta` method. Pass a value or a closure:

```php
$schema->meta('author', 'Toby Zerner');
$schema->meta('requestTime', function ($request) {
    return new DateTime;
});
```

### Creating Resources

By default, resources are not [creatable](https://jsonapi.org/format/#crud-creating) (i.e. `POST` requests will return `403 Forbidden`). You can allow them to be created using the `creatable` and `notCreatable` methods on the schema builder:

```php
$schema->creatable(function ($request) {
    return $request->getAttribute('isAdmin');
});
```

#### Customizing the Model

When creating a resource, an empty model is supplied by the adapter. You may wish to provide a custom model in special circumstances. You can do so using the `create` method:

```php
$schema->create(function ($request) {
    return new CustomModel;
});
```

### Updating Resources

By default, resources are not [updatable](https://jsonapi.org/format/#crud-updating) (i.e. `PATCH` requests will return `403 Forbidden`). You can allow them to be updated using the `updatable` and `notUpdatable` methods on the schema builder:

```php
$schema->updatable(function ($request) {
    return $request->getAttribute('isAdmin');
});
```

### Deleting Resources

By default, resources are not [deletable](https://jsonapi.org/format/#crud-deleting) (i.e. `DELETE` requests will return `403 Forbidden`). You can allow them to be deleted using the `deletable` and `notDeletable` methods on the schema builder:

```php
$schema->deletable(function ($request) {
    return $request->getAttribute('isAdmin');
});
```

### Events

The server will fire several events, allowing you to hook into the following points in a resource's lifecycle: `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`. (Of course, if you're using Eloquent, you could also use [model events](https://laravel.com/docs/5.8/eloquent#events) to achieve a similar thing, although you wouldn't have access to the request object.)

To listen for an event, simply call the matching method name on the schema builder and pass a closure to be executed, which will receive the model and the request:

```php
$schema->creating(function ($model, $request) {
    // do something before a new model is saved
});
```

### Authentication

You are responsible for performing your own authentication. An effective way to pass information about the authenticated user is by setting attributes on your request object before passing it into the request handler.

You should indicate to the server if the consumer is authenticated using the `authenticated` method. This is important because it will determine whether the response will be `401 Unauthorized` or `403 Forbidden` in the case of an unauthorized request.

```php
$api->authenticated();
```

## Examples

- [Forust](https://github.com/forust/core/tree/master/schema) is forum software that uses tobyz/json-api-server to power its API.

## Contributing

Feel free to send pull requests or create issues if you come across problems or have great ideas. See the [Contributing Guide](https://github.com/tobyz/json-api-server/blob/master/CONTRIBUTING.md) for more information.

### Running Tests

```bash
$ vendor/bin/phpunit
```

## License

This code is published under the [The MIT License](LICENSE). This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.
