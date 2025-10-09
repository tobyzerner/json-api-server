<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Data\IdConflictException;
use Tobyz\JsonApiServer\Exception\Data\InvalidAttributesException;
use Tobyz\JsonApiServer\Exception\Data\InvalidDataException;
use Tobyz\JsonApiServer\Exception\Data\InvalidIdException;
use Tobyz\JsonApiServer\Exception\Data\InvalidTypeException;
use Tobyz\JsonApiServer\Exception\Data\UnsupportedTypeException;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Field\InvalidFieldValueException;
use Tobyz\JsonApiServer\Exception\Field\ReadOnlyFieldException;
use Tobyz\JsonApiServer\Exception\Field\RequiredFieldException;
use Tobyz\JsonApiServer\Exception\Field\UnknownFieldException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\Relationship\InvalidRelationshipsException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Id;

use function Tobyz\JsonApiServer\field_path;
use function Tobyz\JsonApiServer\get_value;
use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\set_value;

trait MutatesResource
{
    use FindsResources;

    /**
     * Parse and validate a JSON:API document's `data` member.
     */
    private function parseData(Context $context): array
    {
        $body = (array) $context->body();

        if (!is_array($body['data'] ?? null)) {
            throw (new InvalidDataException())->source([
                'pointer' => array_key_exists('data', $body) ? '/data' : '',
            ]);
        }

        if (!is_string($body['data']['type'] ?? null)) {
            throw (new InvalidTypeException())->source(['pointer' => '/data/type']);
        }

        if (isset($context->model)) {
            if (!is_string($body['data']['id'] ?? null)) {
                throw (new InvalidIdException())->source(['pointer' => '/data/id']);
            }

            if ($body['data']['id'] !== $context->id($context->resource, $context->model)) {
                throw (new IdConflictException())->source(['pointer' => '/data/id']);
            }
        }

        if (!in_array($body['data']['type'], $context->collection->resources())) {
            throw (new UnsupportedTypeException($body['data']['type']))->source([
                'pointer' => '/data/type',
            ]);
        }

        if (
            array_key_exists('attributes', $body['data']) &&
            !is_array($body['data']['attributes'])
        ) {
            throw (new InvalidAttributesException())->source(['pointer' => '/data/attributes']);
        }

        if (
            array_key_exists('relationships', $body['data']) &&
            !is_array($body['data']['relationships'])
        ) {
            throw (new InvalidRelationshipsException())->source([
                'pointer' => '/data/relationships',
            ]);
        }

        return array_merge(['attributes' => [], 'relationships' => []], $body['data']);
    }

    private function getFields(Context $context, bool $creating = false): array
    {
        $fields = $context->fields($context->resource);

        if ($creating) {
            array_unshift($fields, $context->resource->id());
        }

        return $fields;
    }

    /**
     * Assert that the fields contained within a data object are valid.
     */
    private function assertFieldsValid(Context $context, bool $creating = false): void
    {
        $this->assertFieldsExist($context);
        $this->assertFieldsWritable($context, $creating);
    }

    /**
     * Assert that the fields contained within a data object exist in the schema.
     */
    private function assertFieldsExist(Context $context): void
    {
        $fields = $this->getFields($context);

        foreach (['attributes', 'relationships'] as $location) {
            foreach ($context->data[$location] as $name => $value) {
                if (!isset($fields[$name]) || $location !== $fields[$name]->location()) {
                    throw (new UnknownFieldException($name))->source([
                        'pointer' => '/data/' . implode('/', array_filter([$location, $name])),
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
    private function assertFieldsWritable(Context $context, bool $creating = false): void
    {
        foreach ($this->getFields($context, $creating) as $field) {
            if (!has_value($context->data, $field)) {
                continue;
            }

            try {
                $this->assertFieldWritable($context, $field, $creating);
            } catch (Sourceable $e) {
                throw $e->prependSource(['pointer' => '/data' . field_path($field)]);
            }
        }
    }

    private function assertFieldWritable(
        Context $context,
        Field $field,
        bool $creating = false,
    ): void {
        $context = $context->withField($field);

        $writable = $creating ? $field->isWritableOnCreate($context) : $field->isWritable($context);

        if (!$writable) {
            throw new ReadOnlyFieldException();
        }
    }

    /**
     *
     */
    private function deserializeValues(Context $context, bool $creating = false): void
    {
        foreach ($this->getFields($context, $creating) as $field) {
            if (!has_value($context->data, $field)) {
                continue;
            }

            $value = get_value($context->data, $field);

            try {
                set_value($context->data, $field, $field->deserializeValue($value, $context));
            } catch (Sourceable $e) {
                throw $e->prependSource(['pointer' => '/data' . field_path($field)]);
            }
        }
    }

    /**
     * Assert that the field values within a data object pass validation.
     *
     * @throws JsonApiErrorsException if any fields do not pass validation.
     */
    private function assertDataValid(Context $context, bool $validateAll): void
    {
        $errors = [];

        foreach ($this->getFields($context, $validateAll) as $field) {
            $present = has_value($context->data, $field);

            if (!$present && (!$field->required || !$validateAll)) {
                continue;
            }

            if (!$present && $field->required) {
                $errors[] = (new RequiredFieldException())->source([
                    'pointer' => '/data' . field_path($field),
                ]);
            } else {
                array_push(
                    $errors,
                    ...$this->validateField($context, $field, get_value($context->data, $field)),
                );
            }
        }

        if ($errors) {
            throw new JsonApiErrorsException($errors);
        }
    }

    private function validateField(Context $context, Field|Id $field, mixed $value): array
    {
        $errors = [];

        $fail = function ($error = []) use (&$errors, $field) {
            if (!$error instanceof ErrorProvider) {
                $error = new InvalidFieldValueException(
                    is_scalar($error) ? ['detail' => (string) $error] : $error,
                );
            }

            $errors[] = $error->source(['pointer' => '/data' . field_path($field)]);
        };

        $field->validateValue($value, $fail, $context->withField($field));

        return $errors;
    }

    /**
     * Set field values from a data object to the model instance.
     */
    private function setValues(Context $context, bool $creating = false): void
    {
        foreach ($this->getFields($context, $creating) as $field) {
            if (!has_value($context->data, $field)) {
                continue;
            }

            $value = get_value($context->data, $field);

            $field->setValue($context->model, $value, $context);
        }
    }

    /**
     * Run any field save callbacks.
     */
    private function saveFields(Context $context, bool $creating = false): void
    {
        foreach ($this->getFields($context, $creating) as $field) {
            if (!has_value($context->data, $field)) {
                continue;
            }

            $value = get_value($context->data, $field);

            $field->saveValue($context->model, $value, $context);
        }
    }
}
