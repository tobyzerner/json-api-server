# Laravel Integration

json-api-server is a great way to build APIs in Laravel applications.

## Handling Requests

To route requests to json-api-server, you will need to obtain an instance of a
[PSR-7 request](https://laravel.com/docs/10.x/requests#psr7-requests) instead of
a Laravel request. First, install the Symfony HTTP Message Bridge and a
compatible implementation:

```bash
composer require symfony/psr-http-message-bridge
composer require nyholm/psr7
```

Then, define a catch-all route for your API and type-hint the request interface
on your route closure or controller method. You can then return the response
directly – the framework will automatically convert it back into a Laravel
response and display it.

```php
use App\Http\Api\Resources;
use Psr\Http\Message\ServerRequestInterface;

Route::get('/api/{uri}', function (ServerRequestInterface $request) {
    $api = new JsonApi('/api');

    $api->resource(new Resources\PostsResource());
    $api->resource(new Resources\UsersResource());

    try {
        return $api->handle($request);
    } catch (Throwable $e) {
        return $api->error($e);
    }
})->where('uri', '.*');
```

## Eloquent Resources

The Laravel integration provides an `EloquentResource` base class which
implements all of the required behaviour for the main endpoints. You can define
Eloquent-backed resources by extending this class, and everything will just
work:

```php
use Tobyz\JsonApiServer\Laravel\EloquentResource;

class PostsResource extends EloquentResource
{
    public function type(): string
    {
        return 'posts';
    }

    public function newModel(Context $context): object
    {
        return new Post();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Index::make(),
            Endpoint\Create::make(),
            Endpoint\Update::make(),
            Endpoint\Delete::make(),
        ];
    }

    public function fields(): array
    {
        return [
            Field\Attribute::make('title'),
            Field\ToOne::make('author')->type('users'),
        ];
    }
}
```

### Properties

Eloquent resources will automatically convert field names into `snake_case` when
getting and setting model values. For example, a field with the name `createdAt`
will read its value from the model's `created_at` property.

### Scoping

If you need to modify the query used to retrieve and list models – for example,
to only allow the authenticated user to see models that they own, or to select
additional columns – override the `scope` method on your resource class:

```php
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;

class PostsResource extends EloquentResource
{
    // ...

    public function scope(Builder $query, Context $context)
    {
        $query->whereBelongsTo(Auth::user());
    }
}
```

This method will also be used to scope queries when retrieving related models
for a relationship.

### Soft Deleting

If your Eloquent model is
[soft deletable](https://laravel.com/docs/10.x/eloquent#soft-deleting), you can
choose whether or not the soft delete capability is exposed to the JSON:API
client. By default, when a client sends a `DELETE` request to remove a resource,
the model will be soft-deleted and it will no longer appear in the API.

Alternatively, you can expose the soft-delete capability to the client, meaning
the client will be able to soft-delete and restore resources via `PATCH`
requests, and force delete a resource using a `DELETE` request.

To expose the soft-delete capability to the client, add the
`Tobyz\JsonApiServer\Laravel\SoftDeletes` trait to your Eloquent resource, and a
nullable [`DateTime` field](attributes.md#date-and-datetime) to your fields
array:

```php
use Tobyz\JsonApiServer\Laravel\SoftDeletes; // [!code ++]
use Tobyz\JsonApiServer\Schema\Field\Attribute; // [!code ++]
use Tobyz\JsonApiServer\Schema\Type\DateTime; // [!code ++]

class PostsResource extends EloquentResource
{
    use SoftDeletes; // [!code ++]

    // ...

    public function fields(): array
    {
        return [
            Attribute::make('deletedAt') // [!code ++]
                ->type(DateTime::make()), // [!code ++]
                ->nullable(), // [!code ++]
        ];
    }
}
```

If you prefer to use a boolean to indicate whether or not a resource is
soft-deleted instead of a nullable date-time value, you can use a
[`BooleanDateTime` attribute](attributes.md#booleandatetime) instead:

```php
use Tobyz\JsonApiServer\Schema\Field\BooleanDateTime;

BooleanDateTime::make('isDeleted')
    ->property('deleted_at')
    ->writable();
```

## Filters

The Laravel integration provides a number of filters for use in your Eloquent
resources.

### Where

```php
Where::make('name');
Where::make('id')->commaSeparated();
Where::make('isConfirmed')->asBoolean();
Where::make('score')->asNumeric();
WhereBelongsTo::make('user');
Has::make('hasComments');
WhereHas::make('comments');
WhereDoesntHave::make('comments');
WhereNull::make('draft')->property('published_at');
WhereNotNull::make('published')->property('published_at');
Scope::make('withTrashed');
Scope::make('trashed')->scope('onlyTrashed');
```

## Sort Fields

The Laravel integration provides a number of sort fields for use in your
Eloquent resources.

### SortColumn

The `SortColumn` class adds a sort field that relates to a database column.

```php
use Tobyz\JsonApiServer\Laravel\Sort\SortColumn;

SortColumn::make('createdAt');
```

The argument provided to the `make` method is the JSON:API sort field name. The
database column is expected to be the underscored version of the field name – so
`created_at` in the above example. If you would like to use a different column,
specify it using the `column` method:

```php
SortColumn::make('createdAt')->column('created_at');
```

### SortWithCount

The `SortWithCount` class adds a sort field that relates to a relationship on
the resource's Eloquent model.

For example, to allow the client to sort resources by the number of `comments`
they have:

```php
use Tobyz\JsonApiServer\Laravel\Sort\SortWithCount;

SortWithCount::make('comments');
```

If you want the JSON:API sort field name to be different from the relationship
name, specify the relationship name using the `relationship` method:

```php
SortWithCount::make('comments')->relationship('approvedComments');
```

Laravel allows you to alias the relationship count, which is typically used when
there might be a collision with a column name or you are using multiple counts
for the same relationship. Use the `countAs` method if you need to alias the
count for the sort:

```php
SortWithCount::make('comments')->countAs('total_comments');
```

You can also constrain the count by providing a closure to the `scope` method.
This receives the query builder instance that is used for the count:

```php
SortWithCount::make('comments')->scope(
    fn($query) => $query->where('approved', true),
);
```

## Helpers

These helpers improve the ergonomics of your API resource definitions when using
Laravel.

### `authenticated`

The `authenticated` helper returns a closure which calls `Auth::check()`. This
can be used to make endpoints and fields only visible to authenticated users:

```php
use function Tobyz\JsonApiServer\Laravel\authenticated;

Create::make()->visible(authenticated());
```

### `can`

The `can` helper returns a closure which uses Laravel's
[Gate component](https://laravel.com/docs/10.x/authorization) to check if the
given ability is allowed. If this is used in the context of a specific model
(e.g. on fields and the `Show`, `Update`, `Delete` endpoints), then the model
will be passed to the gate check as well.

```php
use function Tobyz\JsonApiServer\Laravel\can;

Update::make()->visible(can('update'));
```

### `rules`

::: warning  
Before reaching for the `rules` helper, remember that you can mark fields as
[required](fields.md#required) and [nullable](fields.md#nullable), and make use
of the [built-in attribute types](attributes.md#typed-attributes) and their
options. These will be reflected in your OpenAPI definitions, whereas validation
rules will not.  
:::

The `rules` helper allows you to use Laravel's
[Validation component](https://laravel.com/docs/10.x/validation) as a
[field validator](fields.md#validation). Pass a string or array of validation
rules to be applied to the value:

```php
use function Tobyz\JsonApiServer\Laravel\rules;

Attribute::make('password')->validate(rules(['password']));
```

Note that values are validated one at a time, so interdependent rules such as
`required_if` will not work.

You can add an `{id}` placeholder to database rules such as `unique` which will
be substituted if a model is being updated:

```php
Attribute::make('email')
    ->type(Str::make()->format('email'))
    ->validate(Laravel\rules(['email', 'unique:users,email,{id}']));
```

Validating array contents is also supported:

```php
Attribute::make('jobs')->validate(
    Laravel\rules(['required', 'array', '*' => ['string', 'min:3', 'max:255']]),
);
```

You can also pass an array of custom messages and custom attribute names as the
second and third arguments.
