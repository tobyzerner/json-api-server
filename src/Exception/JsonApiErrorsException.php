<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;

class JsonApiErrorsException extends DomainException implements Sourceable
{
    public function __construct(public array $errors)
    {
        parent::__construct('Multiple errors occurred');

        foreach ($this->errors as &$error) {
            if (!$error instanceof ErrorProvider) {
                $error = new InternalServerErrorException();
            }
        }
    }

    public function prependSourcePath(int|string ...$path): static
    {
        return $this->eachSourceable(fn(Sourceable $error) => $error->prependSourcePath(...$path));
    }

    public function prependSourceParameter(string $parameter): static
    {
        return $this->eachSourceable(
            fn(Sourceable $error) => $error->prependSourceParameter($parameter),
        );
    }

    public function prependSourcePointer(string $pointer): static
    {
        return $this->eachSourceable(
            fn(Sourceable $error) => $error->prependSourcePointer($pointer),
        );
    }

    /** @deprecated Use prependSourcePath() and prependSourceParameter() or prependSourcePointer(). */
    public function prependSource(array $source): static
    {
        return $this->eachSourceable(fn(Sourceable $error) => $error->prependSource($source));
    }

    private function eachSourceable(callable $callback): static
    {
        foreach ($this->errors as $error) {
            if ($error instanceof Sourceable) {
                $callback($error);
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
