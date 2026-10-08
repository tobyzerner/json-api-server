<?php

namespace Tobyz\JsonApiServer\Extension\Relfield;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Extension\Hook\SparseFields;
use Tobyz\JsonApiServer\Extension\Relfield\Exception\RelfieldFieldsConflictException;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;

/**
 * Sparse fieldsets relative to the default fieldset.
 *
 * @see https://github.com/ThorstenSuckow/relfield
 */
final class Relfield extends Extension
{
    public const URI = 'https://conjoon.org/json-api/ext/relfield';
    public const NAMESPACE = 'relfield';
    public const PARAMETER = self::NAMESPACE . ':fields';

    public function uri(): string
    {
        return self::URI;
    }

    public function namespace(): string
    {
        return self::NAMESPACE;
    }

    public function hooks(): array
    {
        return [
            SparseFields::make($this->sparseFields(...))->parameters([
                Parameter::make(self::PARAMETER)
                    ->description(
                        'Comma-separated sparse fieldsets keyed by type, relative to the default fieldset',
                    )
                    ->type(Type\Obj::make()->additionalProperties(Type\Str::make()))
                    ->validate(function (array $value, callable $fail, Context $context) {
                        // Read the raw value so the check doesn't depend on
                        // whether `fields` has been loaded yet.
                        $fields = $context->request->getQueryParams()['fields'] ?? null;

                        if (!is_array($fields)) {
                            return;
                        }

                        foreach (array_keys(array_intersect_key($value, $fields)) as $type) {
                            $fail(
                                (new RelfieldFieldsConflictException())->prependSourcePath($type),
                            );
                        }
                    }),
            ]),
        ];
    }

    private function sparseFields(Resource $resource, Context $context): ?array
    {
        $value = $context->parameter(self::PARAMETER)[$resource->type()] ?? null;

        if ($value === null) {
            return null;
        }

        $names = array_keys($context->defaultFields($resource));

        foreach (explode(',', $value) as $name) {
            if (str_starts_with($name, '-')) {
                $names = array_diff($names, [substr($name, 1)]);
            } else {
                $names[] = $name;
            }
        }

        return $names;
    }
}
