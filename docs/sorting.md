# Sorting

The JSON:API specification reserves the `sort` query parameter for
[sorting resources](https://jsonapi.org/format/#fetching-sorting). Multiple sort
fields are comma-separated, and a `-` prefix indicates descending order. For
example, the following request would list posts sorted by `title` ascending,
then `createdAt` descending:

```http
GET /posts?sort=title,-createdAt
```

To define sort fields that can be used in this query parameter, add them to your
`Listable` resource's `sorts` method:

```php
class PostsResource extends Resource implements Listable
{
    // ...

    public function sorts(): array
    {
        return [
            ExampleSort::make('example'), // [!code ++]
        ];
    }
}
```

::: tip Laravel Integration  
For Eloquent-backed resources, a number of [sort fields](laravel.md#sorting) are
provided to make it easy to implement sorting on your resource.  
:::

## Inline Sort Fields

The easiest way to define a custom sort field is to use the `CustomSort` class,
which accepts the name of the sort field and a callback to apply the sort to the
query:

```php
use Tobyz\JsonApiServer\Schema\CustomSort;

CustomSort::make('example', function (
    $query,
    string $direction,
    Context $context,
) {
    $query->orderBy('example', $direction);
});
```

## Writing Sort Fields

To create your own sort field class, extend the
`Tobyz\JsonApiServer\Schema\Sort` class and implement the `apply` method:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Sort;

class SortColumn extends Sort
{
    public function apply(
        object $query,
        string $direction,
        Context $context,
    ): void {
        $query->orderBy($this->name, $direction);
    }
}
```

## Visibility

If you want to restrict the ability to use a sort field, use the `visible` or
`hidden` method, passing a closure that returns a boolean value:

```php
SortColumn::make('example')->visible(
    fn(Context $context) => $context->request->getAttribute('isAdmin'),
);
```

## Default Sort

You can set a default sort to be used when none is specified by the client. To
do this, override the `defaultSort` method on your resource:

```php
public function defaultSort(): ?string
{
    return '-createdAt,title';
}
```
