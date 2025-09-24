<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

use function Tobyz\JsonApiServer\apply_filters;

/**
 * @see https://jsonapi.org/format/#fetching-filtering
 */
class FilteringTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1', 'name' => 'Toby']),
                    ($user2 = (object) ['id' => '2', 'name' => 'Franz']),
                ],
                endpoints: [Index::make()],
                fields: [Attribute::make('name')],
                filters: [
                    CustomFilter::make('name', function ($query, $value) {
                        $query->models = array_filter(
                            $query->models,
                            fn($model) => $model->name === $value,
                        );
                    }),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [
                    '1' => (object) [
                        'id' => '1',
                        'author' => $user1,
                    ],
                    '2' => (object) [
                        'id' => '2',
                        'author' => $user2,
                    ],
                ],
                endpoints: [Index::make()],
                fields: [ToOne::make('author')->type('users')],
                filters: [
                    CustomFilter::make('author', function ($query, $value, Context $context) {
                        $query->models = array_filter($query->models, function ($model) use (
                            $query,
                            $value,
                            $context,
                        ) {
                            /** @var ToOne $relationship */
                            $resource = $context->resource(
                                $context->collection->resource($model, $context),
                            );
                            $relationship = $context->fields($resource)['author'];
                            $relatedResource = $context->api->getResource(
                                $relationship->collections[0],
                            );

                            apply_filters(
                                $q = (object) ['models' => [$model->author]],
                                $value,
                                $relatedResource,
                                $context,
                            );

                            return !!$q->models;
                        });
                    }),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'posts',
                models: [
                    (object) ['id' => '1', 'status' => 'draft', 'views' => 50],
                    (object) ['id' => '2', 'status' => 'published', 'views' => 150],
                    (object) ['id' => '3', 'status' => 'published', 'views' => 80],
                ],
                endpoints: [Index::make()],
                fields: [Attribute::make('status'), Attribute::make('views')],
                filters: [
                    CustomFilter::make('status', function ($query, $value) {
                        [$operator, $payload] = $this->resolveOperator($value, ['eq', 'in']);

                        if ($operator === 'in') {
                            $values = array_map('strval', (array) $payload);

                            $query->models = array_filter(
                                $query->models,
                                fn($model) => in_array($model->status, $values, true),
                            );

                            return;
                        }

                        $target = is_array($payload) ? ($payload[0] ?? null) : $payload;

                        $query->models = array_filter(
                            $query->models,
                            fn($model) => $model->status === $target,
                        );
                    }),

                    CustomFilter::make('views', function ($query, $value) {
                        [$operator, $payload] = $this->resolveOperator(
                            $value,
                            ['eq', 'gt', 'gte', 'lt', 'lte', 'between', 'in'],
                        );

                        $values = array_values(array_map('intval', (array) $payload));
                        $first = $values[0] ?? 0;
                        $second = $values[1] ?? $first;

                        $query->models = array_filter($query->models, function ($model) use (
                            $values,
                            $operator,
                            $first,
                            $second,
                        ) {
                            $views = (int) $model->views;

                            return match ($operator) {
                                'gt' => $views > $first,
                                'gte' => $views >= $first,
                                'lt' => $views < $first,
                                'lte' => $views <= $first,
                                'between' => $views >= $first && $views <= $second,
                                'in' => in_array($views, $values, true),
                                default => $views === $first,
                            };
                        });
                    }),
                ],
            ),
        );
    }

    public function test_filtering()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/users?filter[name]=Toby'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => ['name' => 'Toby'],
                    ],
                ],
            ],
            $body = $response->getBody(),
        );

        $document = json_decode($body, true);

        $this->assertCount(1, $document['data']);
    }

    public function test_nested_filtering()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles?filter[author][name]=Toby'),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => [['type' => 'articles', 'id' => '1']]],
            $body = $response->getBody(),
        );

        $document = json_decode($body, true);

        $this->assertCount(1, $document['data']);
    }

    public function test_filtering_with_comparison_operator()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/posts?filter[views][gt]=100'));

        $this->assertJsonApiDocumentSubset(
            ['data' => [['type' => 'posts', 'id' => '2']]],
            $body = $response->getBody(),
        );

        $document = json_decode($body, true);

        $this->assertCount(1, $document['data']);
    }

    public function test_filtering_with_nested_boolean_groups()
    {
        $response = $this->api->handle(
            $this->buildRequest(
                'GET',
                '/posts?filter[or][0][status]=published&filter[or][0][views][gt]=100&filter[or][1][status]=draft',
            ),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertCount(2, $document['data']);

        $ids = array_column($document['data'], 'id');
        sort($ids);

        $this->assertSame(['1', '2'], $ids);
    }

    public function test_filtering_with_and_group()
    {
        $response = $this->api->handle(
            $this->buildRequest(
                'GET',
                '/posts?filter[and][0][status]=published&filter[and][1][views][gt]=100',
            ),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertCount(1, $document['data']);
        $this->assertSame('2', $document['data'][0]['id']);
    }

    public function test_filtering_with_not_group()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/posts?filter[not][status]=draft'),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertCount(2, $document['data']);

        $ids = array_column($document['data'], 'id');
        sort($ids);

        $this->assertSame(['2', '3'], $ids);
    }

    public function test_filtering_with_nested_not_group()
    {
        $response = $this->api->handle(
            $this->buildRequest(
                'GET',
                '/posts?filter[not][or][0][status]=published&filter[not][or][1][views][gt]=100',
            ),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertCount(1, $document['data']);
        $this->assertSame('1', $document['data'][0]['id']);
    }

    public function test_invalid_filter_operator_returns_error()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unsupported operator: gte');

        $this->api->handle($this->buildRequest('GET', '/posts?filter[status][gte]=draft'));
    }

    private function resolveOperator(array|string $value, array $allowed): array
    {
        if (!is_array($value) || array_is_list($value)) {
            return ['eq', $value];
        }

        if (count($value) !== 1) {
            throw new BadRequestException('Operator groups cannot combine with other values');
        }

        $operator = array_key_first($value);

        if (!in_array($operator, $allowed, true)) {
            throw new BadRequestException("Unsupported operator: $operator");
        }

        return [$operator, $value[$operator]];
    }
}
