<?php

namespace Tobyz\Tests\JsonApiServer;

use Exception;
use Tobyz\JsonApiServer\Exception\Concerns\JsonApiError;
use Tobyz\JsonApiServer\Exception\ErrorProvider;

class MockErrorException extends Exception implements ErrorProvider
{
    use JsonApiError;

    public function __construct(private readonly string $status = '400')
    {
        parent::__construct('Mock error');
    }

    public function getJsonApiStatus(): string
    {
        return $this->status;
    }
}
