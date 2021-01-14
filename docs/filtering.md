# Filtering

You can define a field as `filterable` to allow the resource listing to be [filtered](https://jsonapi.org/recommendations/#filtering) by the field's value.

This works for both attributes and relationships:

```php
$type->attribute('firstName')
    ->filterable();
// GET /users?filter[firstName]=Toby

$type->hasMany('groups')
    ->filterable();
// GET /users?filter[groups]=1,2,3
```

The `>`, `>=`, `<`, `<=`, and `..` operators on attribute filter values are automatically parsed and applied, supporting queries like:

```
GET /users?filter[postCount]=>=10
GET /users?filter[postCount]=5..15
```

## Custom Filters

To define filters with custom logic, or ones that do not correspond to an attribute, use the `filter` method:

```php
$type->filter('minPosts', function ($query, $value, Context $context) {
    $query->where('postCount', '>=', $value);
});
```

Just like [fields](visibility.md), filters can be made conditionally `visible` or `hidden`:

```php
$type->filter('email', $callback)
    ->visible(function (Context $context) {
        return $context->getRequest()->getAttribute('isAdmin');
    });
```
