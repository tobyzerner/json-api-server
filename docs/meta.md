# Meta Information

You can add meta information at various levels of the document using the `meta` method.

## Document Meta

To add meta information at the top-level of a document, you can call the `meta` method on the `Context` instance which is available inside any of your schema's callbacks.

For example, to add meta information to a resource listing, you might call this inside of an `onListed` listener:

```php
$type->onListed(function ($models, Context $context) {
    $context->meta('foo', 'bar');
});
```

## Resource Meta

To add meta information at the resource-level, call `meta` on the schema builder.

```php
$type->meta('updatedAt', function ($model, Context $context) {
    return $model->updated_at;
});
```

## Relationship Meta

Meta information can also be [added to relationships](relationships.md#meta-information).
