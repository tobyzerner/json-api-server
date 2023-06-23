<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Throwable;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class BadRequestException extends DomainException implements ErrorProviderInterface
{
    public function __construct(
        string $message = '',
        public ?array $source = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function setSource(?array $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function prependSource(array $source): static
    {
        foreach ($source as $k => $v) {
            $this->source = [$k => $v . ($this->source[$k] ?? '')];
        }

        return $this;
    }

    public function getJsonApiErrors(): array
    {
        $members = [];

        if ($this->message) {
            $members['detail'] = $this->message;
        }

        if ($this->source) {
            $members['source'] = $this->source;
        }

        return [
            [
                'title' => 'Bad Request',
                'status' => $this->getJsonApiStatus(),
                ...$members,
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '400';
    }
}
