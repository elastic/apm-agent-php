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

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\MySQLi;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use PHPUnit\Framework\TestCase;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ApiKind implements LoggableInterface
{
    use LoggableTrait;

    /** @var ?self */
    private static $procedural = null;

    /** @var ?self */
    private static $oop = null;

    /** @var self[] */
    private static $allValues;

    /** @var string */
    private $asString;

    private function __construct(string $asString)
    {
        $this->asString = $asString;
    }

    public function asString(): string
    {
        return $this->asString;
    }

    public function isOOP(): bool
    {
        return $this === self::$oop;
    }

    public static function fromString(string $asString): ApiKind
    {
        foreach (self::allValues() as $val) {
            if ($val->asString() === $asString) {
                return $val;
            }
        }
        TestCase::fail(LoggableToString::convert(['$asString' => $asString]));
    }

    private static function ensureInited(): void
    {
        if (self::$procedural !== null) {
            return;
        }

        self::$oop = new self('object-oriented programming');
        self::$procedural = new self('procedural');
        self::$allValues = [self::$oop, self::$procedural];
    }

    public static function procedural(): self
    {
        self::ensureInited();
        /** @var self self::$procedural */
        return self::$procedural;
    }

    public static function oop(): self
    {
        self::ensureInited();
        /** @var self self::$oop */
        return self::$oop;
    }

    /**
     * @return self[]
     */
    public static function allValues(): array
    {
        self::ensureInited();
        return self::$allValues;
    }
}
