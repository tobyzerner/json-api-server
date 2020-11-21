# json-api-server

[![Pre Release](https://img.shields.io/packagist/vpre/tobyz/json-api-server.svg?style=flat)](https://github.com/tobyz/json-api-server/releases)
[![License](https://img.shields.io/packagist/l/tobyz/json-api-server.svg?style=flat)](https://packagist.org/packages/tobyz/json-api-server)

> **A fully automated [JSON:API](http://jsonapi.org) server implementation in PHP.**  
> Define your schema, plug in your models, and we'll take care of the rest. 🍻

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->


- [Installation](#installation)
- [Usage](#usage)
  - [Handling Requests](#handling-requests)
  - [Defining Resources](#defining-resources)
  - [Attributes](#attributes)
  - [Relationships](#relationships)
    - [Relationship Links](#relationship-links)
    - [Relationship Linkage](#relationship-linkage)
    - [Relationship Inclusion](#relationship-inclusion)
    - [Custom Loading Logic](#custom-loading-logic)
    - [Polymorphic Relationships](#polymorphic-relationships)
  - [Getters](#getters)
  - [Visibility](#visibility)
    - [Resource Visibility](#resource-visibility)
    - [Field Visibility](#field-visibility)
  - [Writability](#writability)
  - [Default Values](#default-values)
  - [Validation](#validation)
  - [Transformers, Setters & Savers](#transformers-setters--savers)
  - [Filtering](#filtering)
  - [Sorting](#sorting)
  - [Context](#context)
  - [Pagination](#pagination)
    - [Countability](#countability)
  - [Meta Information](#meta-information)
  - [Creating Resources](#creating-resources)
    - [Customizing the Model](#customizing-the-model)
    - [Customizing Creation Logic](#customizing-creation-logic)
  - [Updating Resources](#updating-resources)
    - [Customizing Update Logic](#customizing-update-logic)
  - [Deleting Resources](#deleting-resources)
  - [Events](#events)
  - [Authentication](#authentication)
  - [Laravel Helpers](#laravel-helpers)
    - [Authorization](#authorization)
    - [Validation](#validation-1)
  - [Meta Information](#meta-information-1)
    - [Document-level](#document-level)
    - [Resource-level](#resource-level)
    - [Relationship-level](#relationship-level)
  - [Modifying Responses](#modifying-responses)
- [Examples](#examples)
- [Contributing](#contributing)
- [License](#license)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Installation

```bash
composer require tobyz/json-api-server
```

## Usage

```php
use App\Models\{Article, Comment, User};
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\JsonApiServer\Laravel\EloquentAdapter;
use Tobyz\JsonApiServer\Laravel;

$api = new JsonApi('http://example.com/api');

$api->resource('articles', new EloquentAdapter(Article::class), function (Type $type) {
    $type->attribute('title')
        ->writable()
        ->required();

    $type->hasOne('author')->type('users')
        ->includable()
        ->filterable();

    $type->hasMany('comments')
        ->includable();
});

$api->resource('comments', new EloquentAdapter(Comment::class), function (Type $type) {
    $type->creatable(Laravel\authenticated());
    $type->updatable(Laravel\can('update-comment'));
    $type->deletable(Laravel\can('delete-comment'));

    $type->attribute('body')
        ->writable()
        ->required();

    $type->hasOne('article')
        ->required();

    $type->hasOne('author')->type('users')
        ->required();
});

$api->resource('users', new EloquentAdapter(User::class), function (Type $type) {
    $type->attribute('firstName')->sortable();
    $type->attribute('lastName')->sortable();
});

/** @var Psr\Http\Message\ServerRequestInterface $request */
/** @var Psr\Http\Message\ResponseInterface $response */
try {
    $response = $api->handle($request);
} catch (Exception $e) {
    $response = $api->error($e);
}
```

Assuming you have a few [Eloquent](https://laravel.com/docs/8.0/eloquent) models set up, the above code will serve a **complete JSON:API that conforms to the [spec](https://jsonapi.org/format/)**, including support for:

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

`Tobyz\JsonApiServer\JsonApi` is a [PSR-15 Request Handler](https://www.php-fig.org/psr/psr-15/). Instantiate it with your API's base URL or path. Convert your framework's request object into a [PSR-7 Request](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface) implementation, then let the `JsonApi` handler take it from there. Catch any exceptions and give them back to `JsonApi` to generate a JSON:API error response.

### Defining Resources

Define your API's resource types using the `resource` method. The first argument is the name of the [resource type](https://jsonapi.org/format/#document-resource-object-identification). The second is an instance of `Tobyz\JsonApiServer\Adapter\AdapterInterface` which will allow the handler to interact with your app's models. The third is a closure in which you'll build the schema for your resource type.

```php
use Tobyz\JsonApiServer\Schema\Type;

$api->resource('comments', $adapter, function (Type $type) {
    // define your schema
});
```

#### Adapters

We provide an `EloquentAdapter` to hook your resources up with Laravel [Eloquent](https://laravel.com/docs/8.0/eloquent) models. Set it up with the model class that your resource represents. You can [implement your own adapter](https://github.com/tobyz/json-api-server/blob/master/src/Adapter/AdapterInterface.php) if you use a different ORM.

```php
use Tobyz\JsonApiServer\Adapter\EloquentAdapter;

$adapter = new EloquentAdapter(User::class);
```

### Attributes

Define an [attribute field](https://jsonapi.org/format/#document-resource-object-attributes) on your resource using the `attribute` method:

```php
$type->attribute('firstName');
```

By default, the attribute will correspond to the property on your model with the same name. (`EloquentAdapter` will `snake_case` it automatically for you.) If you'd like it to correspond to a different property, use the `property` method:

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

By default, the [resource type](https://jsonapi.org/format/#document-resource-object-identification) that the relationship corresponds to will be derived from the relationship name. In the example above, the `user` relationship would correspond to the `users` resource type, while `comments` would correspond to `comments`. If you'd like to use a different resource type, call the `type` method:

```php
$type->hasOne('author')
    ->type('people');
```

Like attributes, the relationship will automatically read and write to the relation on your model with the same name. If you'd like it to correspond to a different relation, use the `property` method.

#### Relationship Links

Relationships include [`self`](https://jsonapi.org/format/#fetching-relationships) and [`related`](https://jsonapi.org/format/#document-resource-object-related-resource-links) links automatically. For some relationships it may not make sense to have them accessible via their own URL; you may disable these links by calling the `withoutLinks` method:

```php
$type->hasOne('mostRelevantPost')
    ->withoutLinks();
```

> **Note:** These URLs are not yet implemented. 

#### Relationship Linkage

By default, to-one relationships include [resource linkage](https://jsonapi.org/format/#document-resource-object-linkage), but to-many relationships do not. You can toggle this by calling the `withLinkage` or `withoutLinkage` methods.

```php
$type->hasMany('users')
    ->withwithLinkage();
```

> **Warning:** Be careful when enabling linkage on to-many relationships as pagination is not supported in relationships.

#### Relationship Inclusion

To make a relationship available for [inclusion](https://jsonapi.org/format/#fetching-includes) via the `include` query parameter, call the `includable` method.

```php
$type->hasOne('user')
    ->includable();
```

> **Warning:** Be careful when making to-many relationships includable as pagination is not supported.

Relationships included via the `include` query parameter are automatically [eager-loaded](https://laravel.com/docs/8.0/eloquent-relationships#eager-loading) by the adapter, and any type [scopes](#resource-visibility) are applied automatically. You can also apply additional scopes at the relationship level using the `scope` method:

```php
$type->hasOne('users')
    ->includable()
    ->scope(function ($query, ServerRequestInterface $request, HasOne $field) {
        $query->where('is_listed', true);
    });
```

#### Custom Loading Logic

Instead of using the adapter's eager-loading logic, you may wish to define your own for a relationship. You can do so using the `load` method. Beware that this can be complicated as eager-loading always takes place on the set of models at the root level; these are passed as the first parameter. The second parameter is an array of the `Relationship` objects that make up the nested inclusion trail leading to the current relationship. So, for example, if a request was made to `GET /categories?include=latestPost.user`, then the custom loading logic for the `user` relationship might look like this:

```php
$api->resource('categories', new EloquentAdapter(Models\Category::class), function (Type $type) {
    $type->hasOne('latestPost')->type('posts')->includable(); // 1
});

$api->resource('posts', new EloquentAdapter(Models\Post::class), function (Type $type) {
    $type->hasOne('user') // 2
        ->includable()
        ->load(function (array $models, array $relationships, Context $context) {
            // Since this request is to the `GET /categories` endpoint, $models
            // will be an array of Category models, and $relationships will be
            // an array containing the objects [1, 2] above.
        });
});
```

To prevent a relationship from being eager-loaded altogether, use the `dontLoad` method:

```php
$type->hasOne('user')
    ->includable()
    ->dontLoad();
```

#### Polymorphic Relationships

Define a polymorphic relationship using the `polymorphic` method. Optionally you may provide an array of allowed resource types:

```php
$type->hasOne('commentable')
    ->polymorphic();

$type->hasMany('taggable')
    ->polymorphic(['photos', 'videos']);
```

Note that nested includes cannot be requested on polymorphic relationships.

### Getters

Use the `get` method to define custom retrieval logic for your field, instead of just reading the value straight from the model property. (If you're using Eloquent, you could also define attribute [casts](https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting) or [accessors](https://laravel.com/docs/5.8/eloquent-mutators#defining-an-accessor) on your model to achieve a similar thing.)

```php
$type->attribute('firstName')
    ->get(function ($model, Context $context) {
        return ucfirst($model->first_name);
    });
```

### Visibility

#### Resource Visibility

You can restrict the visibility of the whole resource using the `scope` method. This will allow you to modify the query builder object provided by the adapter:

```php
$type->scope(function ($query, Context $context) {
    $query->where('user_id', $context->getRequest()->getAttribute('userId'));
});
```

The third argument to this callback (`$id`) is only populated if the request is to access a single resource. If the request is to a resource listing, it will be `null`.

If you want to prevent listing the resource altogether (ie. return `405 Method Not Allowed` from `GET /articles`), you can use the `notListable` method:

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
    ->visible(function ($model, Context $context) {
        return $model->id == $context->getRequest()->getAttribute('userId');
    })

    // Always hide a field (useful for write-only fields like password)
    ->hidden()

    // Hide a field only if certain logic is met
    ->hidden(function ($model, Context $context) {
        return $context->getRequest()->getAttribute('userIsSuspended');
    });
```

### Writability

By default, fields are read-only. You can allow a field to be written to via `PATCH` and `POST` requests using the `writable` and `readonly` methods:

```php
$type->attribute('email')
    // Make an attribute writable
    ->writable()

    // Make an attribute writable only if certain logic is met
    ->writable(function ($model, Context $context) {
        return $model->id == $context->getRequest()->getAttribute('userId');
    })

    // Make an attribute read-only (default)
    ->readonly()

    // Make an attribute writable *unless* certain logic is met
    ->readonly(function ($model, Context $context) {
        return $context->getRequest()->getAttribute('userIsSuspended');
    });
```

### Default Values

You can provide a default value for a field to be used when creating a new resource if there is no value provided by the consumer. Pass a value or a closure to the `default` method:

```php
$type->attribute('joinedAt')
    ->default(new DateTime);

$type->attribute('ipAddress')
    ->default(function (Context $context) {
        return $context->getRequest()->getServerParams()['REMOTE_ADDR'] ?? null;
    });
```

If you're using Eloquent, you could also define [default attribute values](https://laravel.com/docs/5.8/eloquent#default-attribute-values) to achieve a similar thing (although you wouldn't have access to the request object).

### Validation

You can ensure that data provided for a field is valid before it is saved. Provide a closure to the `validate` method, and call the first argument if validation fails:

```php
$type->attribute('email')
    ->validate(function (callable $fail, $value, $model, Context $context) {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('Invalid email');
        }
    });
```

This works for relationships too – the related models will be retrieved via your adapter and passed into your validation function.

```php
$type->hasMany('groups')
    ->validate(function (callable $fail, array $groups, $model, Context $context) {
        foreach ($groups as $group) {
            if ($group->id === 1) {
                $fail('You cannot assign this group');
            }
        }
    });
```

You can easily use Laravel's [Validation](https://laravel.com/docs/8.0/validation) component for field validation with the `rules` function:

```php
use Tobyz\JsonApiServer\Laravel\rules;

$type->attribute('username')
    ->validate(rules(['required', 'min:3', 'max:30']));
```

### Transformers, Setters & Savers

Use the `transform` method on an attribute to mutate any incoming value before it is saved to the model. (If you're using Eloquent, you could also define attribute [casts](https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting) or [mutators](https://laravel.com/docs/5.8/eloquent-mutators#defining-a-mutator) on your model to achieve a similar thing.)

```php
$type->attribute('firstName')
    ->transform(function ($value, Context $context) {
        return ucfirst($value);
    });
```

Use the `set` method to define custom mutation logic for your field, instead of just setting the value straight on the model property.

```php
$type->attribute('firstName')
    ->set(function ($value, $model, Context $context) {
        $model->first_name = ucfirst($value);
        if ($model->first_name === 'Toby') {
            $model->last_name = 'Zerner';
        }
    });
```

If your field corresponds to some other form of data storage rather than a simple property on your model, you can use the `save` method to provide a closure to be run _after_ your model has been successfully saved. If specified, the adapter will NOT be used to set the field on the model.

```php
$type->attribute('locale')
    ->save(function ($value, $model, Context $context) {
        $model->preferences()
            ->where('key', 'locale')
            ->update(['value' => $value]);
    });
```

Finally, you can add an event listener to be run after a field has been saved using the `onSaved` method:

```php
$type->attribute('email')
    ->onSaved(function ($value, $model, Context $context) {
        event(new EmailWasChanged($model));
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

The `>`, `>=`, `<`, `<=`, and `..` operators on attribute filter values are automatically parsed and applied, supporting queries like:

```
GET /api/users?filter[postCount]=>=10
GET /api/users?filter[postCount]=5..15
```

To define filters with custom logic, or ones that do not correspond to an attribute, use the `filter` method:

```php
$type->filter('minPosts', function ($query, $value, Context $context) {
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
$type->sort('relevance', function ($query, string $direction, Context $context) {
    $query->orderBy('relevance', $direction);
});
```

### Context

The `Context` object is passed through to all callbacks. This object has a few useful methods:

```php
$context->getApi(); // Get the root API object
$context->getRequest(); // Get the current request being handled
$context->setRequest($request); // Modify the current request
$context->getField(); // In the context of a field callback, get the current field
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

By default, a query will be performed to count the total number of resources in a collection. This will be used to populate a `total` attribute in the document's `meta` object, as well as the `last` pagination link. For some types of resources, or when a query is resource-intensive (especially when certain filters or sorting is applied), it may be undesirable to have this happen. So it can be toggled using the `countable` and `uncountable` methods:

```php
$type->countable();
$type->uncountable();
```

### Meta Information

You can add meta information to a resource using the `meta` method:

```php
$type->meta('requestTime', function ($model, Context $context) {
    return new DateTime;
});
```

or relationship field :

```php
$type->hasOne('user')
    ->meta('updatedAt', function ($model, $user, Context $context) {
        return $user->updated_at;
    });
```

### Creating Resources

By default, resources are not [creatable](https://jsonapi.org/format/#crud-creating) (ie. `POST` requests will return `403 Forbidden`). You can allow them to be created using the `creatable` and `notCreatable` methods on the schema builder. Pass a closure that returns `true` if the resource should be creatable, or no value to have it always creatable.

```php
$type->creatable();

$type->creatable(function (Context $context) {
    return $request->getAttribute('isAdmin');
});
```

#### Customizing the Model

When creating a resource, an empty model is supplied by the adapter. You may wish to override this and provide a custom model in special circumstances. You can do so using the `newModel` method:

```php
$type->newModel(function (Context $context) {
    return new CustomModel;
});
```

#### Customizing Creation Logic

```php
$type->create(function ($model, Context $context) {
    // push to a queue
});
```

### Updating Resources

By default, resources are not [updatable](https://jsonapi.org/format/#crud-updating) (i.e. `PATCH` requests will return `403 Forbidden`). You can allow them to be updated using the `updatable` and `notUpdatable` methods on the schema builder:

```php
$type->updatable();

$type->updatable(function (Context $context) {
    return $context->getRequest()->getAttribute('isAdmin');
});
```

#### Customizing Update Logic

```php
$type->update(function ($model, Context $context) {
    // push to a queue
});
```

### Deleting Resources

By default, resources are not [deletable](https://jsonapi.org/format/#crud-deleting) (i.e. `DELETE` requests will return `403 Forbidden`). You can allow them to be deleted using the `deletable` and `notDeletable` methods on the schema builder:

```php
$type->deletable();

$type->deletable(function (ServerRequestInterface $request) {
    return $request->getAttr``ibute('isAdmin');
});

$type->delete(function ($model, Context $context) {
    $model->delete();
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

### Laravel Helpers

#### Authorization

#### Validation

### Meta Information

#### Document-level

#### Resource-level

#### Relationship-level

### Modifying Responses

## Examples

* TODO

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](LICENSE)
