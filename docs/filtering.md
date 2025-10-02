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
the query. The value received by a filter can be a string or an array, so you
will need to handle both:

```php
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

## Boolean Filters

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

In this request every result must be published, and it must also either have
more than 100 views or it is not archived.

```http
GET /posts
  ?filter[or][0][status]=draft
  &filter[or][1][status]=published
  &filter[or][1][not][comments]=0
```

This request returns drafts, or posts that are published and have comments. The
second example also shows that in certain cases you can omit `[and]` groups and
numeric indices; sibling filters at the same level default to `AND` behaviour.

## Writing Filters

To create your own filter class, extend the `Tobyz\JsonApiServer\Schema\Filter`
class and implement the `apply` method:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereIn extends Filter
{
    public function apply(
        object $query,
        string|array $value,
        Context $context,
    ): void {
        $query->whereIn($this->name, $value);
    }
}
```

## Visibility

If you want to restrict the ability to use a filter, use the `visible` or
`hidden` method, passing a closure that returns a boolean value:

```php
WhereIn::make('example')->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```
