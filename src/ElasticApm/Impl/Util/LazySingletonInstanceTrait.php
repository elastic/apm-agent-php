<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait LazySingletonInstanceTrait
{
    /**
     * Constructor is hidden because instance() should be used instead
     */
    use HiddenConstructorTrait;

    /** @var self */
    private static $singletonInstance;

    public static function instance(): self
    {
        if (!isset(self::$singletonInstance)) {
            self::$singletonInstance = new self();
        }
        return self::$singletonInstance;
    }
}
