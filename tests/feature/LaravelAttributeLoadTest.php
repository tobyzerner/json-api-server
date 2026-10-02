<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use ArrayObject;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Laravel\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Serializer;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class LaravelAttributeLoadTest extends AbstractTestCase
{
    private JsonApi $api;
    private ArrayObject $loads;

    public function setUp(): void
    {
        $this->api = new JsonApi();
        $this->loads = new ArrayObject();
    }

    public function test_relations_are_loaded_in_one_batch_for_visible_models()
    {
        $this->usersResource(
            fields: [
                $this->attribute('institution')
                    ->visible(fn($model) => $model->id !== '2')
                    ->load('institution'),
            ],
            count: 3,
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users'));
        $data = json_decode($response->getBody(), true)['data'];

        $this->assertSame([[['institution'], ['1', '3']]], $this->loads->getArrayCopy());
        $this->assertSame('loaded', $data[0]['attributes']['institution']);
        $this->assertArrayNotHasKey('attributes', $data[1]);
        $this->assertSame('loaded', $data[2]['attributes']['institution']);
    }

    public function test_relations_are_not_loaded_when_no_model_is_visible()
    {
        $this->usersResource(
            fields: [
                $this->attribute('institution')
                    ->visible(false)
                    ->load('institution'),
            ],
        );

        $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertCount(0, $this->loads);
    }

    public function test_relations_are_not_loaded_when_field_is_not_requested()
    {
        $this->usersResource(
            fields: [
                $this->attribute('name')->get(null),
                $this->attribute('institution')->load('institution'),
            ],
        );

        $this->api->handle($this->buildRequest('GET', '/users?fields[users]=name'));

        $this->assertCount(0, $this->loads);
    }

    public function test_each_load_call_loads_its_relations_with_constraints()
    {
        $constraintArgs = null;

        $this->usersResource(
            fields: [
                $this->attribute('institution')
                    ->load('institution')
                    ->load([
                        'referralSubscription.product',
                        'tags' => function (...$args) use (&$constraintArgs) {
                            $constraintArgs = $args;
                        },
                    ]),
            ],
            count: 1,
        );

        $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertCount(2, $this->loads);
        $this->assertSame(['institution'], $this->loads[0][0]);

        $relations = $this->loads[1][0];

        $this->assertSame([0, 'tags'], array_keys($relations));
        $this->assertSame('referralSubscription.product', $relations[0]);

        $relations['tags']('query');

        $this->assertSame('query', $constraintArgs[0]);
        $this->assertInstanceOf(Context::class, $constraintArgs[1]);
    }

    public function test_callbacks_run_once_with_visible_models_in_declared_order()
    {
        $calls = [];

        $this->usersResource(
            fields: [
                $this->attribute('commentCount')
                    ->visible(fn($model) => $model->id !== '2')
                    ->load('comments')
                    ->load(function ($models, Context $context) use (&$calls) {
                        $calls[] = array_map(fn($model) => $model->id, $models->models);

                        foreach ($models->models as $model) {
                            $model->loaded['commentCount'] = $model->loaded['comments'] . ' count';
                        }
                    }),
            ],
            count: 3,
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users'));
        $data = json_decode($response->getBody(), true)['data'];

        $this->assertSame([['1', '3']], $calls);
        $this->assertSame('loaded count', $data[0]['attributes']['commentCount']);
        $this->assertSame('loaded count', $data[2]['attributes']['commentCount']);
    }

    public function test_callbacks_alone_defer_the_value()
    {
        $calls = 0;

        $this->usersResource(
            fields: [
                $this->attribute('commentCount')->load(function ($models) use (&$calls) {
                    $calls++;

                    foreach ($models->models as $model) {
                        $model->loaded['commentCount'] = 'counted';
                    }
                }),
            ],
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertSame(1, $calls);
        $this->assertCount(0, $this->loads);
        $this->assertJsonApiDocumentSubset(
            ['data' => [['attributes' => ['commentCount' => 'counted']]]],
            $response->getBody(),
        );
    }

    public function test_relations_are_loaded_in_one_batch_for_included_models()
    {
        $users = $this->usersResource(
            fields: [$this->attribute('institution')->load('institution')],
        );

        $this->api->resource(
            new MockResource(
                'questions',
                models: ['1' => (object) ['id' => '1', 'contributors' => $users]],
                endpoints: [Show::make()],
                fields: [
                    ToMany::make('contributors')
                        ->type('users')
                        ->includable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/questions/1?include=contributors'),
        );

        $this->assertSame([[['institution'], ['1', '2']]], $this->loads->getArrayCopy());
        $this->assertJsonApiDocumentSubset(
            [
                'included' => [
                    ['id' => '1', 'attributes' => ['institution' => 'loaded']],
                    ['id' => '2', 'attributes' => ['institution' => 'loaded']],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_relations_are_loaded_for_resource_meta()
    {
        $this->usersResource(meta: [$this->attribute('institution')->load('institution')]);

        $response = $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertSame([[['institution'], ['1', '2']]], $this->loads->getArrayCopy());
        $this->assertJsonApiDocumentSubset(
            ['data' => [['meta' => ['institution' => 'loaded']]]],
            $response->getBody(),
        );
    }

    public function test_deferred_getters_are_still_batched()
    {
        $log = [];

        $this->usersResource(
            fields: [
                $this->attribute('institution')
                    ->load('institution')
                    ->get(function ($model) use (&$log) {
                        $log[] = "get:$model->id";

                        return function () use ($model, &$log) {
                            $log[] = "resolve:$model->id";

                            return $model->loaded['institution'];
                        };
                    }),
            ],
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertCount(1, $this->loads);
        $this->assertSame(['get:1', 'get:2', 'resolve:1', 'resolve:2'], $log);
        $this->assertJsonApiDocumentSubset(
            ['data' => [['attributes' => ['institution' => 'loaded']]]],
            $response->getBody(),
        );
    }

    public function test_models_from_an_unfinished_serialization_are_not_loaded_later()
    {
        $field = $this->attribute('institution')->load('institution');

        $this->usersResource(fields: [$field]);

        $context = (new Context($this->api, $this->buildRequest('GET', '/users')))
            ->withSerializer(new Serializer())
            ->withModel((object) ['id' => 'stale', 'loaded' => []]);

        $field->getValue($context);

        $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertSame([[['institution'], ['1', '2']]], $this->loads->getArrayCopy());
    }

    /**
     * Register a users resource and return its models.
     */
    private function usersResource(array $fields = [], array $meta = [], int $count = 2): array
    {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $users[] = (object) ['id' => (string) $i, 'name' => "User $i", 'loaded' => []];
        }

        $this->api->resource(
            new MockResource(
                'users',
                models: $users,
                endpoints: [Index::make()],
                fields: $fields,
                meta: $meta,
            ),
        );

        return $users;
    }

    /**
     * Make an attribute that records loads instead of querying, and whose
     * value reports whether its relation was loaded.
     */
    private function attribute(string $name): Attribute
    {
        $attribute = new class ($name) extends Attribute {
            public ArrayObject $loads;

            protected function newCollection(array $models): object
            {
                return new class ($models, $this->loads) {
                    public function __construct(
                        public array $models,
                        private ArrayObject $loads,
                    ) {
                    }

                    public function loadMissing(array $relations): void
                    {
                        $this->loads[] = [
                            $relations,
                            array_map(fn($model) => $model->id, $this->models),
                        ];

                        foreach ($this->models as $model) {
                            foreach ($relations as $key => $value) {
                                $model->loaded[is_int($key) ? $value : $key] = 'loaded';
                            }
                        }
                    }
                };
            }
        };

        $attribute->loads = $this->loads;

        return $attribute->get(fn($model) => $model->loaded[$name] ?? 'not loaded');
    }
}
