<?php

namespace Tobyz\JsonApiServer\Exception\Concerns;

use ReflectionClass;

trait JsonApiError
{
    public array $error = [];
    private array $sourcePath = [];

    public function __construct(array|string $message = '')
    {
        if (is_array($message)) {
            $this->error = $message;
            $message = $this->error['detail'] ?? '';
        }

        parent::__construct($message);
    }

    public function source(array $source): static
    {
        $this->sourcePath = [];
        $this->error['source'] = $source;

        return $this;
    }

    public function prependSourceParameter(string $parameter): static
    {
        if ($this->sourcePath) {
            $parameter .= implode(
                '',
                array_map(fn(int|string $segment) => '[' . $segment . ']', $this->sourcePath),
            );

            $this->sourcePath = [];
        }

        $this->error['source']['parameter'] =
            $parameter . ($this->error['source']['parameter'] ?? '');

        return $this;
    }

    public function prependSourcePointer(string $pointer): static
    {
        if ($this->sourcePath) {
            $pointer .= '/' . implode(
                '/',
                array_map(
                    fn(int|string $segment) => strtr((string) $segment, ['~' => '~0', '/' => '~1']),
                    $this->sourcePath,
                ),
            );

            $this->sourcePath = [];
        }

        $this->error['source']['pointer'] = $pointer . ($this->error['source']['pointer'] ?? '');

        return $this;
    }

    public function prependSourcePath(int|string ...$path): static
    {
        $this->sourcePath = [...$path, ...$this->sourcePath];

        return $this;
    }

    /** @deprecated Use prependSourcePath() and prependSourceParameter() or prependSourcePointer(). */
    public function prependSource(array $source): static
    {
        foreach ($source as $k => $v) {
            if ($k === 'parameter') {
                $this->prependSourceParameter($v);
            } elseif ($k === 'pointer') {
                $this->prependSourcePointer($v);
            } else {
                $this->error['source'][$k] = $v . ($this->error['source'][$k] ?? '');
            }
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

    public function id(string $id): static
    {
        $this->error['id'] = $id;

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
