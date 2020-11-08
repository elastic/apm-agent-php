<?php

/** @noinspection PhpUnusedPrivateFieldInspection, PhpPrivateFieldCanBeLocalVariableInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\LogTests;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ClassNameUtil;

class ObjectForLoggableTraitTests implements LoggableInterface
{
    use LoggableTrait;

    /** @var bool */
    private static $shouldExcludeProp = true;

    /** @var ?string */
    private static $logWithClassNameValue;

    /** @var int */
    private $intProp = 123;

    /** @var string */
    private $stringProp = 'Abc';

    /** @var ?string */
    private $nullableStringProp = null;

    /** @var string */
    private $excludedProp = 'excludedProp value';

    public static function logWithoutClassName(): void
    {
        self::$logWithClassNameValue = null;
    }

    public static function logWithCustomClassName(string $className): void
    {
        self::$logWithClassNameValue = $className;
    }

    public static function logWithShortClassName(): void
    {
        self::$logWithClassNameValue = ClassNameUtil::fqToShort(static::class);
    }

    protected static function classNameToLog(): ?string
    {
        return self::$logWithClassNameValue;
    }

    public static function shouldExcludeProp(bool $shouldExcludeProp = true): void
    {
        self::$shouldExcludeProp = $shouldExcludeProp;
    }

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLogImpl(): array
    {
        return ['excludedProp'];
    }

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return self::$shouldExcludeProp ? static::propertiesExcludedFromLogImpl() : [];
    }
}
