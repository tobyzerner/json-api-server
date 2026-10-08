<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\JsonApiServer\SchemaContext;

trait BuildsOpenApiPaths
{
    /**
     * @param Parameter[] $parameters
     */
    private function openApiParameters(SchemaContext $context, array $parameters): array
    {
        $extensionParameters = [];
        $extensionUris = [];

        foreach ($context->api->extensions as $extension) {
            foreach ($extension->parameters() as $parameter) {
                $extensionParameters[] = $parameter;
                $extensionUris[spl_object_id($parameter)] = $extension->uri();
            }
        }

        return array_map(
            function (Parameter $parameter) use ($context, $extensionUris) {
                $schema = $parameter->getSchema($context);

                if ($uri = $extensionUris[spl_object_id($parameter)] ?? null) {
                    $schema['description'] = ltrim(
                        ($schema['description'] ?? '') . " Requires the $uri extension.",
                    );
                }

                return $schema;
            },
            $context->api->getParameters([...$extensionParameters, ...$parameters]),
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
