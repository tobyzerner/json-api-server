<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Doctrine\Inflector\InflectorFactory;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;

class ToOne extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(
            InflectorFactory::create()
                ->build()
                ->pluralize($name),
        );

        $this->withLinkage();
    }

    protected function serializeData($value, Context $context): array
    {
        if (!$value) {
            return ['data' => null];
        }

        return ['data' => $this->serializeIdentifier($value, $context)];
    }

    public function deserializeData(mixed $data, Context $context): mixed
    {
        if ($data === null) {
            return null;
        }

        return $this->resourceForIdentifier($data, $context);
    }

    protected function getDataSchema(JsonApi $api): array
    {
        $linkage = [
            'allOf' => [
                ['$ref' => '#/components/schemas/jsonApiResourceIdentifier'],
                [
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => $this->getRelatedResources($api),
                        ],
                    ],
                ],
            ],
        ];

        return $this->nullable ? ['oneOf' => [$linkage, ['type' => 'null']]] : $linkage;
    }
}
