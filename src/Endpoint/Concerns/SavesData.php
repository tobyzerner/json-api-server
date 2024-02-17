<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ConflictException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

use function Tobyz\JsonApiServer\get_value;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\location;
use function Tobyz\JsonApiServer\set_value;

trait SavesData
{
    use FindsResources;

    /**
     * Parse and validate a JSON:API document's `data` member.
     *
     * @throws BadRequestException if the `data` member is invalid.
     */
    private function parseData(Context $context): array
    {
        $body = (array) $context->body();

        if (!isset($body['data']) || !is_array($body['data'])) {
            throw (new BadRequestException('data must be an object'))->setSource([
                'pointer' => '/data',
            ]);
        }

        if (!isset($body['data']['type'])) {
            throw (new BadRequestException('data.type must be present'))->setSource([
                'pointer' => '/data/type',
            ]);
        }

        if (isset($context->model)) {
            if (!isset($body['data']['id'])) {
                throw (new BadRequestException('data.id must be present'))->setSource([
                    'pointer' => '/data/id',
                ]);
            }

            if ($body['data']['id'] !== $context->resource->getId($context->model, $context)) {
                throw (new ConflictException('data.id does not match the resource ID'))->setSource([
                    'pointer' => '/data/id',
                ]);
            }
        } elseif (isset($body['data']['id'])) {
            throw (new ForbiddenException('Client-generated IDs are not supported'))->setSource([
                'pointer' => '/data/id',
            ]);
        }

        if (!in_array($body['data']['type'], $context->collection->resources())) {
            throw (new ConflictException(
                'collection does not support this resource type',
            ))->setSource(['pointer' => '/data/type']);
        }

        if (
            array_key_exists('attributes', $body['data']) &&
            !is_array($body['data']['attributes'])
        ) {
            throw (new BadRequestException('data.attributes must be an object'))->setSource([
                'pointer' => '/data/attributes',
            ]);
        }

        if (
            array_key_exists('relationships', $body['data']) &&
            !is_array($body['data']['relationships'])
        ) {
            throw (new BadRequestException('data.relationships must be an object'))->setSource([
                'pointer' => '/data/relationships',
            ]);
        }

        return array_merge(['attributes' => [], 'relationships' => []], $body['data']);
    }

    /**
     * Assert that the fields contained within a data object are valid.
     */
    private function assertFieldsValid(Context $context, array $data): void
    {
        $this->assertFieldsExist($context, $data);
        $this->assertFieldsWritable($context, $data);
    }

    /**
     * Assert that the fields contained within a data object exist in the schema.
     *
     * @throws BadRequestException if a field is unknown.
     */
    private function assertFieldsExist(Context $context, array $data): void
    {
        $fields = $context->fields($context->resource);

        foreach (['attributes', 'relationships'] as $location) {
            foreach ($data[$location] as $name => $value) {
                if (!isset($fields[$name]) || $location !== location($fields[$name])) {
                    throw (new BadRequestException("Unknown field [$name]"))->setSource([
                        'pointer' => "/data/$location/$name",
                    ]);
                }
            }
        }
    }

    /**
     * Assert that the fields contained within a data object are writable.
     *
     * @throws ForbiddenException if a field is not writable.
     */
    private function assertFieldsWritable(Context $context, array $data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field)) {
                continue;
            }

            if (!$field->isWritable($context->withField($field))) {
                throw (new ForbiddenException("Field [$field->name] is not writable"))->setSource([
                    'pointer' => '/data/' . location($field) . '/' . $field->name,
                ]);
            }
        }
    }

    /**
     *
     */
    private function deserializeValues(Context $context, array &$data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            try {
                set_value($data, $field, $field->deserializeValue($value, $context));
            } catch (Sourceable $e) {
                throw $e->prependSource([
                    'pointer' => '/data/' . location($field) . '/' . $field->name,
                ]);
            }
        }
    }

    /**
     * Assert that the field values within a data object pass validation.
     *
     * @throws UnprocessableEntityException if any fields do not pass validation.
     */
    private function assertDataValid(Context $context, array $data, bool $validateAll): void
    {
        $errors = [];

        foreach ($context->fields($context->resource) as $field) {
            $empty = !has_value($data, $field);

            if ($empty && (!$field->required || !$validateAll)) {
                continue;
            }

            $fail = function ($detail = null) use (&$errors, $field) {
                $errors[] = [
                    'source' => ['pointer' => '/data/' . location($field) . '/' . $field->name],
                    'detail' => $detail,
                ];
            };

            if ($empty && $field->required) {
                $fail('field is required');
            } else {
                $field->validateValue(get_value($data, $field), $fail, $context->withField($field));
            }
        }

        if (count($errors)) {
            throw new UnprocessableEntityException($errors);
        }
    }

    /**
     * Set field values from a data object to the model instance.
     */
    private function setValues(Context $context, array $data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            $field->setValue($context->model, $value, $context);
        }
    }

    /**
     * Run any field save callbacks.
     */
    private function saveFields(Context $context, array $data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field)) {
                continue;
            }

            $value = get_value($data, $field);

            $field->saveValue($context->model, $value, $context);
        }
    }
}
