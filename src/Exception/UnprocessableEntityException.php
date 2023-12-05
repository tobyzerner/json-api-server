<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;

class UnprocessableEntityException extends DomainException implements ErrorProvider, Sourceable
{
    public function __construct(public array $errors)
    {
        parent::__construct(print_r($errors, true));
    }

    public function prependSource(array $source): static
    {
        foreach ($this->errors as &$error) {
            foreach ($source as $k => $v) {
                $error['source'][$k] = $v . ($error['source'][$k] ?? '');
            }
        }

        return $this;
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
