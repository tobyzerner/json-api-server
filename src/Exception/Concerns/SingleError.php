<?php

namespace Tobyz\JsonApiServer\Exception\Concerns;

use ReflectionClass;

trait SingleError
{
    protected ?array $source = null;

    public function setSource(?array $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function prependSource(array $source): static
    {
        foreach ($source as $k => $v) {
            $this->source[$k] = $v . ($this->source[$k] ?? '');
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
                'status' => $this->getJsonApiStatus(),
                'title' => $this->getErrorTitle(),
                'detail' => $this->getMessage(),
                ...$members,
            ],
        ];
    }

    protected function getErrorTitle(): string
    {
        $class = (new ReflectionClass($this))->getShortName();
        $words = preg_split('/(?=[A-Z])/', $class);

        if (end($words) === 'Exception') {
            array_pop($words);
        }

        return trim(implode(' ', $words));
    }
}
