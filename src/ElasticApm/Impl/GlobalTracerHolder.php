<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class GlobalTracerHolder
{
    use StaticClassTrait;

    /** @var TracerInterface|null */
    private static $singletonInstance = null;

    public static function isSet(): bool
    {
        return !is_null(self::$singletonInstance);
    }

    public static function get(): TracerInterface
    {
        if (is_null(self::$singletonInstance)) {
            self::$singletonInstance = TracerBuilder::startNew()->build();
        }
        return self::$singletonInstance;
    }

    public static function set(TracerInterface $newInstance): void
    {
        self::$singletonInstance = $newInstance;
    }

    public static function unset(): void
    {
        self::$singletonInstance = null;
    }
}
