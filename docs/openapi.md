# OpenAPI Definitions

You can generate an OpenAPI 3.1.0 Definition for your API using the
`OpenApiGenerator` class.

```php
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;

$api = new JsonApi();

$definition = (new OpenApiGenerator())->generate($api);
```

## Schemas

The OpenAPI generator creates three schemas for each resource:

- `{type}` - The full resource schema including all fields
- `{type}_create` - Schema for creating resources (includes only fields that are
  writable on creation)
- `{type}_update` - Schema for updating resources (includes only writable
  fields)
