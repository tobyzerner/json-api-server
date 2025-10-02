<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Context;

interface ResourceEndpoint
{
    public function resourceLinks($model, Context $context): array;
}
