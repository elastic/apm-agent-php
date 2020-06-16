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

    /** @var TracerInterface */
    private static $singletonInstance;

    public static function isSet(): bool
    {
        return isset(self::$singletonInstance);
    }

    public static function get(): TracerInterface
    {
        if (!isset(self::$singletonInstance)) {
            self::reset();
        }
        return self::$singletonInstance;
    }

    public static function set(TracerInterface $newInstance): void
    {
        self::$singletonInstance = $newInstance;
    }

    public static function reset(): void
    {
        self::$singletonInstance = TracerBuilder::startNew()->build();
    }
}
