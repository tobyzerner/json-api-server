<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Extension\Hook\HookParameters;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\JsonApiServer\SchemaContext;

trait BuildsOpenApiPaths
{
    /**
     * @param (Parameter|HookParameters)[] $parameters
     */
    private function openApiParameters(SchemaContext $context, array $parameters): array
    {
        return array_map(
            function (array $resolved) use ($context) {
                [$parameter, $uri] = $resolved;
                $schema = $parameter->getSchema($context);

                if ($uri) {
                    $schema['description'] = ltrim(
                        ($schema['description'] ?? '') . " Requires the $uri extension.",
                    );
                }

                return $schema;
            },
            $context->api->resolveParameters($parameters, $context->api->extensions),
        );
    }

    /**
     * @param Parameter[] $parameters
     */
    private function openApiResourceParameters(
        SchemaContext $context,
        array $parameters = [],
    ): array {
        return $this->openApiParameters($context, [
            Parameter::make('id')
                ->in('path')
                ->required()
                ->type(Type\Str::make()),
            ...$parameters,
        ]);
    }

    /**
     * @param string[] $resources
     * @return array<int, array{'\$ref': string}>
     */
    private function openApiSchemaRefs(array $resources, string $suffix = ''): array
    {
        return array_map(
            fn(string $resource) => ['$ref' => "#/components/schemas/{$resource}{$suffix}"],
            $resources,
        );
    }

    /**
     * @return array{'\$ref': string}
     */
    private function openApiRelationshipSchemaRef(string $resourceType, string $fieldName): array
    {
        return ['$ref' => "#/components/schemas/{$resourceType}_relationship_{$fieldName}"];
    }
}
