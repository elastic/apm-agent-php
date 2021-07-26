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

use Closure;
use Elastic\Apm\Impl\Config\BoolOptionParser;
use Elastic\Apm\Impl\Log\LoggableToEncodedJson;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\ExpectationFailedException;

final class FlakyAssertions
{
    use StaticClassTrait;

    private const ENABLED_ENV_VAR_NAME = 'ELASTIC_APM_PHP_TESTS_FLAKY_ASSERTIONS_ENABLED';
    private const ENABLED_DEFAULT_VALUE = false;

    /** @var bool */
    private static $areEnabled;

    private static function areEnabled(): bool
    {
        if (!isset(self::$areEnabled)) {
            $envVarValue = getenv(self::ENABLED_ENV_VAR_NAME);
            if ($envVarValue === false) {
                self::$areEnabled = self::ENABLED_DEFAULT_VALUE;
            } else {
                self::$areEnabled = (new BoolOptionParser())->parse($envVarValue);
            }
        }

        return self::$areEnabled;
    }

    /**
     * @param Closure $assertionCall
     * @param bool    $forceEnableFlakyAssertions
     *
     * @phpstan-param Closure(): void $assertionCall
     */
    public static function run(Closure $assertionCall, bool $forceEnableFlakyAssertions = false): void
    {
        try {
            $assertionCall();
        } catch (ExpectationFailedException $ex) {
            if ($forceEnableFlakyAssertions || self::areEnabled()) {
                throw $ex;
            }

            TestCaseBase::printMessage(
                __METHOD__,
                'Flaky assertions are disabled but one has just failed' . PHP_EOL
                . '+-> Exception:' . PHP_EOL
                . LoggableToEncodedJson::convert($ex) . PHP_EOL
                . '+-> Stack trace:' . PHP_EOL
                . $ex->getTraceAsString()
            );
        }
    }
}
