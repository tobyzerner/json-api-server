<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Extension\Hook\HandleRequest;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#content-negotiation
 */
class ContentNegotiationTest extends AbstractTestCase
{
    private const DEMO_MEDIA_TYPE = 'application/vnd.api+json; ext="https://example.com/extensions/demo"';

    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource('users', models: [(object) ['id' => '1']], endpoints: [Show::make()]),
        );
    }

    public function test_json_api_content_type_is_returned()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertEquals('application/vnd.api+json', $response->getHeaderLine('Content-Type'));
    }

    public function test_success_when_request_content_type_contains_profile()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')->withHeader(
                'Accept',
                'application/vnd.api+json; profile="http://example.com/profile"',
            ),
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_error_when_request_content_type_contains_unknown_parameter()
    {
        $request = $this->buildRequest('PATCH', '/users/1')->withHeader(
            'Content-Type',
            'application/vnd.api+json; unknown="parameter"',
        );

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->api->handle($request);
    }

    public function test_error_when_request_content_type_contains_unsupported_extension()
    {
        $request = $this->buildRequest('PATCH', '/users/1')->withHeader(
            'Content-Type',
            'application/vnd.api+json; ext="http://example.com/extension"',
        );

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->api->handle($request);
    }

    public function test_success_when_accepts_wildcard()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')->withHeader('Accept', '*/*'),
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_error_when_all_accepts_have_unknown_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')->withHeader(
            'Accept',
            'application/vnd.api+json; unknown="parameter", application/vnd.api+json; unknown="parameter2"',
        );

        $this->expectException(NotAcceptableException::class);

        $this->api->handle($request);
    }

    public function test_success_when_only_some_accepts_have_parameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')->withHeader(
                'Accept',
                'application/vnd.api+json; unknown="parameter", application/vnd.api+json',
            ),
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_responds_with_vary_header()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertEquals('Accept', $response->getHeaderLine('vary'));
    }

    public function test_requested_profiles_can_be_read()
    {
        $this->api = new JsonApi();

        $capturedProfiles = null;

        $resource = new MockResource(
            'users',
            models: [(object) ['id' => '1']],
            endpoints: [
                Show::make()->response(function ($response, $model, $context) use (
                    &$capturedProfiles,
                ) {
                    $capturedProfiles = $context->requestedProfiles();
                }),
            ],
        );

        $this->api->resource($resource);

        $request = $this->buildRequest('GET', '/users/1')->withHeader(
            'Accept',
            'application/vnd.api+json; profile="https://example.com/profile1 https://example.com/profile2"',
        );

        $this->api->handle($request);

        $this->assertEquals(
            ['https://example.com/profile1', 'https://example.com/profile2'],
            $capturedProfiles,
        );
    }

    public function test_activated_profiles_appear_in_content_type()
    {
        $this->api = new JsonApi();

        $resource = new MockResource(
            'users',
            models: [(object) ['id' => '1']],
            endpoints: [
                Show::make()->response(function ($response, $model, $context) {
                    $context->activateProfile('https://example.com/profile1');
                    $context->activateProfile('https://example.com/profile2');
                }),
            ],
        );

        $this->api->resource($resource);

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertEquals(
            'application/vnd.api+json; profile="https://example.com/profile1 https://example.com/profile2"',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function test_profiles_from_lines_with_unsupported_extensions_are_ignored()
    {
        $this->api = new JsonApi();

        $capturedProfiles = null;

        $resource = new MockResource(
            'users',
            models: [(object) ['id' => '1']],
            endpoints: [
                Show::make()->response(function ($response, $model, $context) use (
                    &$capturedProfiles,
                ) {
                    $capturedProfiles = $context->requestedProfiles();
                }),
            ],
        );

        $this->api->resource($resource);

        $request = $this->buildRequest('GET', '/users/1')->withHeader(
            'Accept',
            'application/vnd.api+json; ext="https://unsupported.com/ext"; profile="https://example.com/should-be-ignored", application/vnd.api+json; profile="https://example.com/valid-profile"',
        );

        $this->api->handle($request);

        // Should only get the profile from the second (valid) Accept line
        $this->assertEquals(['https://example.com/valid-profile'], $capturedProfiles);
    }

    public function test_extensions_can_activate_on_get_requests_without_content_type()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo());

        $response = $this->api->handle(
            $this->buildRequest('GET', '/extension-demo')->withHeader(
                'Accept',
                self::DEMO_MEDIA_TYPE,
            ),
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            self::DEMO_MEDIA_TYPE,
            $response->getHeaderLine('Content-Type'),
        );
        $this->assertJsonApiDocumentSubset(
            ['meta' => ['activated' => true]],
            (string) $response->getBody(),
        );
    }

    public function test_content_type_does_not_restrict_extensions_without_a_body()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo());

        $response = $this->api->handle(
            $this
                ->buildRequest('GET', '/extension-demo')
                ->withHeader(
                    'Accept',
                    self::DEMO_MEDIA_TYPE,
                )
                ->withHeader('Content-Type', 'application/vnd.api+json'),
        );

        $this->assertJsonApiDocumentSubset(
            ['meta' => ['activated' => true]],
            (string) $response->getBody(),
        );
    }

    public function test_extensions_with_content_type_must_be_requested_in_both_headers()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo());

        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle(
            $this
                ->buildRequest('POST', '/extension-demo')
                ->withHeader(
                    'Accept',
                    self::DEMO_MEDIA_TYPE,
                )
                ->withHeader('Content-Type', 'application/vnd.api+json')
                ->withParsedBody(['data' => ['type' => 'users']]),
        );
    }

    public function test_extension_already_in_response_media_type_is_not_duplicated()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo(self::DEMO_MEDIA_TYPE));

        $response = $this->api->handle(
            $this->buildRequest('GET', '/extension-demo')->withHeader(
                'Accept',
                self::DEMO_MEDIA_TYPE,
            ),
        );

        $this->assertEquals(self::DEMO_MEDIA_TYPE, $response->getHeaderLine('Content-Type'));
    }

    public function test_content_type_restricts_extensions_when_content_length_indicates_a_body()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo());

        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle(
            $this
                ->buildRequest('POST', '/extension-demo')
                ->withHeader(
                    'Accept',
                    self::DEMO_MEDIA_TYPE,
                )
                ->withHeader('Content-Type', 'application/vnd.api+json')
                ->withHeader('Content-Length', '2'),
        );
    }

    public function test_content_type_restricts_extensions_when_the_body_is_only_in_the_stream()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo());

        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle(
            $this
                ->buildRequest('POST', '/extension-demo')
                ->withHeader('Accept', self::DEMO_MEDIA_TYPE)
                ->withHeader('Content-Type', 'application/vnd.api+json')
                ->withBody(Stream::create('{"data":{"type":"users"}}')),
        );
    }

    public function test_extension_response_with_other_media_type_parameters_gets_ext()
    {
        $this->api = new JsonApi();
        $this->api->extension($this->extensionDemo('application/vnd.api+json; charset=utf-8'));

        $response = $this->api->handle(
            $this->buildRequest('GET', '/extension-demo')->withHeader(
                'Accept',
                self::DEMO_MEDIA_TYPE,
            ),
        );

        $this->assertEquals(
            self::DEMO_MEDIA_TYPE,
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function test_empty_media_type_uris_are_ignored()
    {
        $this->api = new JsonApi();
        $this->api->extension(
            $this->extensionDemo(
                'application/vnd.api+json; ext="  https://example.com/extensions/demo "',
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/extension-demo')->withHeader(
                'Accept',
                self::DEMO_MEDIA_TYPE,
            ),
        );

        $this->assertEquals(self::DEMO_MEDIA_TYPE, $response->getHeaderLine('Content-Type'));
    }

    private function extensionDemo(?string $contentType = null): Extension
    {
        return new class($contentType) extends Extension {
            public function __construct(
                private readonly ?string $contentType,
            ) {}

            public function uri(): string
            {
                return 'https://example.com/extensions/demo';
            }

            public function hooks(): array
            {
                return [
                    HandleRequest::make(function (Context $context): ?ResponseInterface {
                        if ($context->path() !== 'extension-demo') {
                            return null;
                        }

                        $response = $context->createResponse(['meta' => ['activated' => true]]);

                        return $this->contentType
                            ? $response->withHeader('Content-Type', $this->contentType)
                            : $response;
                    }),
                ];
            }
        };
    }
}
