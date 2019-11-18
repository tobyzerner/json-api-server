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
use Tobyz\JsonApiServer\Adapter\EloquentAdapter;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;

$api = new JsonApi('http://example.com/api');

$api->resource('articles', new EloquentAdapter(Article::class), function (Type $type) {
    $type->attribute('title');
    $type->hasOne('author')->type('people');
    $type->hasMany('comments');
});

$api->resource('people', new EloquentAdapter(User::class), function (Type $type) {
    $type->attribute('firstName');
    $type->attribute('lastName');
    $type->attribute('twitter');
});

$api->resource('comments', new EloquentAdapter(Comment::class), function (Type $type) {
    $type->attribute('body');
    $type->hasOne('author')->type('people');
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
use Tobyz\JsonApiServer\JsonApi;

$api = new JsonApi('http://example.com/api');

try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

`Tobyz\JsonApiServer\JsonApi` is a [PSR-15 Request Handler](https://www.php-fig.org/psr/psr-15/). Instantiate it with your API's base URL. Convert your framework's request object into a [PSR-7 Request](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface) implementation, then let the `JsonApi` handler take it from there. Catch any exceptions and give them back to `JsonApi` to generate a JSON:API error response.

### Defining Resources

Define your API's resources using the `resource` method. The first argument is the [resource type](https://jsonapi.org/format/#document-resource-object-identification). The second is an instance of `Tobyz\JsonApiServer\Adapter\AdapterInterface` which will allow the handler to interact with your models. The third is a closure in which you'll build the schema for your resource.

```php
use Tobyz\JsonApiServer\Schema\Type;

$api->resource('comments', $adapter, function (Type $type) {
    // define your schema
});
```

We provide an `EloquentAdapter` to hook your resources up with Laravel [Eloquent](https://laravel.com/docs/5.8/eloquent) models. Set it up with the name of the model that your resource represents. You can [implement your own adapter](https://github.com/tobyz/json-api-server/blob/master/src/Adapter/AdapterInterface.php) if you use a different ORM.

```php
use Tobyz\JsonApiServer\Adapter\EloquentAdapter;

$adapter = new EloquentAdapter(User::class);
```

### Attributes

Define an [attribute field](https://jsonapi.org/format/#document-resource-object-attributes) on your resource using the `attribute` method:

```php
$type->attribute('firstName');
```

By default the attribute will correspond to the property on your model with the same name. (`EloquentAdapter` will `snake_case` it automatically for you.) If you'd like it to correspond to a different property, use the `property` method:

```php
$type->attribute('firstName')
    ->property('fname');
```

### Relationships

Define [relationship fields](https://jsonapi.org/format/#document-resource-object-relationships) on your resource using the `hasOne` and `hasMany` methods:

```php
$type->hasOne('user');
$type->hasMany('comments');
```

By default the [resource type](https://jsonapi.org/format/#document-resource-object-identification) that the relationship corresponds to will be derived from the relationship name. In the example above, the `user` relationship would correspond to the `users` resource type, while `comments` would correspond to `comments`. If you'd like to use a different resource type, call the `type` method:

```php
$type->hasOne('author')
    ->type('people');
```

Like attributes, the relationship will automatically read and write to the relation on your model with the same name. If you'd like it to correspond to a different relation, use the `property` method.

#### Relationship Links

Relationships include [`self`](https://jsonapi.org/format/#fetching-relationships) and [`related`](https://jsonapi.org/format/#document-resource-object-related-resource-links) links automatically. For some relationships it may not make sense to have them accessible via their own URL; you may disable these links by calling the `noLinks` method:

```php
$type->hasOne('mostRelevantPost')
    ->noLinks();
```

> **Note:** Accessing these URLs is not yet implemented. 

#### Relationship Linkage

By default relationships include no [resource linkage](https://jsonapi.org/format/#document-resource-object-linkage). You can toggle this by calling the `linkage` or `noLinkage` methods.

```php
$type->hasOne('user')
    ->linkage();
```

> **Warning:** Be careful when enabling linkage on to-many relationships as pagination is not supported.

#### Relationship Inclusion

To make a relationship available for [inclusion](https://jsonapi.org/format/#fetching-includes) via the `include` query parameter, call the `includable` method.

```php
$type->hasOne('user')
    ->includable();
```

> **Warning:** Be careful when making to-many relationships includable as pagination is not supported.

Relationships included via the `include` query parameter are automatically [eager-loaded](https://laravel.com/docs/5.8/eloquent-relationships#eager-loading) by the adapter. However, you may wish to define your own eager-loading logic, or prevent a relationship from being eager-loaded. You can do so using the `loadable` and `notLoadable` methods:

```php
$type->hasOne('user')
    ->includable()
    ->loadable(function ($models, ServerRequestInterface $request) {
        collect($models)->load(['user' => function () { /* constraints */ }]);
    });

$type->hasOne('user')
    ->includable()
    ->notLoadable();
```

#### Polymorphic Relationships

Define a relationship as polymorphic using the `polymorphic` method:

```php
$type->hasOne('commentable')
    ->polymorphic();

$type->hasMany('taggable')
    ->polymorphic();
```

This will mean that the resource type associated with the relationship will be derived from the model of each related resource. Consequently, nested includes cannot be requested on these relationships.

### Getters

Use the `get` method to define custom retrieval logic for your field, instead of just reading the value straight from the model property. (If you're using Eloquent, you could also define attribute [casts](https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting) or [accessors](https://laravel.com/docs/5.8/eloquent-mutators#defining-an-accessor) on your model to achieve a similar thing.)

```php
$type->attribute('firstName')
    ->get(function ($model, ServerRequestInterface $request) {
        return ucfirst($model->first_name);
    });
```

### Visibility

#### Resource Visibility

You can restrict the visibility of the whole resource using the `scope` method. This will allow you to modify the query builder object provided by your adapter:

```php
$type->scope(function ($query, ServerRequestInterface $request, string $id = null) {
    $query->where('user_id', $request->getAttribute('userId'));
});
```

The third argument to this callback (`$id`) is only populated if the request is to access a single resource. If the request is to a resource listing, it will be `null`.

If you want to prevent listing the resource altogether (ie. return `403 Forbidden` from `GET /articles`), you can use the `notListable` method:

```php
$type->notListable();
```

#### Field Visibility

You can specify logic to restrict the visibility of a field using the `visible` and `hidden` methods:

```php
$type->attribute('email')
    // Make a field always visible (default)
    ->visible()

    // Make a field visible only if certain logic is met
    ->visible(function ($model, ServerRequestInterface $request) {
        return $model->id == $request->getAttribute('userId');
    })

    // Always hide a field (useful for write-only fields like password)
    ->hidden()

    // Hide a field only if certain logic is met
    ->hidden(function ($model, ServerRequestInterface $request) {
        return $request->getAttribute('userIsSuspended');
    });
```

#### Expensive Fields

If a field is particularly expensive to calculate (for example, if you define a custom getter which runs a query), you can opt to only show the field when a single resource has been requested (ie. the field will not be included on resource listings). Use the `single` method to do this:

```php
$type->attribute('expensive')
    ->single();
```

### Writability

By default, fields are read-only. You can allow a field to be written to via `PATCH` and `POST` requests using the `writable` and `readonly` methods:

```php
$type->attribute('email')
    // Make an attribute writable
    ->writable()

    // Make an attribute writable only if certain logic is met
    ->writable(function ($model, ServerRequestInterface $request) {
        return $model->id == $request->getAttribute('userId');
    })

    // Make an attribute read-only (default)
    ->readonly()

    // Make an attribute writable *unless* certain logic is met
    ->readonly(function ($model, ServerRequestInterface $request) {
        return $request->getAttribute('userIsSuspended');
    });
```

### Default Values

You can provide a default value for a field to be used when creating a new resource if there is no value provided by the consumer. Pass a value or a closure to the `default` method:

```php
$type->attribute('joinedAt')
    ->default(new DateTime);

$type->attribute('ipAddress')
    ->default(function (ServerRequestInterface $request) {
        return $request->getServerParams()['REMOTE_ADDR'] ?? null;
    });
```

If you're using Eloquent, you could also define [default attribute values](https://laravel.com/docs/5.8/eloquent#default-attribute-values) to achieve a similar thing (although you wouldn't have access to the request object).

### Validation

You can ensure that data provided for a field is valid before it is saved. Provide a closure to the `validate` method, and call the first argument if validation fails:

```php
$type->attribute('email')
    ->validate(function (callable $fail, $email, $model, ServerRequestInterface $request) {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Invalid email');
        }
    });
```

This works for relationships too – the related models will be retrieved via your adapter and passed into your validation function.

```php
$type->hasMany('groups')
    ->validate(function (callable $fail, array $groups, $model, ServerRequestInterface $request) {
        foreach ($groups as $group) {
            if ($group->id === 1) {
                $fail('You cannot assign this group');
            }
        }
    });
```

You can easily use Laravel's [Validation](https://laravel.com/docs/5.8/validation) component for field validation with the `rules` function:

```php
use Tobyz\JsonApiServer\Laravel\rules;

$type->attribute('username')
    ->validate(rules('required', 'min:3', 'max:30'));
```

### Setters & Savers

Use the `set` method to define custom mutation logic for your field, instead of just setting the value straight on the model property. (If you're using Eloquent, you could also define attribute [casts](https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting) or [mutators](https://laravel.com/docs/5.8/eloquent-mutators#defining-a-mutator) on your model to achieve a similar thing.)

```php
$type->attribute('firstName')
    ->set(function ($model, $value, ServerRequestInterface $request) {
        $model->first_name = strtolower($value);
    });
```

If your field corresponds to some other form of data storage rather than a simple property on your model, you can use the `save` method to provide a closure to be run _after_ your model is saved:

```php
$type->attribute('locale')
    ->save(function ($model, $value, ServerRequestInterface $request) {
        $model->preferences()
            ->where('key', 'locale')
            ->update(['value' => $value]);
    });
```

### Filtering

You can define a field as `filterable` to allow the resource index to be [filtered](https://jsonapi.org/recommendations/#filtering) by the field's value. This works for both attributes and relationships:

```php
$type->attribute('firstName')
    ->filterable();

$type->hasMany('groups')
    ->filterable();
    
// eg. GET /api/users?filter[firstName]=Toby&filter[groups]=1,2,3
```

The `EloquentAdapter` automatically parses and applies `>`, `>=`, `<`, `<=`, and `..` operators on attribute filter values, so you can do:

```
GET /api/users?filter[postCount]=>=10
GET /api/users?filter[postCount]=5..15
```

To define filters with custom logic, or ones that do not correspond to an attribute, use the `filter` method:

```php
$type->filter('minPosts', function ($query, $value, ServerRequestInterface $request) {
    $query->where('postCount', '>=', $value);
});
```

### Sorting

You can define an attribute as `sortable` to allow the resource index to be [sorted](https://jsonapi.org/format/#fetching-sorting) by the attribute's value:

```php
$type->attribute('firstName')
    ->sortable();
    
$type->attribute('lastName')
    ->sortable();
    
// e.g. GET /api/users?sort=lastName,firstName
```

You can set a default sort string to be used when the consumer has not supplied one using the `defaultSort` method on the schema builder:

```php
$type->defaultSort('-updatedAt,-createdAt');
```

To define sort fields with custom logic, or ones that do not correspond to an attribute, use the `sort` method:

```php
$type->sort('relevance', function ($query, $direction, ServerRequestInterface $request) {
    $query->orderBy('relevance', $direction);
});
```

### Pagination

By default, resource listings are automatically [paginated](https://jsonapi.org/format/#fetching-pagination) with 20 records per page. You can change this amount using the `paginate` method on the schema builder, or you can remove it by calling the `dontPaginate` method:

```php
$type->paginate(50); // default to listing 50 resources per page
$type->dontPaginate(); // default to listing all resources
```

Consumers may request a different limit using the `page[limit]` query parameter. By default the maximum possible limit is capped at 50; you can change this cap using the `limit` method, or you can remove it by calling the `noLimit` method:

```php
$type->limit(100); // set the maximum limit for resources per page to 100
$type->noLimit(); // remove the maximum limit for resources per page
```

#### Countability

By default a query will be performed to count the total number of resources in a collection. This will be used to populate a `total` attribute in the document's `meta` object, as well as the `last` pagination link. For some types of resources, or when a query is resource-intensive (especially when certain filters or sorting is applied), it may be undesirable to have this happen. So it can be toggled using the `countable` and `uncountable` methods:

```php
$type->countable();
$type->uncountable();
```

### Meta Information

You can add meta information to any resource or relationship field using the `meta` method:

```php
$type->meta('requestTime', function (ServerRequestInterface $request) {
    return new DateTime;
});
```

### Creating Resources

By default, resources are not [creatable](https://jsonapi.org/format/#crud-creating) (ie. `POST` requests will return `403 Forbidden`). You can allow them to be created using the `creatable` and `notCreatable` methods on the schema builder. Pass a closure that returns `true` if the resource should be creatable, or no value to have it always creatable.

```php
$type->creatable();

$type->creatable(function (ServerRequestInterface $request) {
    return $request->getAttribute('isAdmin');
});
```

#### Customizing the Model

When creating a resource, an empty model is supplied by the adapter. You may wish to override this and provide a custom model in special circumstances. You can do so using the `createModel` method:

```php
$type->createModel(function (ServerRequestInterface $request) {
    return new CustomModel;
});
```

### Updating Resources

By default, resources are not [updatable](https://jsonapi.org/format/#crud-updating) (i.e. `PATCH` requests will return `403 Forbidden`). You can allow them to be updated using the `updatable` and `notUpdatable` methods on the schema builder:

```php
$type->updatable();

$type->updatable(function (ServerRequestInterface $request) {
    return $request->getAttribute('isAdmin');
});
```

### Deleting Resources

By default, resources are not [deletable](https://jsonapi.org/format/#crud-deleting) (i.e. `DELETE` requests will return `403 Forbidden`). You can allow them to be deleted using the `deletable` and `notDeletable` methods on the schema builder:

```php
$type->deletable();

$type->deletable(function (ServerRequestInterface $request) {
    return $request->getAttribute('isAdmin');
});
```

### Events

The server will fire several events, allowing you to hook into the following points in a resource's lifecycle: `listing`, `listed`, `showing`, `shown`, `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`. (If you're using Eloquent, you could also use [model events](https://laravel.com/docs/5.8/eloquent#events) to achieve a similar thing, although you wouldn't have access to the request object.)

To listen for an event, simply call the matching method name on the schema and pass a closure to be executed, which will receive the model and the request:

```php
$type->onCreating(function ($model, ServerRequestInterface $request) {
    // do something before a new model is saved
});
```

### Authentication

You are responsible for performing your own authentication. An effective way to pass information about the authenticated user is by setting attributes on your request object before passing it into the request handler.

You should indicate to the server if the consumer is authenticated using the `authenticated` method. This is important because it will determine whether the response will be `401 Unauthorized` or `403 Forbidden` in the case of an unauthorized request.

```php
$api->authenticated();
```

## Contributing

Feel free to send pull requests or create issues if you come across problems or have great ideas.

## License

This code is published under the [The MIT License](LICENSE). This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.
