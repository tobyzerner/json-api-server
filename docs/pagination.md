# Pagination

By default, resource listings are automatically [paginated](https://jsonapi.org/format/#fetching-pagination) with 20 records per page.

You can change this amount using the `paginate` method on the schema builder, or you can remove it by calling the `dontPaginate` method.

```php
$type->paginate(50); // default to listing 50 resources per page
$type->dontPaginate(); // default to listing all resources
```

Consumers may request a different limit using the `page[limit]` query parameter. By default the maximum possible limit is capped at 50; you can change this cap using the `limit` method, or you can remove it by calling the `noLimit` method:

```php
$type->limit(100); // set the maximum limit for resources per page to 100
$type->noLimit(); // remove the maximum limit for resources per page
```

## Countability

By default, a query will be performed to count the total number of resources in a collection. This will be used to populate a `total` attribute in the document's `meta` object, as well as the `last` pagination link.

For some types of resources, or when a query is resource-intensive (especially when certain filters or sorting is applied), it may be undesirable to have this happen. So it can be toggled using the `countable` and `uncountable` methods:

```php
$type->countable();
$type->uncountable();
```
