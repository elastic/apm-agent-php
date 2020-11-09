<?php

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

    public static function extractPathPart(string $url): ?string
    {
        $result = parse_url($url, PHP_URL_PATH);
        if (!is_string($result)) {
            return null;
        }
        return $result;
    }
}
