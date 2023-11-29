<?php

namespace Tobyz\Tests\JsonApiServer\benchmark;

use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\Tests\JsonApiServer\MockResource;

#[BeforeMethods('setUp')]
class CompoundBench
{
    private JsonApi $api;

    public function setUp()
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'people',
                models: [
                    ($user2 = (object) ['id' => '2']),
                    ($user9 = (object) [
                        'id' => '9',
                        'firstName' => 'Dan',
                        'lastName' => 'Gebhardt',
                        'twitter' => 'dgeb',
                    ]),
                ],
                fields: [Str::make('firstName'), Str::make('lastName'), Str::make('twitter')],
            ),
        );

        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment5 = (object) ['id' => '5', 'body' => 'First!', 'author' => $user2]),
                    ($comment12 = (object) [
                        'id' => '12',
                        'body' => 'I like XML better',
                        'author' => $user9,
                    ]),
                ],
                fields: [Str::make('body'), ToOne::make('author')->type('people')],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [
                    '1' => (object) [
                        'id' => '1',
                        'title' => 'JSON:API paints my bikeshed!',
                        'author' => $user9,
                        'comments' => [$comment5, $comment12],
                    ],
                ],
                endpoints: [Index::make()],
                fields: [
                    Str::make('title'),
                    ToOne::make('author')
                        ->type('people')
                        ->includable(),
                    ToMany::make('comments')->includable(),
                ],
            ),
        );
    }

    #[Revs(1000), Iterations(5)]
    public function benchCompound(): void
    {
        $this->api->handle(new ServerRequest('GET', '/articles?include=author,comments'));
    }
}
