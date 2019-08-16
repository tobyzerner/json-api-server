<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use JsonApiPhp\JsonApi\Error;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class UnprocessableEntityException extends DomainException implements ErrorProviderInterface
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
            $members = [
                new Error\Status($this->getJsonApiStatus()),
            ];

            if ($field = $failure['field']) {
                $members[] = new Error\SourcePointer('/data/'.$field->getLocation().'/'.$field->getName());
            }

            if ($failure['message']) {
                $members[] = new Error\Detail($failure['message']);
            }

            return new Error(...$members);
        }, $this->failures);
    }

    public function getJsonApiStatus(): string
    {
        return '422';
    }
}
