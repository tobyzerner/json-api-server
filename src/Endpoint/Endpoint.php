<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;

interface Endpoint
{
    public function handle(Context $context): ?Response;
}
