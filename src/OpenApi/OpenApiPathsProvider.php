<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;

interface OpenApiPathsProvider
{
    public function getOpenApiPaths(Collection $collection): array;
}
