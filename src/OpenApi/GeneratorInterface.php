<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\JsonApi;

interface GeneratorInterface
{
    public function generate(JsonApi $api): array;
}
