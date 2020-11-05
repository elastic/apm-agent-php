<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ElasticApmExtensionUtil
{
    use StaticClassTrait;

    public const EXTENSION_NAME = 'elastic_apm';

    /** @var bool */
    private static $isLoaded;

    public static function isLoaded(): bool
    {
        if (!isset(self::$isLoaded)) {
            self::$isLoaded = extension_loaded(self::EXTENSION_NAME);
        }

        return self::$isLoaded;
    }
}
