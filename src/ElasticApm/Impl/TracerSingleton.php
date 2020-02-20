<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\StaticClassTrait;
use ElasticApm\TracerInterface;

final class TracerSingleton
{
    use StaticClassTrait;

    /** @var TracerInterface */
    private static $singletonInstance;

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
