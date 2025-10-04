<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Header;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class AsyncProcessingTest extends AbstractTestCase
{
    private JsonApi $api;
    private MockResource $jobsResource;
    private MockResource $photosResource;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->jobsResource = new MockResource(
            'jobs',
            endpoints: [Show::make()],
            fields: [
                Attribute::make('status'),
                Attribute::make('done'),
                Attribute::make('result_id'),
            ],
        );

        $jobsResource = $this->jobsResource;

        $this->photosResource = new MockResource(
            'photos',
            endpoints: [
                Create::make()->async('jobs', function ($model, $context) use ($jobsResource) {
                    // Simulate creating a job for async processing
                    $job = (object) [
                        'id' => 'job-1',
                        'status' => 'pending',
                        'done' => false,
                        'result_id' => null,
                    ];

                    // Add the job to the jobs resource for later retrieval
                    $jobsResource->models[] = $job;

                    return $job;
                }),
                Show::make(),
            ],
            fields: [Attribute::make('title')->writable()],
        );

        $this->api->resource($this->jobsResource);
        $this->api->resource($this->photosResource);
    }

    public function test_async_creation_returns_202_with_content_location_for_object_return()
    {
        $response = $this->api->handle(
            $this->buildRequest('POST', '/photos')->withParsedBody([
                'data' => [
                    'type' => 'photos',
                    'attributes' => [
                        'title' => 'My Photo',
                    ],
                ],
            ]),
        );

        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEquals('/jobs/job-1', $response->getHeaderLine('Content-Location'));
    }

    public function test_async_creation_returns_202_with_content_location_for_string_return()
    {
        $this->photosResource = new MockResource(
            'photos',
            endpoints: [
                Create::make()->async('jobs', function ($model, $context) {
                    return 'jobs/job-2';
                }),
            ],
            fields: [Attribute::make('title')->writable()],
        );

        $this->api = new JsonApi();
        $this->api->resource($this->photosResource);

        $response = $this->api->handle(
            $this->buildRequest('POST', '/photos')->withParsedBody([
                'data' => [
                    'type' => 'photos',
                    'attributes' => [
                        'title' => 'My Photo',
                    ],
                ],
            ]),
        );

        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEmpty((string) $response->getBody());
        $this->assertEquals('/jobs/job-2', $response->getHeaderLine('Location'));
    }

    public function test_async_creation_returns_201_when_callback_returns_null()
    {
        $this->photosResource = new MockResource(
            'photos',
            endpoints: [
                Create::make()->async('jobs', function ($model, $context) {
                    return null; // Process synchronously
                }),
                Show::make(),
            ],
            fields: [Attribute::make('title')->writable()],
        );

        $this->api = new JsonApi();
        $this->api->resource($this->photosResource);

        $response = $this->api->handle(
            $this->buildRequest('POST', '/photos')->withParsedBody([
                'data' => [
                    'type' => 'photos',
                    'attributes' => [
                        'title' => 'My Photo',
                    ],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertStringContainsString('"type":"photos"', $response->getBody());
    }

    public function test_job_polling_with_retry_after_header()
    {
        $job = (object) [
            'id' => 'job-1',
            'status' => 'pending',
            'done' => false,
            'retry_after' => 60,
        ];

        $this->jobsResource->models[] = $job;

        $this->jobsResource = new MockResource(
            'jobs',
            models: [$job],
            endpoints: [
                Show::make()->headers([
                    Header::make('Retry-After')
                        ->type(Integer::make())
                        ->get(fn($model) => $model->done ? null : $model->retry_after),
                ]),
            ],
            fields: [Attribute::make('status'), Attribute::make('done')],
        );

        $this->api = new JsonApi();
        $this->api->resource($this->jobsResource);

        $response = $this->api->handle($this->buildRequest('GET', '/jobs/job-1'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('60', $response->getHeaderLine('Retry-After'));
        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'jobs',
                    'id' => 'job-1',
                    'attributes' => [
                        'status' => 'pending',
                        'done' => false,
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_completed_job_with_string_path_returns_303()
    {
        $completedJob = (object) [
            'id' => 'job-1',
            'status' => 'completed',
            'done' => true,
            'result_id' => 'photo-1',
        ];

        $this->jobsResource = new MockResource(
            'jobs',
            models: [$completedJob],
            endpoints: [
                Show::make()->seeOther(function ($model, $context) {
                    if ($model->done) {
                        return "photos/{$model->result_id}";
                    }
                    return null;
                }),
            ],
            fields: [Attribute::make('status'), Attribute::make('done')],
        );

        $this->api = new JsonApi();
        $this->api->resource($this->jobsResource);

        $response = $this->api->handle($this->buildRequest('GET', '/jobs/job-1'));

        $this->assertEquals(303, $response->getStatusCode());
        $this->assertEquals('/photos/photo-1', $response->getHeaderLine('Location'));
    }

    public function test_custom_response_callback()
    {
        $model = (object) ['id' => '1', 'custom' => true];

        $resource = new MockResource(
            'items',
            models: [$model],
            endpoints: [
                Show::make()->response(function ($response, $model, $context) {
                    if ($model->custom) {
                        return $response->withHeader('X-Custom', 'true');
                    }
                    return $response;
                }),
            ],
        );

        $this->api = new JsonApi();
        $this->api->resource($resource);

        $response = $this->api->handle($this->buildRequest('GET', '/items/1'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('true', $response->getHeaderLine('X-Custom'));
    }
}
