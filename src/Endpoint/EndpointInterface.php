<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;

interface EndpointInterface
{
    public function handle(Context $context): ?Response;
}
