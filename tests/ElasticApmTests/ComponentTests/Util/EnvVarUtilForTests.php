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

use Elastic\Apm\Impl\Util\StaticClassTrait;
use PHPUnit\Framework\Assert;

final class EnvVarUtilForTests
{
    use StaticClassTrait;

    public static function get(string $envVarName): ?string
    {
        $envVarValue = getenv($envVarName, /* local_only: */ true);
        return $envVarValue === false ? null : $envVarValue;
    }

    public static function set(string $envVarName, string $envVarValue): void
    {
        Assert::assertTrue(putenv($envVarName . '=' . $envVarValue));
        Assert::assertSame($envVarValue, self::get($envVarName));
    }

    public static function unset(string $envVarName): void
    {
        Assert::assertTrue(putenv($envVarName));
        Assert::assertNull(self::get($envVarName));
    }

    public static function setOrUnset(string $envVarName, ?string $envVarValue): void
    {
        if ($envVarValue === null) {
            self::unset($envVarName);
        } else {
            self::set($envVarName, $envVarValue);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        return getenv();
    }
}
