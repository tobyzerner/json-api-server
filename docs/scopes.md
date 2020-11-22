# Scopes

Restrict the visibility of resources, and make other query modifications, using the `scope` method.

This `scope` method allows you to modify the query builder object provided by the adapter. This is the perfect opportunity to apply conditions to the query to restrict which resources are visible in the API.

For example, to make it so the authenticated user can only see their own posts:

```php
$type->scope(function ($query, Context $context) {
    $query->where('user_id', $context->getRequest()->getAttribute('userId'));
});
```

A resource type's scope is global – it will also be applied when that resource is being [included](relationships) as a relationship.

You can define multiple scopes per resource type, and they will be applied in order.
