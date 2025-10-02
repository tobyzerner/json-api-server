<?php

namespace Tobyz\Tests\JsonApiServer\benchmark;

use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes as Bench;
use Tobyz\JsonApiServer\Endpoint;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\MockResource;

#[Bench\BeforeMethods('setUp')]
class SerializationBench
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $users = [];
        $posts = [];
        $comments = [];

        for ($i = 1; $i <= 100; $i++) {
            $users[] = (object) [
                'id' => (string) $i,
                'name' => "User $i",
                'email' => "user$i@example.com",
            ];
        }

        $postId = 1;
        foreach ($users as $user) {
            for ($j = 1; $j <= 5; $j++) {
                $posts[] = (object) [
                    'id' => (string) $postId,
                    'title' => "Post $postId",
                    'body' => "This is the body of post $postId",
                    'author' => $user,
                    'comments' => [],
                ];
                $postId++;
            }
        }

        $commentId = 1;
        foreach ($posts as $post) {
            for ($k = 1; $k <= 10; $k++) {
                $author = $users[array_rand($users)];
                $comment = (object) [
                    'id' => (string) $commentId,
                    'body' => "This is comment $commentId",
                    'author' => $author,
                    'post' => $post,
                ];
                $comments[] = $comment;
                $post->comments[] = $comment;
                $commentId++;
            }
        }

        $this->api->resource(
            new MockResource(
                'users',
                models: $users,
                endpoints: [
                    Endpoint\Index::make(),
                    Endpoint\Show::make(),
                    Endpoint\Create::make(),
                    Endpoint\Update::make(),
                    Endpoint\Delete::make(),
                ],
                fields: [Attribute::make('name'), Attribute::make('email')],
            ),
        );

        $this->api->resource(
            new MockResource(
                'posts',
                models: $posts,
                endpoints: [
                    Endpoint\Index::make(),
                    Endpoint\Show::make(),
                    Endpoint\Create::make(),
                    Endpoint\Update::make(),
                    Endpoint\Delete::make(),
                ],
                fields: [
                    Attribute::make('title'),
                    Attribute::make('body'),
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                    ToMany::make('comments')
                        ->type('comments')
                        ->includable()
                        ->withLinkage(),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'comments',
                models: $comments,
                endpoints: [
                    Endpoint\Index::make(),
                    Endpoint\Show::make(),
                    Endpoint\Create::make(),
                    Endpoint\Update::make(),
                    Endpoint\Delete::make(),
                ],
                fields: [
                    Attribute::make('body'),
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                    ToOne::make('post')
                        ->type('posts')
                        ->includable(),
                ],
            ),
        );
    }

    /**
     * Simple resource list without includes (baseline, 500 posts)
     */
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    public function benchSimple(): void
    {
        $this->api->handle(new ServerRequest('GET', '/posts'));
    }

    /**
     * Complex compound document with nested includes, ToMany relationships,
     * and sparse fieldsets (500 posts + authors + 5000 comments + comment authors)
     */
    #[Bench\Revs(5)]
    #[Bench\Iterations(5)]
    public function benchComplex(): void
    {
        $this->api->handle(
            new ServerRequest(
                'GET',
                '/posts?include=author,comments.author,comments.post&fields[posts]=title,author,comments&fields[users]=name',
            ),
        );
    }
}
