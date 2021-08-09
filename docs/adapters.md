# Adapters

Adapters connect your API schema to your application's data persistence layer.

You'll need to supply an adapter for each [resource type](https://jsonapi.org/format/#document-resource-object-identification) you define. You can define resource types using the `resource` method. For example:

```php
use Tobyz\JsonApiServer\Schema\Type;

$api->resourceType('users', $adapter, function (Type $type) {
    // define your schema
});
```

### Eloquent Adapter

An `EloquentAdapter` is provided out of the box to hook your resources up with Laravel [Eloquent](https://laravel.com/docs/8.x/eloquent) models. Instantiate it with the model class that corresponds to your resource.

```php
use App\Models\User;
use Tobyz\JsonApiServer\Adapter\EloquentAdapter;

$adapter = new EloquentAdapter(User::class);
```

When using the Eloquent Adapter, the `$model` passed around in the schema will be an instance of the given model, and the `$query` will be a `Illuminate\Database\Eloquent\Builder` instance querying the model's table:

```php
$type->scope(function (Builder $query) {});

$type->attribute('name')
    ->get(function (User $user) {});
```

### Custom Adapters

For other ORMs or data persistence layers, you can [implement your own adapter](https://github.com/tobyzerner/json-api-server/blob/master/src/Adapter/AdapterInterface.php).
