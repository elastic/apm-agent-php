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
final class AppCodeHostKind implements LoggableInterface
{
    use LoggableTrait;

    /** @var ?self */
    private static $builtinHttpServer = null;

    /** @var ?self */
    private static $cliScript = null;

    /** @var string */
    private $asString;

    /** @var bool */
    private $isHttp;

    private function __construct(string $asString, bool $isHttp)
    {
        $this->asString = $asString;
        $this->isHttp = $isHttp;
    }

    public function asString(): string
    {
        return $this->asString;
    }

    public function isHttp(): bool
    {
        return $this->isHttp;
    }

    private static function ensureInited(): void
    {
        if (self::$cliScript !== null) {
            return;
        }

        self::$builtinHttpServer = new self('Built-in HTTP server', /* isHttp: */ true);
        self::$cliScript = new self('CLI script', /* isHttp: */ false);
    }

    public static function builtinHttpServer(): self
    {
        self::ensureInited();
        return self::$builtinHttpServer; // @phpstan-ignore-line
    }

    public static function cliScript(): self
    {
        self::ensureInited();
        return self::$cliScript; // @phpstan-ignore-line
    }
}
