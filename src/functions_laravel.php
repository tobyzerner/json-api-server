<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Laravel;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field;

function rules($rules, array $messages = [], array $customAttributes = []): Closure
{
    if (is_string($rules)) {
        $rules = [$rules];
    }

    return function (callable $fail, $value, $model, Context $context, Field $field) use ($rules, $messages, $customAttributes) {
        $key = $field->getName();
        $validationRules = [$key => []];
        $validationMessages = [$key => []];

        foreach ($rules as $k => $v) {
            if (! is_numeric($k)) {
                $validationRules[$key.'.'.$k] = $v;
            } else {
                $validationRules[$key][] = $v;
            }
        }

        foreach ($messages as $k => $v) {
            if (! is_numeric($k)) {
                $validationMessages[$key.'.'.$k] = $v;
            } else {
                $validationMessages[$key][] = $v;
            }
        }

        $validation = Validator::make($value !== null ? [$key => $value] : [], $validationRules, $validationMessages, $customAttributes);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $fail($message);
            }
        }
    };
}

function authenticated(): Closure
{
    return function () {
        return Auth::check();
    };
}

function can(string $ability): Closure
{
    return function ($arg) use ($ability) {
        return Gate::allows($ability, $arg instanceof Model ? $arg : null);
    };
}
