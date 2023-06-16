<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class UnprocessableEntityException extends DomainException implements ErrorProviderInterface
{
    public function __construct(public array $errors)
    {
        parent::__construct(print_r($errors, true));
    }

    public function getJsonApiErrors(): array
    {
        return array_map(
            fn(array $error) => ['status' => $this->getJsonApiStatus(), ...$error],
            $this->errors,
        );
    }

    public function getJsonApiStatus(): string
    {
        return '422';
    }
}
