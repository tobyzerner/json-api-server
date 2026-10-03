<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory as ValidationFactory;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use Throwable;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Laravel\EloquentBuffer;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\laravel\Models\Comment;
use Tobyz\Tests\JsonApiServer\laravel\Models\Country;
use Tobyz\Tests\JsonApiServer\laravel\Models\Image;
use Tobyz\Tests\JsonApiServer\laravel\Models\Post;
use Tobyz\Tests\JsonApiServer\laravel\Models\Tag;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

/**
 * Boots Eloquent on an in-memory SQLite database, with the facades used by
 * the Laravel helpers bound to the container.
 */
abstract class LaravelTestCase extends AbstractTestCase
{
    protected JsonApi $api;
    protected Capsule $db;
    protected Container $app;

    /** Whether the fake auth guard reports an authenticated user. */
    protected bool $authenticated = false;

    /** @var array<string, bool> Abilities the fake gate allows. */
    protected array $abilities = [];

    /** @var array<int, array{0: array, 1: array}> Calls made to the fake gate. */
    protected array $gateCalls = [];

    protected function setUp(): void
    {
        $this->app = new Container();
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);

        $this->db = new Capsule($this->app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->bootEloquent();

        $this->setConfig('app.timezone', 'UTC');

        $this->createSchema();
        $this->bindValidator();
        $this->bindAuth();

        $this->api = new JsonApi();
    }

    protected function tearDown(): void
    {
        // PHPUnit keeps test cases alive for the whole run, so release the
        // database and the static state that refers to it.
        $this->db->getDatabaseManager()->disconnect();
        Model::unsetConnectionResolver();

        $buffer = new ReflectionClass(EloquentBuffer::class);
        $buffer->setStaticPropertyValue('buffer', []);
        $buffer->setStaticPropertyValue('linkage', null);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        unset($this->api, $this->db, $this->app);

        parent::tearDown();
    }

    /**
     * Set a value in the config repository that Capsule binds to the container.
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $this->app['config'][$key] = $value;
    }

    private function createSchema(): void
    {
        $schema = $this->db->getConnection()->getSchemaBuilder();

        $schema->create('countries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->boolean('is_admin')->default(false);
        });

        $schema->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('author_email')->nullable();
            $table->string('title');
            $table->boolean('published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->softDeletes();
        });

        $schema->create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('body');
        });

        $schema->create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        $schema->create('post_tag', function (Blueprint $table) {
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('tag_id');
        });

        $schema->create('images', function (Blueprint $table) {
            $table->increments('id');
            $table->string('imageable_type')->nullable();
            $table->unsignedInteger('imageable_id')->nullable();
            $table->string('url');
        });
    }

    private function bindValidator(): void
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'validation', [
            'required' => 'The :attribute field is required.',
            'unique' => 'The :attribute has already been taken.',
            'max' => ['string' => 'The :attribute may not be greater than :max characters.'],
            'email' => 'The :attribute must be a valid email address.',
        ]);

        $validator = new ValidationFactory(new Translator($loader, 'en'), $this->app);
        $validator->setPresenceVerifier(new DatabasePresenceVerifier($this->db->getDatabaseManager()));

        $this->app->instance('validator', $validator);
    }

    private function bindAuth(): void
    {
        $this->app->instance('auth', new class(fn() => $this->authenticated) {
            public function __construct(
                private Closure $check,
            ) {}

            public function check(): bool
            {
                return ($this->check)();
            }
        });

        $this->app->instance(Gate::class, new class(function (array $abilities, array $arguments): bool {
            $this->gateCalls[] = [$abilities, $arguments];

            foreach ($abilities as $ability) {
                if ($this->abilities[$ability] ?? false) {
                    return true;
                }
            }

            return false;
        }) {
            public function __construct(
                private Closure $any,
            ) {}

            public function any($abilities, $arguments = []): bool
            {
                return ($this->any)((array) $abilities, (array) $arguments);
            }
        });
    }

    /**
     * Register a resource for each model, with all endpoints.
     *
     * @param array<string, array> $fields Fields by type, replacing the default attribute.
     * @param array<string, Closure> $scopes Scopes by type.
     * @param array<string, array> $filters Filters by type.
     */
    protected function resources(array $fields = [], array $scopes = [], array $filters = []): void
    {
        $models = [
            'posts' => [Post::class, 'title'],
            'users' => [User::class, 'name'],
            'comments' => [Comment::class, 'body'],
            'tags' => [Tag::class, 'name'],
            'countries' => [Country::class, 'name'],
            'images' => [Image::class, 'url'],
        ];

        foreach ($models as $type => [$model, $attribute]) {
            $this->api->resource(
                new TestResource(
                    $type,
                    $model,
                    endpoints: [Index::make(), Show::make(), Create::make(), Update::make()],
                    fields: $fields[$type] ?? [Attribute::make($attribute)],
                    filters: $filters[$type] ?? [],
                    scope: $scopes[$type] ?? null,
                ),
            );
        }
    }

    /**
     * Run a callback and return its result and the SQL of the queries it ran.
     *
     * @return array{0: mixed, 1: string[]}
     */
    protected function queries(callable $callback): array
    {
        $connection = $this->db->getConnection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $result = $callback();
        } finally {
            $connection->disableQueryLog();
        }

        return [$result, array_column($connection->getQueryLog(), 'query')];
    }

    protected function assertQueryCount(int $count, string $table, array $queries): void
    {
        $this->assertCount($count, array_filter($queries, fn($sql) => str_contains($sql, "from \"$table\"")));
    }

    protected function get(string $uri): ResponseInterface
    {
        return $this->api->handle($this->buildRequest('GET', $uri));
    }

    protected function send(string $method, string $uri, array $body): ResponseInterface
    {
        return $this->api->handle($this->buildRequest($method, $uri)->withParsedBody($body));
    }

    protected function create(string $type, array $attributes = [], array $relationships = []): ResponseInterface
    {
        return $this->send('POST', "/$type", [
            'data' => array_filter(compact('type', 'attributes', 'relationships')),
        ]);
    }

    protected function update(
        string $type,
        string $id,
        array $attributes = [],
        array $relationships = [],
    ): ResponseInterface {
        return $this->send('PATCH', "/$type/$id", [
            'data' => array_filter(compact('type', 'id', 'attributes', 'relationships')),
        ]);
    }

    /**
     * Run a callback that should throw, and return what it threw.
     */
    protected function exception(callable $callback): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('Expected an exception to be thrown.');
    }

    protected function document(ResponseInterface $response): array
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * @return string[] The IDs of the primary data in a response.
     */
    protected function ids(ResponseInterface $response): array
    {
        return array_column($this->document($response)['data'], 'id');
    }

    /**
     * @return array<array|null> The linkage of a relationship for each resource in the primary data.
     */
    protected function linkage(ResponseInterface $response, string $relationship): array
    {
        return array_map(
            fn($resource) => $resource['relationships'][$relationship]['data'],
            $this->document($response)['data'],
        );
    }

    /**
     * @return array The values of an attribute for each of the given resource objects.
     */
    protected function attributeValues(array $resources, string $attribute): array
    {
        return array_map(fn($resource) => $resource['attributes'][$attribute] ?? null, $resources);
    }
}
