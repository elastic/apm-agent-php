<?php

declare(strict_types=1);

namespace ElasticApm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait NoopObjectTrait
{
    /** @var self */
    private static $cachedInstance;

    public static function create(): self
    {
        if (!isset(self::$cachedInstance)) {
            self::$cachedInstance = new self();
        }
        return self::$cachedInstance;
    }

    public function isNoop(): bool
    {
        return true;
    }
}
