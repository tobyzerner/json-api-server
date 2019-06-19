<?php

namespace Tobscure\JsonApiServer;

interface StatusProviderInterface
{
    public function getJsonApiStatus(): array;
}
