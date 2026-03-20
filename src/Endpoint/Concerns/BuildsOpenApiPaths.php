<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

trait BuildsOpenApiPaths
{
    /**
     * @param Parameter[] $parameters
     */
    private function openApiParameters(SchemaContext $context, array $parameters): array
    {
        return array_map(
            fn(Parameter $parameter) => $parameter->getSchema($context),
            $parameters,
        );
    }

    /**
     * @param Parameter[] $parameters
     */
    private function openApiResourceParameters(SchemaContext $context, array $parameters = []): array
    {
        return [$this->openApiIdParameter(), ...$this->openApiParameters($context, $parameters)];
    }

    private function openApiIdParameter(): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ];
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
