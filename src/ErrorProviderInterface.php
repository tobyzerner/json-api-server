<?php

namespace Tobscure\JsonApiServer;

interface ErrorProviderInterface
{
    public function getJsonApiErrors(): array;
}
