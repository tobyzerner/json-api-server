# Introduction

json-api-server is a comprehensive [JSON:API](http://jsonapi.org) server implementation in PHP.

It allows you to define your API's schema, and then use an [adapter](adapters.md) to connect it to your application's models and database layer, without having to worry about any of the server boilerplate, routing, query parameters, or JSON:API document formatting.

Based on your schema definition, the package will serve a **complete JSON:API that conforms to the [spec](https://jsonapi.org/format/)**, including support for:

- **Showing** individual resources (`GET /api/articles/1`)
- **Listing** resource collections (`GET /api/articles`)
- **Sorting**, **filtering**, **pagination**, and **sparse fieldsets**
- **Compound documents** with inclusion of related resources
- **Creating** resources (`POST /api/articles`)
- **Updating** resources (`PATCH /api/articles/1`)
- **Deleting** resources (`DELETE /api/articles/1`)
- **Error handling**

The schema definition is extremely powerful and lets you easily apply [permissions](visibility.md), [transformations](writing.md#transformers), [validation](writing.md#validation), and custom [filtering](filtering.md) and [sorting](sorting.md) logic to build a fully functional API in minutes.

### Example

The following example uses Eloquent models in a Laravel application. However, json-api-server can be used with any framework that can deal in PSR-7 Requests and Responses. Custom [adapters](adapters.md) can be used to support other ORMs and data persistence layers.

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
        ->validate(Laravel\rules('required'));

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
        ->validate(Laravel\rules('required'));

    $type->hasOne('article')
        ->writable()->once()
        ->validate(Laravel\rules('required'));

    $type->hasOne('author')->type('users')
        ->writable()->once()
        ->validate(Laravel\rules('required'));
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
