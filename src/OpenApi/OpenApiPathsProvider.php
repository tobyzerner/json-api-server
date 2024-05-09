<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\Resource\Collection;

interface OpenApiPathsProvider
{
    public function getOpenApiPaths(Collection $collection): array;
}
