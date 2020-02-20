<?php

declare(strict_types=1);

namespace ElasticApm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait CachedSingletonInstanceTrait
{
    /** @var self */
    private static $cachedInstance;

    /**
     * Constructor is hidden because create() should be used instead.
     */
    /** @noinspection PhpUnused */
    private function __construct()
    {
    }

    public static function create(): self
    {
        if (!isset(self::$cachedInstance)) {
            self::$cachedInstance = new self();
        }
        return self::$cachedInstance;
    }
}
