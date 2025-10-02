<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Pagination\Pagination;

class ToMany extends Relationship
{
    public ?string $defaultSort = null;
    public ?Pagination $pagination = null;
    public bool $attachable = false;
    public array $attachValidators = [];
    public array $detachValidators = [];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type($name);
    }

    public function defaultSort(?string $defaultSort): static
    {
        $this->defaultSort = $defaultSort;

        return $this;
    }

    public function pagination(?Pagination $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function attachable(bool $attachable = true): static
    {
        $this->attachable = $attachable;

        return $this;
    }

    public function validateAttach(callable $validator): static
    {
        $this->attachValidators[] = $validator;

        return $this;
    }

    public function validateDetach(callable $validator): static
    {
        $this->detachValidators[] = $validator;

        return $this;
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if (!is_array($value) || !array_key_exists('data', $value)) {
            throw new BadRequestException('relationship does not include data key');
        }

        if (!array_is_list($value['data'])) {
            throw (new BadRequestException(
                'relationship data must be a list of identifier objects',
            ))->setSource(['pointer' => '/data']);
        }

        $models = [];

        foreach ($value['data'] as $i => $identifier) {
            try {
                $models[] = $this->resourceForIdentifier($identifier, $context);
            } catch (Sourceable $e) {
                throw $e->prependSource(['pointer' => "/data/$i"]);
            }
        }

        if ($this->deserializer) {
            return ($this->deserializer)($models, $context);
        }

        return $models;
    }

    protected function serializeData($value, Context $context): array
    {
        if ($value === null) {
            return [];
        }

        $context = $context->withField($this);

        return [
            'data' => array_map(fn($model) => $this->serializeIdentifier($model, $context), $value),
        ];
    }

    protected function getDataSchema(JsonApi $api): array
    {
        return [
            'type' => 'array',
            'items' => [
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
            ],
        ];
    }
}
