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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AgentConfigSourceKind implements LoggableInterface
{
    use LoggableTrait;

    /** @var ?self */
    private static $iniFile = null;

    /** @var ?self */
    private static $envVars = null;

    /** @var null|self[] */
    private static $all = null;

    /** @var string */
    private $asString;

    private function __construct(string $dbgDesc)
    {
        $this->asString = $dbgDesc;
    }

    public function asString(): string
    {
        return $this->asString;
    }

    private static function ensureInited(): void
    {
        if (self::$all !== null) {
            return;
        }

        self::$envVars = new self('Environment variables');
        self::$iniFile = new self('INI file');

        // INI has higher priority
        self::$all = [self::$iniFile, self::$envVars];
    }

    public static function iniFile(): self
    {
        self::ensureInited();
        return self::$iniFile; // @phpstan-ignore-line
    }

    public static function envVars(): self
    {
        self::ensureInited();
        return self::$envVars; // @phpstan-ignore-line
    }

    /**
     * @return self[]
     */
    public static function all(): array
    {
        self::ensureInited();
        return self::$all; // @phpstan-ignore-line
    }
}
