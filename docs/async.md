# Asynchronous Processing

This library supports the
[JSON:API Asynchronous Processing](https://jsonapi.org/recommendations/#asynchronous-processing)
recommendation, which provides a standardized way to handle long-running
resource creation operations.

## Overview

The async processing pattern works as follows:

1. **Async Creation** (202 Accepted): Client creates a resource that takes time
   to process. Server returns a `202 Accepted` status with a `Content-Location`
   or `Location` header pointing to a job resource.
2. **Job Polling** (200 OK): Client polls the job resource to check status.
   Server returns `200 OK` with optional `Retry-After` header.
3. **Completion** (303 See Other): When processing completes, job resource
   returns `303 See Other` with a `Location` header pointing to the created
   resource.

## Async Creation

To enable async processing for resource creation, use the `async` method on the
`Create` endpoint:

```php
use Tobyz\JsonApiServer\Endpoint\Create;

class PhotosResource extends Resource
{
    public function endpoints(): array
    {
        return [
            Create::make()->async('jobs', function ($model, Context $context) {
                if ($this->requiresAsyncProcessing($model)) {
                    return Job::create([
                        'status' => 'pending',
                        'resource_type' => 'photos',
                        'resource_data' => $model,
                    ]);
                }
            }),
        ];
    }
}
```

The `async` method takes two parameters:

- **Collection name**: The name of the collection in which the job resource will
  be found (e.g. `jobs`)
- **Callback**: A function that receives the model and context, and returns:
    - A **job model object**: Will return a `202 Accepted` response containing
      the job resource, and a `Content-Location` header pointing to the job
      resource
    - A **string path**: Will return a `202 Accepted` response with the string
      as the `Location`
    - **null**: Falls back to synchronous processing with a normal `201 Created`
      response

The callback is invoked after the data pipeline has validated and filled the
model, but before it's persisted to storage.

## Job Polling

Use custom headers on your job resource's `Show` endpoint to provide polling
guidance:

```php
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Schema\Header;
use Tobyz\JsonApiServer\Schema\Type\Integer;

class JobsResource extends Resource
{
    public function endpoints(): array
    {
        return [
            Show::make()->headers([
                Header::make('Retry-After')
                    ->type(Integer::make())
                    ->nullable()
                    ->get(
                        fn($model) => $model->status === 'pending' ? 60 : null,
                    ),
            ]),
        ];
    }
}
```

## Completion with See Other

When a job completes, use the `seeOther` convenience method to redirect clients
to the created resource:

```php
use Tobyz\JsonApiServer\Endpoint\Show;

class JobsResource extends Resource
{
    public function endpoints(): array
    {
        return [
            Show::make()->seeOther(function ($model, Context $context) {
                if ($model->status === 'completed') {
                    return "photos/$model->result_id";
                }
            }),
        ];
    }
}
```

The `seeOther` method automatically:

- Returns a `303 See Other` response when the callback returns a value, with the
  returned string as the `Location` header
- Adds OpenAPI schema for the 303 response
