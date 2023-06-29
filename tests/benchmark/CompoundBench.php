<?php

namespace Tobyz\Tests\JsonApiServer\benchmark;

use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes\BeforeMethods;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\MockAdapter;

error_reporting(E_ALL & ~E_DEPRECATED);

#[BeforeMethods('setUp')]
class CompoundBench
{
    private $api;

    public function setUp()
    {
        $this->api = new JsonApi('/');

        $this->api->resourceType('people', new MockAdapter([
            ($user2 = (object) ['id' => '2']),
            ($user9 = (object) [
                'id' => '9',
                'firstName' => 'Dan',
                'lastName' => 'Gebhardt',
                'twitter' => 'dgeb',
            ]),
        ]), function ($type) {
            $type->attribute('firstName');
            $type->attribute('lastName');
            $type->attribute('twitter');
        });

        $this->api->resourceType('comments', new MockAdapter([
            ($comment5 = (object) ['id' => '5', 'body' => 'First!', 'author' => $user2]),
            ($comment12 = (object) [
                'id' => '12',
                'body' => 'I like XML better',
                'author' => $user9,
            ]),
        ]), function ($type) {
            $type->attribute('body');
            $type->hasOne('author')->type('people');
        });

        $this->api->resourceType('articles', new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'title' => 'JSON:API paints my bikeshed!',
                'author' => $user9,
                'comments' => [$comment5, $comment12],
            ],
        ]), function ($type) {
            $type->attribute('title');
            $type->hasOne('author')->type('people')->includable();
            $type->hasMany('comments')->includable();
        });
    }

    /**
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchCompound(): void
    {
        $this->api->handle(new ServerRequest('GET', '/articles?include=author,comments'));
    }
}
