<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Doctrine\Inflector\InflectorFactory;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Sourceable;
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
        return [
            'data' => $value
                ? $context->serializer->addIncluded(
                    $context->withField($this)->forModel($this->collections, $value),
                )
                : null,
        ];
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if ($this->deserializer) {
            return ($this->deserializer)($value, $context);
        }

        if (!is_array($value) || !array_key_exists('data', $value)) {
            throw new BadRequestException('relationship does not include data key');
        }

        if ($value['data'] === null) {
            return null;
        }

        try {
            return $this->resourceForIdentifier($value['data'], $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['pointer' => '/data']);
        }
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
