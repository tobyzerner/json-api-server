<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Relationship\InvalidRelationshipDataException;
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

    public function deserializeData(mixed $data, Context $context): array
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidRelationshipDataException();
        }

        $models = [];

        foreach ($data as $i => $identifier) {
            try {
                $models[] = $this->resourceForIdentifier($identifier, $context);
            } catch (Sourceable $e) {
                throw $e->prependSource(['pointer' => "/$i"]);
            }
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
