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

By default, the resource type that the relationship corresponds to will be the
pluralized form of the relationship name. In the example above, the `user`
relationship would correspond to the `users` resource type, while `comments`
would correspond to `comments`.

If you'd like to use a different resource type, call the `type` method:

```php
ToOne::make('author')->type('people');
```

### Polymorphic Relationships

You can define a polymorphic relationship by passing an array to the `type`
method containing a map of model classes to resource types:

```php
use App\Models\{Article, Post};

ToOne::make('commentable')->type([
    Article::class => 'articles',
    Post::class => 'posts',
]);
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
