# Filtering

The JSON:API specification reserves the `filter` query parameter for
[filtering resources](https://jsonapi.org/format/#fetching-filtering).

To define filters that can be used in this query parameter, add them to your
`Listable` resource's `filters` method:

```php
class PostsResource extends Resource implements Listable
{
    // ...

    public function filters(): array
    {
        return [
            ExampleFilter::make('example'), // [!code ++]
        ];
    }
}
```

::: tip Laravel Integration  
For Eloquent-backed resources, a number of [filters](laravel.md#filters) are
provided to make it easy to implement filtering on your resource.  
:::

## Inline Filters

The easiest way to define a filter is to use the `CustomFilter` class, which
accepts the name of the filter parameter and a callback to apply the filter to
the query. Without a declared type, the value received by a filter is the raw
query string value: either a string or an array, depending on how the filter was
used in the URL:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\CustomFilter;

CustomFilter::make('name', function (
    $query,
    string|array $value,
    Context $context,
) {
    $query->whereIn('name', (array) $value);
});
```

Now the filter can be applied like so:

```http
GET /posts?filter[name]=Toby
GET /posts?filter[name][]=Toby&filter[name][]=Franz
```

### Typed Values

Filters receive raw query string values by default. If you want a filter to
receive a validated value, declare its type using the same type system used by
fields and parameters:

```php
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Type;

CustomFilter::make('published', function ($query, bool $value) {
    $query->where('published', $value);
})->type(Type\Boolean::make());

CustomFilter::make('ids', function ($query, array $ids) {
    $query->whereKey($ids);
})->type(Type\Arr::make()->items(Type\Integer::make()));
```

### Arrays

Array filters accept repeated query parameters, and scalar values are treated as
one-item arrays. If you want a comma-delimited string to be split into array
items, use `commaSeparated()` on the array type:

```php
CustomFilter::make('ids', function ($query, array $ids) {
    $query->whereKey($ids);
})->type(
    Type\Arr::make()
        ->items(Type\Integer::make())
        ->commaSeparated(),
);
```

Now all of these requests pass an integer array to the callback:

```http
GET /posts?filter[ids]=1
GET /posts?filter[ids]=1,2,3
GET /posts?filter[ids][]=1&filter[ids][]=2
```

### Operators

You may also opt into operator syntax:

```php
CustomFilter::make('views', function ($query, array $value) {
    foreach ($value as $operator => $views) {
        $query->where(
            'views',
            [
                'eq' => '=',
                'gt' => '>',
                'gte' => '>=',
                'lt' => '<',
                'lte' => '<=',
            ][$operator],
            $views,
        );
    }
})
    ->type(Type\Integer::make())
    ->operators(['eq', 'gt', 'gte', 'lt', 'lte']);
```

```http
GET /posts?filter[views]=100
GET /posts?filter[views][gt]=100
```

The value passed to the callback is an array keyed by operator. When no operator
is specified, the first configured operator is used.

If some operators accept a different payload type, pass the type keyed by
operator name:

```php
CustomFilter::make('createdAt', function ($query, array $value) {
    // ...
})
    ->type(Type\Date::make())
    ->operators([
        'eq',
        'lt',
        'gt',
        'null' => Type\Boolean::make(),
        'notnull' => Type\Boolean::make(),
    ]);
```

Operators without their own type continue to use the filter's base type.

## Writing Filters

If you need to reuse filter logic across resources, create your own filter class
by extending `Tobyz\JsonApiServer\Schema\Filter` and implementing the
`applyValue` method:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;
use Tobyz\JsonApiServer\Schema\Type;

class WhereIn extends Filter
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(Type\Arr::make()->items(Type\Str::make()));
    }

    protected function applyValue(
        object $query,
        mixed $value,
        Context $context,
    ): void {
        $query->whereIn($this->name, $value);
    }
}
```

## Boolean Groups

By default it is assumed that each filter applied to the query will be combined
with a logical `AND`. When a resource implements
`Tobyz\JsonApiServer\Resource\SupportsBooleanFilters` you can express more
complex logic with `AND`, `OR`, and `NOT` groups.

Boolean groups are expressed by nesting objects under the `filter` parameter.
You may use either associative objects or indexed lists of clauses. Each clause
can be another filter or another boolean group.

```http
GET /posts
  ?filter[and][0][status]=published
  &filter[and][1][or][0][views][gt]=100
  &filter[and][1][or][1][not][status]=archived
```

In this request every result must be published, and it must either have more
than 100 views or not be archived.

```http
GET /posts
  ?filter[or][0][status]=draft
  &filter[or][1][status]=published
  &filter[or][1][not][comments]=0
```

This request returns drafts, or posts that are published and have comments. The
second example also shows that in certain cases you can omit `[and]` groups and
numeric indices; sibling filters at the same level default to `AND` behaviour.

## Visibility

If you want to restrict the ability to use a filter, use the `visible` or
`hidden` method, passing a closure that returns a boolean value:

```php
WhereIn::make('example')->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```
