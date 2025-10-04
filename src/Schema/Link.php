<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Field;

class Link extends Field
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return null;
    }

    public function getSchema(JsonApi $api): array
    {
        return parent::getSchema($api) + [
            'oneOf' => [
                ['type' => 'string', 'format' => 'uri'],
                [
                    'type' => 'object',
                    'required' => ['href'],
                    'properties' => [
                        'href' => ['type' => 'string', 'format' => 'uri'],
                        'rel' => ['type' => 'string'],
                        'describedby' => [
                            'oneOf' => [
                                ['type' => 'string', 'format' => 'uri'],
                                [
                                    'type' => 'object',
                                    'required' => ['href'],
                                    'properties' => [
                                        'href' => ['type' => 'string', 'format' => 'uri'],
                                    ],
                                ],
                            ],
                        ],
                        'title' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'hreflang' => ['type' => 'string'],
                        'meta' => ['type' => 'object'],
                    ],
                ],
            ],
        ];
    }
}
