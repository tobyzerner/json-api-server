<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Pagination\MaxPageSizeExceededException;
use Tobyz\JsonApiServer\Exception\Pagination\RangePaginationNotSupportedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class CursorPaginationTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'articles',
                models: array_map(fn($i) => (object) ['id' => (string) $i], range(1, 10)),
                endpoints: [Index::make()->cursorPaginate(2, 5)],
            ),
        );
    }

    public function test_includes_profile_in_content_type(): void
    {
        $response = $this->api->handle($this->buildRequest('GET', '/articles'));

        $this->assertEquals(
            'application/vnd.api+json; profile="https://jsonapi.org/profiles/ethanresnick/cursor-pagination"',
            $response->getHeaderLine('Content-Type'),
        );
    }

    public function test_first_page_includes_expected_links_and_item_cursors(): void
    {
        $response = $this->api->handle($this->buildRequest('GET', '/articles'));

        $document = json_decode($response->getBody(), true);

        $this->assertEquals('1', $document['data'][0]['id'] ?? null);
        $this->assertEquals('2', $document['data'][1]['id'] ?? null);

        $this->assertEquals(10, $document['meta']['page']['total'] ?? null);

        $this->assertNull($document['links']['prev'] ?? null);
        $this->assertEquals('/articles?page%5Bafter%5D=2', $document['links']['next'] ?? null);
    }

    public function test_after_parameter_returns_expected_page_and_links(): void
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['after' => '2', 'size' => '2'],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(['3', '4'], array_column($document['data'], 'id'));
        $this->assertEquals(
            '/articles?page%5Bafter%5D=4&page%5Bsize%5D=2',
            $document['links']['next'] ?? null,
        );
        $this->assertEquals(
            '/articles?page%5Bsize%5D=2&page%5Bbefore%5D=3',
            $document['links']['prev'] ?? null,
        );
    }

    public function test_before_parameter_returns_prior_page(): void
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['before' => '5', 'size' => '2'],
            ]),
        );

        $document = json_decode($response->getBody(), true);

        $this->assertEquals(['3', '4'], array_column($document['data'], 'id'));
        $this->assertEquals(
            '/articles?page%5Bsize%5D=2&page%5Bafter%5D=4',
            $document['links']['next'] ?? null,
        );
        $this->assertEquals(
            '/articles?page%5Bbefore%5D=3&page%5Bsize%5D=2',
            $document['links']['prev'] ?? null,
        );
    }

    public function test_range_pagination_not_supported_error(): void
    {
        try {
            $this->api->handle(
                $this->buildRequest('GET', '/articles')->withQueryParams([
                    'page' => ['after' => '2', 'before' => '5', 'size' => '2'],
                ]),
            );

            $this->fail('RangePaginationNotSupportedException was not thrown');
        } catch (RangePaginationNotSupportedException $exception) {
            $error = $exception->getJsonApiError() ?? [];

            $this->assertEquals('400', $error['status'] ?? null);
            $this->assertEquals(
                [
                    'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/range-pagination-not-supported',
                ],
                $error['links']['type'] ?? null,
            );
        }
    }

    public function test_max_size_exceeded_error_contains_meta(): void
    {
        $this->expectException(MaxPageSizeExceededException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['size' => '51'],
            ]),
        );
    }

    public function test_invalid_size_returns_bad_request(): void
    {
        try {
            $this->api->handle(
                $this->buildRequest('GET', '/articles')->withQueryParams([
                    'page' => ['size' => '0'],
                ]),
            );

            $this->fail('BadRequestException was not thrown');
        } catch (BadRequestException $exception) {
            $error = $exception->getJsonApiError() ?? [];

            $this->assertEquals('400', $error['status'] ?? null);
            $this->assertEquals('page[size]', $error['source']['parameter'] ?? null);
        }
    }

    public function test_unknown_cursor_returns_invalid_parameter_error(): void
    {
        try {
            $this->api->handle(
                $this->buildRequest('GET', '/articles')->withQueryParams([
                    'page' => ['after' => '999', 'size' => '2'],
                ]),
            );

            $this->fail('BadRequestException was not thrown');
        } catch (BadRequestException $exception) {
            $error = $exception->getJsonApiError() ?? [];

            $this->assertEquals('400', $error['status'] ?? null);
            $this->assertStringContainsString('after', $error['source']['parameter'] ?? '');
        }
    }
}
