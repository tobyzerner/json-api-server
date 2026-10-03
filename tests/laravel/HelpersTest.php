<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\laravel\Models\User;

use function Tobyz\JsonApiServer\Laravel\authenticated;
use function Tobyz\JsonApiServer\Laravel\can;
use function Tobyz\JsonApiServer\Laravel\rules;

class HelpersTest extends LaravelTestCase
{
    public function test_rules_validate_with_laravel_validator()
    {
        $this->resources(fields: ['users' => [
            Attribute::make('name')->writable()->validate(rules('max:5')),
            Attribute::make('email')
                ->writable()
                ->validate(rules(['email'])),
        ]]);

        $errors = $this->errors(fn() => $this->create('users', ['name' => 'Tobias', 'email' => 'nope']));

        $this->assertSame(
            [
                ['The name may not be greater than 5 characters.', '/data/attributes/name'],
                ['The email must be a valid email address.',       '/data/attributes/email'],
            ],
            $errors,
        );

        $this->create('users', ['name' => 'Toby', 'email' => 'toby@example.com']);
        $this->assertSame('Toby', User::find(1)->name);
    }

    public function test_rules_replace_id_placeholder_with_model_key()
    {
        User::create(['name' => 'Toby', 'email' => 'toby@example.com']);
        User::create(['name' => 'Franz', 'email' => 'franz@example.com']);

        $this->resources(fields: ['users' => [
            Attribute::make('email')->writable()->validate(rules('unique:users,email,{id}')),
        ]]);

        // Keeping your own email passes the unique rule.
        $this->update('users', '1', ['email' => 'toby@example.com']);

        $this->assertSame(
            [['The email has already been taken.', '/data/attributes/email']],
            $this->errors(fn() => $this->update('users', '1', ['email' => 'franz@example.com'])),
        );

        // New models have no key to exclude.
        $this->assertSame(
            [['The email has already been taken.', '/data/attributes/email']],
            $this->errors(fn() => $this->create('users', ['email' => 'toby@example.com'])),
        );
    }

    public function test_rules_with_nested_keys()
    {
        $this->resources(fields: ['users' => [
            Attribute::make('name')->writable(),
            Attribute::make('meta')
                ->writable()
                ->validate(rules(['nickname' => 'required'])),
        ]]);

        $this->assertSame(
            [['The meta.nickname field is required.', '/data/attributes/meta']],
            $this->errors(fn() => $this->create('users', ['name' => 'Toby', 'meta' => ['other' => 1]])),
        );
    }

    public function test_authenticated()
    {
        User::create(['name' => 'Toby', 'email' => 'toby@example.com']);

        $this->resources(fields: ['users' => [
            Attribute::make('name'),
            Attribute::make('email')->visible(authenticated()),
        ]]);

        $this->assertArrayNotHasKey('email', $this->attributes());

        $this->authenticated = true;

        $this->assertSame('toby@example.com', $this->attributes()['email']);
    }

    public function test_can_passes_model_to_gate()
    {
        User::create(['name' => 'Toby', 'email' => 'toby@example.com']);

        $this->resources(fields: ['users' => [
            Attribute::make('name'),
            Attribute::make('email')->visible(can('viewEmail')),
        ]]);

        $this->assertArrayNotHasKey('email', $this->attributes());

        $this->assertSame(['viewEmail'], $this->gateCalls[0][0]);
        $this->assertInstanceOf(User::class, $this->gateCalls[0][1][0]);
        $this->assertSame(1, $this->gateCalls[0][1][0]->id);

        $this->abilities['viewEmail'] = true;

        $this->assertSame('toby@example.com', $this->attributes()['email']);
    }

    public function test_can_with_explicit_arguments_and_multiple_abilities()
    {
        User::create(['name' => 'Toby', 'email' => 'toby@example.com']);

        $this->resources(fields: ['users' => [
            Attribute::make('name'),
            Attribute::make('email')->visible(can(['viewEmail', 'admin'], 'arg')),
        ]]);

        $this->abilities['admin'] = true;

        $this->assertSame('toby@example.com', $this->attributes()['email']);
        $this->assertSame([['viewEmail', 'admin'], ['arg']], $this->gateCalls[0]);
    }

    private function attributes(): array
    {
        return $this->document($this->get('/users/1'))['data']['attributes'];
    }

    /**
     * @return array<array{0: string, 1: string}> Error details and pointers.
     */
    private function errors(callable $callback): array
    {
        $e = $this->exception($callback);

        $this->assertInstanceOf(JsonApiErrorsException::class, $e);

        return array_map(fn($error) => [
            $error->getJsonApiError()['detail'],
            $error->getJsonApiError()['source']['pointer'],
        ], $e->errors);
    }
}
