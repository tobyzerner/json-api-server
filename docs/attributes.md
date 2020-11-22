# Attributes

Define an [attribute field](https://jsonapi.org/format/#document-resource-object-attributes) on your resource using the `attribute` method.

```php
$type->attribute('firstName');
```

By default, the attribute will read and write to the property on your model with the same name. (The Eloquent adapter will `snake_case` it automatically for you.) If you'd like it to correspond to a different property, use the `property` method:

```php
$type->attribute('firstName')
    ->property('fname');
```

## Getters

Use the `get` method to define custom retrieval logic for your attribute, instead of just reading the value straight from the model property.

```php
use Tobyz\JsonApiServer\Context;

$type->attribute('firstName')
    ->get(function ($model, Context $context) {
        return ucfirst($model->first_name);
    });
```

::: tip
If you're using Eloquent, you could also define attribute [casts](https://laravel.com/docs/8.x/eloquent-mutators#attribute-casting) or [accessors](https://laravel.com/docs/8.x/eloquent-mutators#defining-an-accessor) on your model to achieve a similar thing. However, the Request instance will not be available in this context.
:::
