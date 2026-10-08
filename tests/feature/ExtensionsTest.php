<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use InvalidArgumentException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\CollectionAction;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\Request\InvalidQueryParameterException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Extension\Hook\HandleRequest;
use Tobyz\JsonApiServer\Extension\Hook\Hook;
use Tobyz\JsonApiServer\Extension\Hook\HookParameters;
use Tobyz\JsonApiServer\Extension\Hook\ParameterizedHook;
use Tobyz\JsonApiServer\Extension\Hook\SparseFields;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\JsonApiMediaType;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;
use TypeError;

class ExtensionsTest extends AbstractTestCase
{
    // Registration

    #[DataProvider('unnamespacedExtensionParameters')]
    public function test_extension_query_parameters_must_be_namespaced(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Extension query parameter '$name' must be prefixed with the extension's namespace.",
        );

        (new JsonApi())->extension($this->extension(
            namespace: 'ext',
            hooks: [SparseFields::make(fn() => null)->parameters([Parameter::make($name)])],
        ));
    }

    public static function unnamespacedExtensionParameters(): iterable
    {
        yield 'unprefixed' => ['fields'];
        yield 'other namespace' => ['other:option'];
    }

    public function test_extension_namespaces_must_be_unique(): void
    {
        $api = new JsonApi();
        $api->extension($this->extension('https://example.com/a', namespace: 'shared'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Extension namespace 'shared' is already used by https://example.com/a.",
        );

        $api->extension($this->extension('https://example.com/b', namespace: 'shared'));
    }

    public function test_extension_namespaces_must_be_alphanumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Extension namespace 'my-ext' must contain only alphanumeric characters.",
        );

        (new JsonApi())->extension($this->extension(namespace: 'my-ext'));
    }

    public function test_extension_hooks_must_be_hooks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Extension https://example.com/ext must only return Hook instances from hooks().',
        );

        (new JsonApi())->extension($this->extension(hooks: [Parameter::make('x')]));
    }

    public function test_registering_an_extension_again_replaces_its_hooks(): void
    {
        $api = $this->api();
        $api->extension($this->extension(hooks: [SparseFields::make(fn() => ['name'])]));
        $api->extension($this->extension(hooks: [SparseFields::make(fn() => ['email'])]));

        $document = $this->document($api->handle($this->request('GET', '/users/1')));

        $this->assertSame(['email' => 'toby@example.com'], $document['data']['attributes']);
    }

    // Hooks

    public function test_handle_request_hooks_respond_with_the_json_api_media_type(): void
    {
        $api = new JsonApi();
        $api->extension($this->extension(hooks: [HandleRequest::make(fn() => new Response(204))]));

        $response = $api->handle($this->request('GET', '/'));

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(
            'application/vnd.api+json; ext="https://example.com/ext"',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function test_first_hook_to_return_a_result_wins_and_activates_its_extension(): void
    {
        $api = $this->api();
        $api->extension($this->extension('https://example.com/a', hooks: [
            SparseFields::make(fn() => null),
        ]));
        $api->extension($this->extension('https://example.com/b', hooks: [
            SparseFields::make(fn() => ['name']),
        ]));
        $api->extension($this->extension('https://example.com/c', hooks: [
            SparseFields::make(fn() => ['email']),
        ]));

        $response = $api->handle($this->request(
            'GET',
            '/users/1',
            'https://example.com/a',
            'https://example.com/b',
            'https://example.com/c',
        ));

        $this->assertSame(['name' => 'Toby'], $this->document($response)['data']['attributes']);
        $this->assertSame(
            'application/vnd.api+json; ext="https://example.com/b"',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function test_hooks_returning_null_defer_to_standard_behavior(): void
    {
        $api = $this->api();
        $api->extension($this->extension(hooks: [SparseFields::make(fn() => null)]));

        $response = $api->handle($this->request('GET', '/users/1?fields[users]=email'));

        $this->assertSame(
            ['email' => 'toby@example.com'],
            $this->document($response)['data']['attributes'],
        );
        $this->assertSame(JsonApi::MEDIA_TYPE, $response->getHeaderLine('Content-Type'));
    }

    public function test_sparse_fields_hooks_must_return_field_names(): void
    {
        $api = $this->api();
        $api->extension($this->extension(hooks: [SparseFields::make(fn() => 'name')]));

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(
            'SparseFields::__invoke(): Return value must be of type ?array',
        );

        $api->handle($this->request('GET', '/users/1'));
    }

    // Parameters

    #[DataProvider('hooksWithoutParameters')]
    public function test_hook_parameters_require_a_concrete_parameterized_hook(string $hook): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("$hook hooks cannot define parameters.");

        HookParameters::for($hook);
    }

    public static function hooksWithoutParameters(): iterable
    {
        yield 'unparameterized' => [HandleRequest::class];
        yield 'abstract' => [ParameterizedHook::class];
    }

    public function test_hook_parameters_are_resolved_for_the_given_extensions(): void
    {
        $api = new JsonApi();
        $api->extension($a = $this->extension('https://example.com/a', namespace: 'a', hooks: [
            SparseFields::make(fn() => null)->parameters([Parameter::make('a:x')]),
        ]));
        $api->extension($this->extension('https://example.com/b', namespace: 'b', hooks: [
            SparseFields::make(fn() => null)->parameters([Parameter::make('b:x')]),
        ]));

        $parameters = $api->getParameters([HookParameters::for(SparseFields::class)], [$a]);

        $this->assertSame(['a:x'], array_column($parameters, 'name'));
    }

    public function test_hook_parameters_are_only_accepted_on_endpoints_that_run_the_hook(): void
    {
        $api = $this->api();
        $api->extension($this->extensionWithParameter());

        $this->expectException(InvalidQueryParameterException::class);

        $api->handle($this->request('DELETE', '/users/1?myext:only=name'));
    }

    public function test_hook_parameters_are_only_documented_on_endpoints_that_run_the_hook(): void
    {
        $api = $this->api();
        $api->extension($this->extensionWithParameter());

        $paths = (new OpenApiGenerator())->generate($api)['paths']['/users/{id}'];
        $show = array_column($paths['get']['parameters'], null, 'name');

        $this->assertStringEndsWith(
            'Requires the https://example.com/ext extension.',
            $show['myext:only']['description'],
        );
        $this->assertNotContains(
            'myext:only',
            array_column($paths['delete']['parameters'], 'name'),
        );
    }

    public function test_custom_endpoints_can_load_hook_parameters(): void
    {
        $api = new JsonApi();
        $api->extension($this->extensionWithParameter());
        $api->resource(new MockResource(
            'users',
            endpoints: [
                CollectionAction::make(
                    'fields',
                    fn(Context $context) => $context->createResponse([
                        'meta' => [
                            'fields' => array_keys(
                                $context->sparseFields($context->resource('users')),
                            ),
                        ],
                    ]),
                )->parameters([HookParameters::for(SparseFields::class)]),
            ],
            fields: [Attribute::make('name'), Attribute::make('email')],
        ));

        $document = $this->document(
            $api->handle($this->request('POST', '/users/fields?myext:only=email')),
        );

        $this->assertSame(['email'], $document['meta']['fields']);
    }

    private function api(): JsonApi
    {
        $api = new JsonApi();
        $api->resource(new MockResource(
            'users',
            models: [(object) ['id' => '1', 'name' => 'Toby', 'email' => 'toby@example.com']],
            endpoints: [Show::make(), Delete::make()],
            fields: [Attribute::make('name'), Attribute::make('email')],
        ));

        return $api;
    }

    private function request(
        string $method,
        string $uri,
        string ...$extensions,
    ): ServerRequestInterface {
        return $this->buildRequest($method, $uri)->withHeader(
            'Accept',
            (string) new JsonApiMediaType($extensions ?: ['https://example.com/ext']),
        );
    }

    private function document(ResponseInterface $response): array
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * An extension whose SparseFields hook only serializes the field named by
     * its `myext:only` parameter.
     */
    private function extensionWithParameter(): Extension
    {
        return $this->extension(namespace: 'myext', hooks: [
            SparseFields::make(
                fn(Resource $resource, Context $context) => (
                    ($only = $context->parameter('myext:only')) ? [$only] : null
                ),
            )->parameters([Parameter::make('myext:only')]),
        ]);
    }

    /**
     * @param Hook[] $hooks
     */
    private function extension(
        string $uri = 'https://example.com/ext',
        ?string $namespace = null,
        array $hooks = [],
    ): Extension {
        return new class($uri, $namespace, $hooks) extends Extension {
            public function __construct(
                private readonly string $uri,
                private readonly ?string $namespace,
                private readonly array $hooks,
            ) {}

            public function uri(): string
            {
                return $this->uri;
            }

            public function namespace(): ?string
            {
                return $this->namespace;
            }

            public function hooks(): array
            {
                return $this->hooks;
            }
        };
    }
}
