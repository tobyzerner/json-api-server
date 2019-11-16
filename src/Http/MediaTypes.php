<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Http;

class MediaTypes
{
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Determine whether the list contains the given type without modifications
     *
     * This is meant to ease implementation of JSON:API rules for content
     * negotiation, which demand HTTP error responses e.g. when all of the
     * JSON:API media types in the "Accept" header are modified with "media type
     * parameters". Therefore, this method only returns true when the requested
     * media type is contained without additional parameters (except for the
     * weight parameter "q" and "Accept extension parameters").
     *
     * @param string $mediaType
     * @return bool
     */
    public function containsExactly(string $mediaType): bool
    {
        $types = array_map('trim', explode(',', $this->value));

        // Accept headers can contain multiple media types, so we need to check
        // whether any of them matches.
        foreach ($types as $type) {
            $parts = array_map('trim', explode(';', $type));

            // The actual media type needs to be an exact match
            if (array_shift($parts) !== $mediaType) {
                continue;
            }

            // The media type can optionally be followed by "media type
            // parameters". Parameters after the "q" parameter are considered
            // "Accept extension parameters", which we don't care about. Thus,
            // we have an exact match if there are no parameters at all or if
            // the first one is named "q".
            // See https://tools.ietf.org/html/rfc7231#section-5.3.2.
            if (empty($parts) || substr($parts[0], 0, 2) === 'q=') {
                return true;
            }

            continue;
        }

        return false;
    }
}
