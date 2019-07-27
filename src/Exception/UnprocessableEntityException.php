<?php

namespace Tobscure\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobscure\JsonApiServer\ErrorProviderInterface;

class UnprocessableEntityException extends \DomainException implements ErrorProviderInterface
{
    private $failures;

    public function __construct(array $failures)
    {
        parent::__construct();

        $this->failures = $failures;
    }

    public function getJsonApiErrors(): array
    {
        return array_map(function ($failure) {
            return new Error(
                new Error\Status($this->getJsonApiStatus()),
                new Error\SourcePointer('/data/'.$failure['field']->location.'/'.$failure['field']->name),
                new Error\Detail($failure['message'])
            );
        }, $this->failures);
    }

    public function getJsonApiStatus(): string
    {
        return '422';
    }
}
