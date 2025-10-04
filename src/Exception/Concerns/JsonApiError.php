<?php

namespace Tobyz\JsonApiServer\Exception\Concerns;

use ReflectionClass;

trait JsonApiError
{
    public array $error = [];

    public function source(array $source): static
    {
        $this->error['source'] = $source;

        return $this;
    }

    public function prependSource(array $source): static
    {
        foreach ($source as $k => $v) {
            $this->error['source'][$k] = $v . ($this->error['source'][$k] ?? '');
        }

        return $this;
    }

    public function meta(array $meta): static
    {
        $this->error['meta'] = array_merge($this->error['meta'] ?? [], $meta);

        return $this;
    }

    public function links(array $links): static
    {
        $this->error['links'] = array_merge($this->error['links'] ?? [], $links);

        return $this;
    }

    public function getJsonApiError(): array
    {
        $class = (new ReflectionClass($this))->getShortName();
        $words = preg_split('/(?=[A-Z])/', $class);

        if (end($words) === 'Exception') {
            array_pop($words);
        }

        $defaults = [
            'status' => $this->getJsonApiStatus(),
            'code' => strtolower(implode('_', array_filter($words))),
            'title' => trim(implode(' ', $words)),
        ];

        if (!empty($this->message)) {
            $defaults['detail'] = $this->message;
        }

        return $this->error + $defaults;
    }
}
