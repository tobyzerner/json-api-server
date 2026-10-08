<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Nyholm\Psr7\ServerRequest;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\Request\InvalidQueryParameterException;
use Tobyz\JsonApiServer\Extension\Relfield\Exception\RelfieldFieldsConflictException;
use Tobyz\JsonApiServer\Extension\Relfield\Relfield;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://github.com/ThorstenSuckow/relfield
 */
class RelfieldTest extends AbstractTestCase
{
    private const MEDIA_TYPE = JsonApi::MEDIA_TYPE . '; ext="' . Relfield::URI . '"';

    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
        $this->api->extension(new Relfield());

        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    $user = (object) ['id' => '1', 'name' => 'Toby', 'email' => 'toby@example.com'],
                ],
                fields: [Attribute::make('name'), Attribute::make('email')->sparse()],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [
                    (object) [
                        'id' => '1',
                        'title' => 'foo',
                        'body' => 'bar',
                        'version' => 'v1',
                        'author' => $user,
                    ],
                ],
                endpoints: [Show::make()],
                fields: [
                    Attribute::make('title'),
                    Attribute::make('body'),
                    Attribute::make('version')->sparse(),
                    ToOne::make('author')->type('users')->includable(),
                ],
            ),
        );
    }

    private function request(string $uri): ServerRequest
    {
        return $this->buildRequest('GET', $uri)->withHeader('Accept', static::MEDIA_TYPE);
    }

    private function fetch(string $uri): array
    {
        $response = $this->api->handle($this->request($uri));

        return json_decode($response->getBody(), true);
    }

    public function test_additional_fields_are_added_to_default_fields(): void
    {
        $document = $this->fetch('/articles/1?relfield:fields[articles]=version');

        $this->assertSame(
            ['title' => 'foo', 'body' => 'bar', 'version' => 'v1'],
            $document['data']['attributes'],
        );
        $this->assertArrayHasKey('author', $document['data']['relationships']);
    }

    public function test_excluded_fields_are_removed_from_default_fields(): void
    {
        $document = $this->fetch('/articles/1?relfield:fields[articles]=-body,-author');

        $this->assertSame(['title' => 'foo'], $document['data']['attributes']);
        $this->assertArrayNotHasKey('relationships', $document['data']);
    }

    public function test_additional_and_excluded_fields_can_be_combined(): void
    {
        $document = $this->fetch('/articles/1?relfield:fields[articles]=version,-title');

        $this->assertSame(['body' => 'bar', 'version' => 'v1'], $document['data']['attributes']);
    }

    public function test_empty_value_returns_default_fields(): void
    {
        $document = $this->fetch('/articles/1?relfield:fields[articles]=');

        $this->assertSame(['title' => 'foo', 'body' => 'bar'], $document['data']['attributes']);
    }

    public function test_unknown_and_redundant_fields_are_ignored(): void
    {
        $document = $this->fetch(
            '/articles/1?relfield:fields[articles]=unknown,title,-missing,-version',
        );

        $this->assertSame(['title' => 'foo', 'body' => 'bar'], $document['data']['attributes']);
    }

    public function test_relative_and_absolute_fieldsets_for_different_types(): void
    {
        $document = $this->fetch(
            '/articles/1?include=author&relfield:fields[users]=email&fields[articles]=author',
        );

        $this->assertArrayNotHasKey('attributes', $document['data']);
        $this->assertSame(
            ['name' => 'Toby', 'email' => 'toby@example.com'],
            $document['included'][0]['attributes'],
        );
    }

    public function test_relative_and_absolute_fieldsets_for_same_type_are_rejected(): void
    {
        try {
            $this->api->handle(
                $this->request(
                    '/articles/1?relfield:fields[articles]=version&fields[articles]=title',
                ),
            );
            $this->fail('Expected conflicting fieldsets to be rejected.');
        } catch (JsonApiErrorsException $e) {
            $this->assertInstanceOf(RelfieldFieldsConflictException::class, $e->errors[0]);
            $this->assertSame(
                'relfield:fields[articles]',
                $e->errors[0]->getJsonApiError()['source']['parameter'],
            );
        }
    }

    public function test_invalid_fieldset_is_rejected(): void
    {
        try {
            $this->api->handle($this->request('/articles/1?relfield:fields[articles][x]=title'));
            $this->fail('Expected invalid fieldset to be rejected.');
        } catch (JsonApiErrorsException $e) {
            $this->assertSame(
                'relfield:fields[articles]',
                $e->errors[0]->getJsonApiError()['source']['parameter'],
            );
        }
    }

    public function test_extension_appears_in_content_type_when_applied(): void
    {
        $response = $this->api->handle($this->request(
            '/articles/1?relfield:fields[articles]=version',
        ));

        $this->assertSame(static::MEDIA_TYPE, $response->getHeaderLine('Content-Type'));
    }

    public function test_extension_does_not_appear_in_content_type_when_not_applied(): void
    {
        $response = $this->api->handle($this->request('/articles/1'));

        $this->assertSame(JsonApi::MEDIA_TYPE, $response->getHeaderLine('Content-Type'));
    }

    public function test_parameter_is_ignored_when_extension_is_not_negotiated(): void
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1?relfield:fields[articles]=version'),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertSame(['title' => 'foo', 'body' => 'bar'], $document['data']['attributes']);
        $this->assertSame(JsonApi::MEDIA_TYPE, $response->getHeaderLine('Content-Type'));
    }

    public function test_unknown_namespaced_parameter_is_rejected(): void
    {
        try {
            $this->api->handle($this->request('/articles/1?relfield:feilds[articles]=version'));
            $this->fail('Expected unknown parameter to be rejected.');
        } catch (InvalidQueryParameterException $e) {
            $this->assertSame('relfield:feilds[articles]', $e->parameter);
        }
    }

    public function test_parameter_is_documented_in_openapi(): void
    {
        $definition = (new OpenApiGenerator())->generate($this->api);

        $parameters = array_column(
            $definition['paths']['/articles/{id}']['get']['parameters'],
            null,
            'name',
        );

        $this->assertStringEndsWith(
            'Requires the ' . Relfield::URI . ' extension.',
            $parameters[Relfield::PARAMETER]['description'],
        );
    }
}
