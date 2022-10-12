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
    private $intProp = 123; // @phpstan-ignore-line

    /** @var string */
    private $stringProp = 'Abc'; // @phpstan-ignore-line

    /** @var ?string */
    private $nullableStringProp = null; // @phpstan-ignore-line

    /** @var string */
    private $excludedProp = 'excludedProp value'; // @phpstan-ignore-line

    /** @var string */
    public $lateInitProp;

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
