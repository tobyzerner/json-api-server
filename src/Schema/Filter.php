<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Filter\UnsupportedFilterOperatorException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

abstract class Filter
{
    use HasSchema;
    use HasVisibility;

    protected ?Type\Type $type = null;
    protected ?array $operators = null;

    public function __construct(public string $name)
    {
    }

    public function type(Type\Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function operators(array $operators): static
    {
        $this->operators = $operators;

        return $this;
    }

    public function apply(object $query, string|array $value, Context $context): void
    {
        $this->applyValue($query, $this->normalizeValue($value), $context);
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($this->operators) {
            return $this->normalizeOperatorValue($value, $this->operators);
        }

        return $this->normalizeTypedValue($value);
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        throw new \LogicException(
            sprintf('%s must implement applyValue() or override apply().', static::class),
        );
    }

    public function getSchema(): array
    {
        $valueSchema = $this->type?->schema() ?: [];
        $operators = $this->operators;

        if (!$operators) {
            return $this->mergeSchema($valueSchema);
        }

        $operatorProperties = [];

        $defaultValueSchema = null;

        foreach ($operators as $operator) {
            $payloadSchema = $this->operatorPayloadSchema($operator, $valueSchema);

            $defaultValueSchema ??= $payloadSchema;
            $operatorProperties[$operator] = $payloadSchema === [] ? (object) [] : $payloadSchema;
        }

        $operatorSchema = [
            'type' => 'object',
            'properties' => $operatorProperties,
            'minProperties' => 1,
            'additionalProperties' => false,
        ];

        return $this->mergeSchema([
            'oneOf' => [
                $this->operatorDefaultValueSchema($defaultValueSchema, $operatorSchema),
                $operatorSchema,
            ],
            'x-jsonapi-filter-operators' => $operators,
        ]);
    }

    protected function operatorDefaultValueSchema(
        array $defaultValueSchema,
        array $operatorSchema,
    ): array {
        if ($defaultValueSchema === []) {
            return ['not' => ['type' => 'object']];
        }

        if (($defaultValueSchema['type'] ?? null) === 'object') {
            return [
                'allOf' => [$defaultValueSchema, ['not' => $operatorSchema]],
            ];
        }

        return $defaultValueSchema;
    }

    private function operatorPayloadSchema(string $operator, array $valueSchema): array
    {
        $type = $this->operatorPayloadType($operator);

        return $type?->schema() ?? $valueSchema;
    }

    private function normalizeOperatorValue(mixed $value, array $operators): array
    {
        $default = $operators[0];

        if (!$this->isOperatorValue($value, $operators)) {
            return [$default => $this->normalizeOperatorPayload($default, $value)];
        }

        $result = [];

        foreach ($value as $operator => $payload) {
            if (!in_array($operator, $operators, true)) {
                throw (new UnsupportedFilterOperatorException($operator))->prependSourcePath(
                    $operator,
                );
            }

            try {
                $result[$operator] = $this->normalizeOperatorPayload($operator, $payload);
            } catch (Sourceable $e) {
                throw $e->prependSourcePath($operator);
            }
        }

        return $result;
    }

    protected function normalizeOperatorPayload(string $operator, mixed $payload): mixed
    {
        $type = $this->operatorPayloadType($operator);

        return $this->normalizeTypedValue($payload, $type);
    }

    protected function operatorPayloadType(string $operator): ?Type\Type
    {
        return $this->type;
    }

    protected function isOperatorValue(mixed $value, array $operators): bool
    {
        if (!is_array($value) || array_is_list($value)) {
            return false;
        }

        // Object filters may receive property names at the operator level; those belong
        // to the default operator payload unless all keys are known operators.
        if ($this->type instanceof Type\Obj) {
            return array_diff(array_keys($value), $operators) === [];
        }

        return true;
    }

    protected function normalizeTypedValue(mixed $value, ?Type\Type $type = null): mixed
    {
        $type ??= $this->type;

        if (!$type) {
            return $value;
        }

        $value = $type->deserializeQueryValue($value);

        $errors = [];

        $type->validate($value, function ($error) use (&$errors) {
            if (!$error instanceof ErrorProvider) {
                $error = new TypeMismatchException('valid filter value');
            }

            $errors[] = $error;
        });

        if ($errors) {
            throw new JsonApiErrorsException($errors);
        }

        return $value;
    }
}
