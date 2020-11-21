# Meta Information

You can add meta information at various levels of the document using the `meta` method.

## Document Meta

To add meta information at the top-level, call `meta` on the `JsonApi` instance:

```php
$api->meta('requestTime', function (Request $request) {
    return new DateTime;
});
```

## Resource Meta

To add meta information at the resource-level, call `meta` on the schema builder.

```php
$type->meta('updatedAt', function ($model, Request $request) {
    return $model->updated_at;
});
```

## Relationship Meta

Meta information can also be [added to relationships](relationships.md#meta-information).
