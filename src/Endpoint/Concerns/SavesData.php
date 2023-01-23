<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ConflictException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\get_value;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\run_callbacks;
use function Tobyz\JsonApiServer\set_value;

trait SavesData
{
    use FindsResources;

    /**
     * Parse and validate a JSON:API document's `data` member.
     *
     * @throws BadRequestException if the `data` member is invalid.
     */
    private function parseData(ResourceType $resourceType, $body, $model = null): array
    {
        $body = (array) $body;

        if (! isset($body['data']) || ! is_array($body['data'])) {
            throw new BadRequestException('data must be an object');
        }

        if (! isset($body['data']['type'])) {
            throw new BadRequestException('data.type must be present');
        }

        if ($model) {
            if (! isset($body['data']['id'])) {
                throw new BadRequestException('data.id must be present');
            }

            if ($body['data']['id'] !== $resourceType->getAdapter()->getId($model)) {
                throw new ConflictException('data.id does not match the resource ID');
            }
        } elseif (isset($body['data']['id'])) {
            throw new ForbiddenException('Client-generated IDs are not supported');
        }

        if ($body['data']['type'] !== $resourceType->getType()) {
            throw new ConflictException('data.type does not match the resource type');
        }

        if (isset($body['data']['attributes']) && ! is_array($body['data']['attributes'])) {
            throw new BadRequestException('data.attributes must be an object');
        }

        if (isset($body['data']['relationships']) && ! is_array($body['data']['relationships'])) {
            throw new BadRequestException('data.relationships must be an object');
        }

        return array_merge(
            ['attributes' => [], 'relationships' => []],
            $body['data']
        );
    }

    /**
     * Get the model corresponding to the given identifier.
     *
     * @throws BadRequestException if the identifier is invalid.
     */
    private function getModelForIdentifier(Context $context, array $identifier, array $validTypes = null)
    {
        if (! isset($identifier['type'])) {
            throw new BadRequestException('type not specified');
        }

        if (! isset($identifier['id'])) {
            throw new BadRequestException('id not specified');
        }

        if ($validTypes !== null && count($validTypes) && ! in_array($identifier['type'], $validTypes)) {
            throw new BadRequestException("type [{$identifier['type']}] not allowed");
        }

        $resourceType = $context->getApi()->getResourceType($identifier['type']);

        return $this->findResource($resourceType, $identifier['id'], $context);
    }

    /**
     * Assert that the fields contained within a data object are valid.
     */
    private function validateFields(ResourceType $resourceType, array $data, $model, Context $context)
    {
        $this->assertFieldsExist($resourceType, $data);
        $this->assertFieldsWritable($resourceType, $data, $model, $context);
    }

    /**
     * Assert that the fields contained within a data object exist in the schema.
     *
     * @throws BadRequestException if a field is unknown.
     */
    private function assertFieldsExist(ResourceType $resourceType, array $data)
    {
        $fields = $resourceType->getSchema()->getFields();

        foreach (['attributes', 'relationships'] as $location) {
            foreach ($data[$location] as $name => $value) {
                if (! isset($fields[$name]) || $location !== $fields[$name]->getLocation()) {
                    throw new BadRequestException("Unknown field [$name]");
                }
            }
        }
    }

    /**
     * Assert that the fields contained within a data object are writable.
     *
     * @throws BadRequestException if a field is not writable.
     */
    private function assertFieldsWritable(ResourceType $resourceType, array $data, $model, Context $context)
    {
        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            if (
                ! evaluate($field->getWritable(), [$model, $context])
                || (
                    $context->getRequest()->getMethod() !== 'POST'
                    && $field->isWritableOnce()
                )
            ) {
                throw new BadRequestException("Field [{$field->getName()}] is not writable");
            }
        }
    }

    /**
     * Replace relationship linkage within a data object with models.
     */
    private function loadRelatedResources(ResourceType $resourceType, array &$data, Context $context)
    {
        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! $field instanceof Relationship || ! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if (! array_key_exists('data', $value)) {
                throw new BadRequestException('relationship does not include data key');
            }

            if ($value['data'] !== null) {
                $allowedTypes = (array) $field->getType();

                if ($field instanceof HasOne) {
                    set_value($data, $field, $this->getModelForIdentifier($context, $value['data'], $allowedTypes));
                } elseif ($field instanceof HasMany) {
                    set_value($data, $field, array_map(function ($identifier) use ($context, $allowedTypes) {
                        return $this->getModelForIdentifier($context, $identifier, $allowedTypes);
                    }, $value['data']));
                }
            } else {
                set_value($data, $field, null);
            }
        }
    }

    /**
     * Assert that the field values within a data object pass validation.
     *
     * @throws UnprocessableEntityException if any fields do not pass validation.
     */
    private function assertDataValid(ResourceType $resourceType, array $data, $model, Context $context, bool $validateAll): void
    {
        $failures = [];

        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! $validateAll && ! has_value($data, $field)) {
                continue;
            }

            $fail = function ($message = null) use (&$failures, $field) {
                $failures[] = compact('field', 'message');
            };

            run_callbacks(
                $field->getListeners('validate'),
                [$fail, get_value($data, $field), $model, $context, $field]
            );
        }

        if (count($failures)) {
            throw new UnprocessableEntityException($failures);
        }
    }

    /**
     * Set field values from a data object to the model instance.
     */
    private function setValues(ResourceType $resourceType, array $data, $model, Context $context)
    {
        $adapter = $resourceType->getAdapter();

        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if ($setCallback = $field->getSetCallback()) {
                $setCallback($model, $value, $context);
                continue;
            }

            if ($field->getSaveCallback()) {
                continue;
            }

            if ($field instanceof Attribute) {
                $adapter->setAttribute($model, $field, $value);
            } elseif ($field instanceof HasOne) {
                $adapter->setHasOne($model, $field, $value);
            }
        }
    }

    /**
     * Save the model and its fields.
     */
    private function save(ResourceType $resourceType, array $data, $model, Context $context)
    {
        $this->saveModel($resourceType, $model, $context);
        $this->saveFields($resourceType, $data, $model, $context);
    }

    /**
     * Save the model.
     */
    private function saveModel(ResourceType $resourceType, $model, Context $context)
    {
        if ($saveCallback = $resourceType->getSchema()->getSaveCallback()) {
            $saveCallback($model, $context);
        } else {
            $resourceType->getAdapter()->save($model);
        }
    }

    /**
     * Save any fields that were not saved with the model.
     */
    private function saveFields(ResourceType $resourceType, array $data, $model, Context $context)
    {
        $adapter = $resourceType->getAdapter();

        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            if ($saveCallback = $field->getSaveCallback()) {
                $saveCallback($model, $value, $context);
            } elseif ($field instanceof HasMany) {
                $adapter->saveHasMany($model, $field, $value);
            }
        }

        $this->runSavedCallbacks($resourceType, $data, $model, $context);
    }

    /**
     * Run field saved listeners.
     */
    private function runSavedCallbacks(ResourceType $resourceType, array $data, $model, Context $context)
    {

        foreach ($resourceType->getSchema()->getFields() as $field) {
            if (! has_value($data, $field)) {
                continue;
            }

            run_callbacks(
                $field->getListeners('saved'),
                [$model, get_value($data, $field), $context]
            );
        }
    }

    /**
     * Get a fresh copy of the model for display.
     */
    private function freshModel(ResourceType $resourceType, $model, Context $context)
    {
        $id = $resourceType->getAdapter()->getId($model);

        return $this->findResource($resourceType, $id, $context);
    }
}
