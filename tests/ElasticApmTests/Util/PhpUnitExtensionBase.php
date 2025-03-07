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

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableToJsonEncodable;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggingSubsystem;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use PHPUnit\Runner\BeforeTestHook;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class PhpUnitExtensionBase implements BeforeTestHook
{
    public const LOG_COMPOSITE_DATA_MAX_DEPTH_IN_TEST_MODE = 15;

    /** @var float */
    public static $timestampBeforeTest;

    /** @var ?float */
    public static $timestampAfterTest = null;

    /** @var Logger */
    private $logger;

    public function __construct(string $dbgProcessName)
    {
        LoggingSubsystem::$isInTestingContext = true;
        SerializationUtil::$isInTestingContext = true;
        LoggableToJsonEncodable::$maxDepth = self::LOG_COMPOSITE_DATA_MAX_DEPTH_IN_TEST_MODE;

        AmbientContextForTests::init($dbgProcessName);

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_UTIL, __NAMESPACE__, __CLASS__, __FILE__);
    }

    /**
     * @param string $test
     */
    public function executeBeforeTest(string $test): void
    {
        AssertMessageStack::reset();
        self::$timestampBeforeTest = AmbientContextForTests::clock()->getSystemClockCurrentTime();
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('', ['timestampBeforeTest' => TimeUtilForTests::timestampToLoggable(self::$timestampBeforeTest)]);
        self::$timestampAfterTest = null;
        MetadataExpectations::setDefaults();
        SpanExpectations::setDefaults();
        TransactionExpectations::setDefaults();
    }
}
