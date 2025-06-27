<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use ReflectionException;
use ReflectionFunction;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

trait BuildsOpenApiPaths
{
    private function buildOpenApiContent(
        array $resources,
        bool $multiple = false,
        bool $included = true,
        bool $links = false,
    ): array {
        $item = count($resources) === 1 ? $resources[0] : ['oneOf' => $resources];

        return [
            JsonApi::MEDIA_TYPE => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => array_filter([
                        'links' => $links ? $this->buildLinksObject($item) : [],
                        'data' => $multiple ? ['type' => 'array', 'items' => $item] : $item,
                        'included' => $included ? ['type' => 'array'] : [],
                    ]),
                ],
            ],
        ];
    }

    private function buildLinksObject(array $item): array
    {
        // @todo: maybe pull in the API or Context to return a server name?
        $baseUri = sprintf('https://{server}/%s', $this->findResourceFromItem($item));
        $defaultQuery = ['page[limit]' => 10];

        $links = [
            'self' => ['page[offset]' => 2],
            'first' => ['page[offset]' => 1],
            'prev' => ['page[offset]' => 1],
            'next' => ['page[offset]' => 3],
            'last' => ['page[offset]' => 10],
        ];

        foreach ($links as $key => $params) {
            $params = $params + $defaultQuery;

            $query = implode(
                '&',
                array_map(
                    fn($k, $v) => $k . '=' . urlencode($v),
                    array_keys($params),
                    $params,
                )
            );

            $links[$key] = sprintf('%s/%s', $baseUri, $query);
        }

        return [
            'type' => 'object',
            'properties' => [
                'self' => [
                    'type' => 'string',
                    'example' => array_map(function (string $uri) {
                        return [
                            'type' => 'string',
                            'example' => $uri,
                        ];
                    }, $links),
                ],
            ],
        ];
    }

    private function findResourceFromItem(array $item)
    {
        $value = $item['$ref'] ?? null;

        if (empty($value)) {
            dd($value);
        }

        $parts = explode('/', $value);

        return end($parts);
    }

    /**
     * @throws ReflectionException
     */
    private function buildOpenApiParameters(Collection $collection): array
    {
        // @todo: fix this
        assert($collection instanceof Resource);

        $parameters = [$this->buildIncludeParameter($collection)];

        if (property_exists($this, 'paginationResolver')) {
            $resolver = $this->paginationResolver;
            $reflection = new ReflectionFunction($resolver);

            if ($reflection->getNumberOfRequiredParameters() > 0) {
                $parameters = array_merge_recursive($parameters, $this->buildPaginatableParameters());
            }
        }

        return array_values(array_filter($parameters));
    }

    private function buildIncludeParameter(Resource $resource): array
    {
        $relationshipNames = array_map(
            fn(Relationship $relationship) => $relationship->name,
            array_filter(
                $resource->fields(),
                fn(Field $field) => $field instanceof Relationship && $field->includable,
            ),
        );

        if (empty($relationshipNames)) {
            return [];
        }

        $includes = implode(', ', $relationshipNames);

        return [
            'name' => 'include',
            'in' => 'query',
            'description' => "Available include parameters: {$includes}.",
            'schema' => [
                'type' => 'string',
            ],
        ];
    }

    private function buildPaginatableParameters(): array
    {
        return [
            [
                'name' => 'page[limit]',
                'in' => 'query',
                'description' => "The limit pagination field.",
                'schema' => [
                    'type' => 'number',
                ],
            ],
            [
                'name' => 'page[offset]',
                'in' => 'query',
                'description' => "The offset pagination field.",
                'schema' => [
                    'type' => 'number',
                ],
            ],
        ];
    }

    public function buildBadRequestErrorResponse(): array
    {
        return $this->buildErrorResponse(
            'A bad request.',
            400,
            'Bad Request',
            'Please try again with a valid request.',
        );
    }

    public function buildUnauthorizedErrorResponse(): array
    {
        return $this->buildErrorResponse(
            'An unauthorised error.',
            401,
            'Unauthorized',
            'Please login and try again.',
        );
    }

    public function buildForbiddenErrorResponse(): array
    {
        return $this->buildErrorResponse(
            'A forbidden error.',
            403,
            'Forbidden',
        );
    }

    public function buildNotFoundErrorResponse(): array
    {
        return $this->buildErrorResponse(
            'A bad request.',
            404,
            'Not Found',
            'The requested resource could not be found.',
        );
    }

    public function buildInternalServerErrorResponse(): array
    {
        return $this->buildErrorResponse(
            'A bad request.',
            500,
            'Internal Server Error',
            'Please try again later.',
        );
    }

    public function buildErrorResponse(string $description, int $status, string $title, ?string $detail = null): array
    {
        return [
            'description' => $description,
            'content' => [
                JsonApi::MEDIA_TYPE => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'errors' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => [
                                        'status',
                                        'title',
                                    ],
                                    'properties' => array_filter([
                                        'status' => [
                                            'type' => 'string',
                                            'example' => (string)$status,
                                        ],
                                        'title' => [
                                            'type' => 'string',
                                            'example' => $title,
                                        ],
                                        'detail' => [
                                            'type' => 'string',
                                            'example' => $detail,
                                        ],
                                        'source' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'pointer' => [
                                                    'type' => 'string',
                                                ],
                                                'parameter' => [
                                                    'type' => 'string',
                                                ],
                                                'header' => [
                                                    'type' => 'string',
                                                ],
                                            ],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
