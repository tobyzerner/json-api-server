<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Exception\ErrorProvider;

class MockException implements ErrorProvider
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
