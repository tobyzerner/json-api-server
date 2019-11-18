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

use Illuminate\Support\Facades\Validator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Schema\Field;

function rules($rules, array $messages = [], array $customAttributes = [])
{
    if (is_string($rules)) {
        $rules = [$rules];
    }

    return function (callable $fail, $value, $model, Request $request, Field $field) use ($rules, $messages, $customAttributes) {
        $key = $field->getName();
        $validationRules = [$key => []];

        foreach ($rules as $k => $v) {
            if (! is_numeric($k)) {
                $validationRules[$key.'.'.$k] = $v;
            } else {
                $validationRules[$key][] = $v;
            }
        }

        $validation = Validator::make($value !== null ? [$key => $value] : [], $validationRules, $messages, $customAttributes);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $fail($message);
            }
        }
    };
}
