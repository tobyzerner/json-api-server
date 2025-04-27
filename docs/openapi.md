# OpenAPI Definitions

You can generate an OpenAPI 3.1.0 Definition for your API using the
`OpenApiGenerator` class.

```php
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;

$api = new JsonApi();

$definition = (new OpenApiGenerator())->generate($api);
```
