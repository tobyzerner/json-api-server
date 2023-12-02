# Deferred Values

To avoid the N+1 problem, you may wish to defer retrieving the value of a field
until after all other fields have been processed. For example, if a relationship
needs to be loaded from the database, it might be best to wait until you know
all the models that it needs to be loaded for so that it can be done in a single
database query.

In these cases, you can return a closure from your resource's `getValue` method,
or a field's getter, to be evaluated at the end of serialization:

```php
use Tobyz\JsonApiServer\Schema\Field\Relationship;

class PostsResource extends Resource
{
    // ...

    public function getValue(
        object $model,
        Field $field,
        Context $context,
    ): mixed {
        if ($field instanceof Relationship) {
            Buffer::add($model, $field);

            return fn() => Buffer::load($model, $field, $context);
        }

        return parent::getValue($model, $field, $context);
    }
}
```

In the above example, the exact implementation of `Buffer` is up to you. The
concept is that every post being serialized will be added to the buffer first;
then, when the first deferred value is evaluated, the buffer can load the
relationship for all of the buffered posts at once.
