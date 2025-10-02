# Pagination

The JSON:API specification reserves the `page` query parameter for
[paginating collections](https://jsonapi.org/format/#fetching-pagination). The
specification is agnostic about the pagination strategy used by the server.

json-api-server supports both offset and cursor pagination strategies. Offset
pagination uses the `page[limit]` and `page[offset]` query parameters, while
cursor pagination follows the
[`ethanresnick/cursor-pagination` profile](https://jsonapi.org/profiles/ethanresnick/cursor-pagination/)
and relies on the `page[size]`, `page[after]`, and `page[before]` parameters.

## Offset Pagination

In order to use offset pagination for your resource listing, return an instance
of `Tobyz\JsonApiServer\Pagination\OffsetPagination` from the `pagination`
method of your resource:

```php
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;

class PostsResource extends Resource implements Listable
{
    // ...

    public function pagination(): ?Pagination
    {
        return new OffsetPagination();
    }
}
```

By default the page size is 20 with a maximum of 50. You can customise these
values by passing arguments:

```php
new OffsetPagination(defaultLimit: 10, maxLimit: 100);
```

You will also need to implement the `Tobyz\JsonApiServer\Resource\Paginatable`
interface on your resource and return a page of results:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Page;
use Tobyz\JsonApiServer\Resource\{Listable, Paginatable, AbstractResource};

class PostsResource extends AbstractResource implements Listable, Paginatable
{
    public function paginate(
        object $query,
        int $offset,
        int $limit,
        Context $context,
    ): Page {
        return new Page(
            results: $this->results(
                $query->offset($offset)->limit($limit + 1),
                $context,
            ),
            isLastPage: count($results) <= $limit,
        );
    }
}
```

## Cursor Pagination

Cursor pagination is enabled by returning an instance of
`Tobyz\JsonApiServer\Pagination\CursorPagination` from the `pagination` method
of your resource:

```php
use Tobyz\JsonApiServer\Pagination\CursorPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;

class PostsResource extends Resource implements Listable
{
    // ...

    public function pagination(): ?Pagination
    {
        return new CursorPagination();
    }
}
```

By default the page size is 20 with a maximum of 50. You can customise these
values by passing arguments:

```php
new CursorPagination(defaultLimit: 10, maxLimit: 100);
```

Cursor pagination requires implementing the
`Tobyz\JsonApiServer\Resource\CursorPaginatable` interface on the resource. The
`cursorPaginate` method must return page of results, while the `itemCursor`
method must return a cursor for the given model/query.

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\CursorPagination;
use Tobyz\JsonApiServer\Pagination\Page;
use Tobyz\JsonApiServer\Resource\CursorPaginatable;

class PostsResource extends AbstractResource implements
    Listable,
    CursorPaginatable
{
    public function cursorPaginate(
        object $query,
        int $size,
        ?string $after,
        ?string $before,
        Context $context,
    ): Page {
        // ...

        return new Page($results, $isFirstPage, $isLastPage);
    }

    public function itemCursor($model, object $query, Context $context): string
    {
        // ...
    }
}
```

## Countability

By default, offset pagination won't include a `last` link because there is no
way to know the total number of resources.

If you would like to include a `last` link, as well as include the total number
of resources as `meta` information, you can implement the
`Tobyz\JsonApiServer\Resource\Countable` interface on your resource:

```php
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\{
    Countable,
    Listable,
    Paginatable,
    AbstractResource,
};

class PostsResource extends AbstractResource implements
    Listable,
    Paginatable,
    Countable
{
    // ...

    public function count(object $query, Context $context): int
    {
        return $query->count();
    }
}
```
