<?php

namespace Tobyz\JsonApiServer;

interface StatusProviderInterface
{
    public function getJsonApiStatus(): array;
}
