<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class UrlUtil
{
    use StaticClassTrait;

    public static function splitHostPort(string $hostPort, ?string &$host, ?int &$port): bool
    {
        $lastColonPos = strrpos($hostPort, ':');
        if ($lastColonPos === false) {
            $doesIncludePort = false;
        } else {
            $lastClosingSquareBracketPos = strrpos($hostPort, ']');
            if ($lastClosingSquareBracketPos === false) {
                // There is no closing square bracket
                $firstColonPos = strpos($hostPort, ':');
                // If there is only one colon it means the host is NOT IPv6 and there is a port after the colon
                // otherwise there is more than colon it means the host is IPv6 but there is no closing square bracket
                // which means there is no port
                $doesIncludePort = ($firstColonPos === $lastColonPos);
            } else {
                // $lastClosingSquareBracketPos is the position of the last closing square bracket
                // If the last closing square bracket is before the last colon
                // it means the part after the last colon is a potential port
                // otherwise the last colon is inside square brackets which means it's IPv6 and there's no port
                $doesIncludePort = ($lastClosingSquareBracketPos < $lastColonPos);
            }
        }

        if (!$doesIncludePort) {
            $host = self::stripSquareBrackets($hostPort);
            $port = null;
            return true;
        }

        /** @var int $lastColonPos */
        $host = ($lastColonPos === 0) ? null : self::stripSquareBrackets(substr($hostPort, 0, $lastColonPos));

        if ($lastColonPos === (strlen($hostPort) - 1)) {
            $port = null;
        } else {
            $portPart = trim(substr($hostPort, $lastColonPos + 1));
            $port = is_numeric($portPart) ? intval($portPart) : null;
        }

        return true;
    }

    private static function stripSquareBrackets(string $host): string
    {
        $hostTrimmed = trim($host);
        return
            (TextUtil::isPrefixOf('[', $hostTrimmed) && TextUtil::isSuffixOf(']', $hostTrimmed))
                ? trim(substr($hostTrimmed, 1, -1))
                : $hostTrimmed;
    }

    public static function splitPathQuery(string $pathQuery, ?string &$path, ?string &$query): bool
    {
        $parsedUrl = parse_url($pathQuery);
        if (!is_array($parsedUrl)) {
            return false;
        }

        $pathPart = ArrayUtil::getValueIfKeyExistsElse('path', $parsedUrl, null);
        if ($pathPart !== null && !is_string($pathPart)) {
            $pathPart = null;
        }
        $path = $pathPart;

        $queryPart = ArrayUtil::getValueIfKeyExistsElse('query', $parsedUrl, null);
        if ($queryPart !== null && !is_string($queryPart)) {
            $queryPart = null;
        }
        $query = $queryPart;

        return true;
    }

    public static function extractHostPart(string $url): ?string
    {
        $result = parse_url($url, PHP_URL_HOST);
        if (!is_string($result)) {
            return null;
        }
        return $result;
    }

    public static function isHttp(string $url): bool
    {
        /** @noinspection HttpUrlsUsage */
        return TextUtil::isPrefixOf('http://', $url, /* isCaseSensitive */ false)
               || TextUtil::isPrefixOf('https://', $url, /* isCaseSensitive */ false);
    }

    public static function defaultPortForScheme(string $scheme): ?int
    {
        if (strcasecmp($scheme, 'http') === 0) {
            return 80;
        }
        if (strcasecmp($scheme, 'https') === 0) {
            return 443;
        }

        return null;
    }

    public static function buildRequestBaseUrl(UrlParts $urlParts): string
    {
        $result = ($urlParts->scheme ?? 'http') . '://';
        $result .= $urlParts->host ?? 'localhost';
        if ($urlParts->port !== null) {
            $result .= ':' . $urlParts->port;
        }
        return $result;
    }

    public static function normalizeUrlPath(string $urlPath): string
    {
         return TextUtil::isPrefixOf('/', $urlPath) ? $urlPath : ('/' . $urlPath);
    }

    public static function buildRequestMethodArg(UrlParts $urlParts): string
    {
        $result = $urlParts->path === null
            ? '/'
            : self::normalizeUrlPath($urlParts->path);

        if ($urlParts->query !== null) {
            $result .= '?' . $urlParts->query;
        }

        return $result;
    }

    public static function buildFullUrl(UrlParts $urlParts): string
    {
        return self::buildRequestBaseUrl($urlParts) . self::buildRequestMethodArg($urlParts);
    }
}
