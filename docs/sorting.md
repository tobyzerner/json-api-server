# Sorting

You can define an attribute as `sortable` to allow the resource listing to be [sorted](https://jsonapi.org/format/#fetching-sorting) by the attribute's value.

```php
$type->attribute('firstName')
    ->sortable();
    
$type->attribute('lastName')
    ->sortable();
    
// GET /users?sort=lastName,firstName
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
