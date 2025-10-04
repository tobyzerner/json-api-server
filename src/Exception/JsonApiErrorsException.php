<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;

class JsonApiErrorsException extends DomainException implements Sourceable
{
    public function __construct(public array $errors)
    {
        parent::__construct('Multiple errors occurred');

        foreach ($this->errors as $error) {
            if (!$error instanceof ErrorProvider) {
                $error = new InternalServerErrorException();
            }
        }
    }

    public function prependSource(array $source): static
    {
        foreach ($this->errors as $error) {
            if ($error instanceof Sourceable) {
                $error->prependSource($source);
            }
        }

        return $this;
    }

    public function getJsonApiStatus(): string
    {
        $statuses = array_map(fn($error) => $error->getJsonApiStatus(), $this->errors);

        if (!$statuses) {
            return '500';
        }

        if (count(array_unique($statuses)) === 1) {
            return $statuses[0];
        }

        $clientErrors = count(array_filter($statuses, fn($s) => $s[0] === '4'));

        return $clientErrors >= count($statuses) / 2 ? '400' : '500';
    }
}
