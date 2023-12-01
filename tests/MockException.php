<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Exception\ErrorProviderInterface;

class MockException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Mock Error',
                'status' => $this->getJsonApiStatus(),
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '400';
    }
}
