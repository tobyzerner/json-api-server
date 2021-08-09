# Relationships

Define [relationship fields](https://jsonapi.org/format/#document-resource-object-relationships) on your resource using the `hasOne` and `hasMany` methods.

```php
$type->hasOne('user');
$type->hasMany('comments');
```

By default, the resource type that the relationship corresponds to will be the pluralized form of the relationship name. In the example above, the `user` relationship would correspond to the `users` resource type, while `comments` would correspond to `comments`. If you'd like to use a different resource type, call the `type` method:

```php
$type->hasOne('author')
    ->type('people');
```

By default, the relationship will read and write to the relation on your model with the same name. If you'd like it to correspond to a different relation, use the `property` method:

```php
$type->hasOne('author')
    ->property('user');
```

## Resource Linkage

By default, to-one relationships will have [resource linkage](https://jsonapi.org/format/#document-resource-object-linkage), but to-many relationships will not. You can toggle this by calling the `withLinkage` or `withoutLinkage` methods.

```php
$type->hasMany('users')
    ->withLinkage();
```

::: danger
Be careful when enabling linkage on to-many relationships as pagination is not supported.
:::

## Relationship Inclusion

To make a relationship available for [inclusion](https://jsonapi.org/format/#fetching-includes) via the `include` query parameter, call the `includable` method.

```php
$type->hasOne('user')
    ->includable();
```

::: danger
Be careful when making to-many relationships includable as pagination is not supported.
:::

Relationships included via the `include` query parameter are automatically [eager-loaded](https://laravel.com/docs/8.x/eloquent-relationships#eager-loading) by the adapter, and any type [scopes](scopes) are applied automatically. You can also apply additional scopes at the relationship level using the `scope` method:

```php
use Tobyz\JsonApiServer\Context;

$type->hasOne('users')
    ->includable()
    ->scope(function ($query, Context $context) {
        $query->where('is_listed', true);
    });
```

## Polymorphic Relationships

Define a polymorphic relationship using the `polymorphic` method. Optionally you may provide an array of allowed resource types:

```php
$type->hasOne('commentable')
    ->polymorphic();

$type->hasMany('taggable')
    ->polymorphic(['photos', 'videos']);
```

## Meta Information

You can add meta information to a relationship using the `meta` method:

```php
$type->hasOne('user')
    ->meta('updatedAt', function ($model, $user, Context $context) {
        return $user->updated_at;
    });
```
