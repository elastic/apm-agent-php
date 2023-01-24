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

namespace ElasticApmTests\Util;

use Closure;
use Elastic\Apm\Impl\Config\BoolOptionParser;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\ComponentTests\Util\EnvVarUtilForTests;
use PHPUnit\Framework\ExpectationFailedException;

final class FlakyAssertions
{
    use StaticClassTrait;

    private const ENABLED_ENV_VAR_NAME = 'ELASTIC_APM_PHP_TESTS_FLAKY_ASSERTIONS_ENABLED';
    private const ENABLED_DEFAULT_VALUE = false;

    /** @var ?bool */
    private static $areEnabled = null;

    public static function setEnabled(bool $areEnabled): void
    {
        self::$areEnabled = $areEnabled;
    }

    private static function areEnabled(): bool
    {
        if (self::$areEnabled === null) {
            $envVarValue = EnvVarUtilForTests::get(self::ENABLED_ENV_VAR_NAME);
            if ($envVarValue === null) {
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

            $logger = TestCaseBase::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
            ($loggerProxy = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($ex, 'Flaky assertions are disabled but one has just failed');
        }
    }
}
