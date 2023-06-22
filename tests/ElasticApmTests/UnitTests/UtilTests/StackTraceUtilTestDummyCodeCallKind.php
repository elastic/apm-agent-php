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

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\TestCaseBase;

final class StackTraceUtilTestDummyCodeCallKind implements LoggableInterface
{
    /** @var bool */
    public $isToCodeToHide;

    /** @var string */
    public $dbgDesc;

    /** @var callable(mixed ...): StackTraceUtilTestDummyCodeRetVal */
    public $callable;

    /** @var mixed[] */
    public $argsPrefix;

    /**
     * @param string       $dbgMethodDesc
     * @param class-string $parentClass
     * @param mixed        $callable
     * @param mixed[]      $argsPrefix
     */
    public function __construct(string $dbgMethodDesc, string $parentClass, $callable, array $argsPrefix = [])
    {
        $this->isToCodeToHide = TextUtil::contains($parentClass, 'Hide');
        $this->dbgDesc = ($this->isToCodeToHide ? 'code to hide' : 'app code') . ' - ' . $dbgMethodDesc;
        TestCaseBase::assertIsCallable($callable);
        /** @var callable(mixed ...): StackTraceUtilTestDummyCodeRetVal $callable */
        $this->callable = $callable;
        $this->argsPrefix = $argsPrefix;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->dbgDesc);
    }
}
