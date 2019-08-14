<?php

namespace Tobyz\JsonApiServer;

interface ErrorProviderInterface
{
    public function getJsonApiErrors(): array;

    public function getJsonApiStatus(): string;
}
