<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class BadRequestException extends DomainException implements ErrorProviderInterface
{
    public string $sourceType;
    public string $source;

    public function setSourceParameter(string $parameter): static
    {
        $this->sourceType = 'parameter';
        $this->source = $parameter;

        return $this;
    }

    public function setSourcePointer(string $pointer): static
    {
        $this->sourceType = 'pointer';
        $this->source = $pointer;

        return $this;
    }

    public function getJsonApiErrors(): array
    {
        $members = [];

        if ($this->message) {
            $members['detail'] = $this->message;
        }

        if ($this->sourceType === 'parameter') {
            $members['source']['parameter'] = $this->source;
        } elseif ($this->sourceType === 'pointer') {
            $members['source']['pointer'] = $this->source;
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
