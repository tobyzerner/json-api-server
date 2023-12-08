# Relationships

Define
[relationship fields](https://jsonapi.org/format/#document-resource-object-relationships)
on your resource using the `ToOne` and `ToMany` fields.

```php
use Tobyz\JsonApiServer\Schema\Field\{ToMany, ToOne};

ToOne::make('user');
ToMany::make('comments');
```

## Resource Type

By default, the resource that the relationship corresponds to will be the
pluralized form of the relationship name. In the example above, the `user`
relationship would correspond to the `users` collection, while `comments` would
correspond to `comments`.

If you'd like to use a different resource, call the `type` method:

```php
ToOne::make('author')->type('people');
```

### Polymorphic Relationships

To define a polymorphic relationship, you will need to first create a
[heterogeneous collection](collections.md) to define the resource types that may
exist in the relationship, and logic for mapping models to their representative
resource types.

Once you have defined and registered the collection, you can use it for a
relationship by calling the `collection` method with the name of the collection:

```php
ToMany::make('activity')->collection('activity');
```

## Resource Linkage

By default, to-one relationships will have
[resource linkage](https://jsonapi.org/format/#document-resource-object-linkage),
but to-many relationships will not. You can toggle this by calling the
`withLinkage` or `withoutLinkage` methods.

```php
ToOne::make('user')->withoutLinkage();
ToMany::make('roles')->withLinkage();
```

::: danger  
Be careful when enabling linkage on to-many relationships as pagination is not
supported.  
:::

## Relationship Inclusion

To make a relationship available for
[inclusion](https://jsonapi.org/format/#fetching-includes) via the `include`
query parameter, call the `includable` method.

```php
ToOne::make('user')->includable();
ToMany::make('roles')->includable();
```

::: danger  
Be careful when making to-many relationships includable as pagination is not
supported.  
:::
