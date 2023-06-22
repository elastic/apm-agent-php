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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\DbgUtil;
use PHPUnit\Framework\Assert;

final class AssertMessageStackScopeData implements LoggableInterface
{
    /** @var Pair<string, array<string, mixed>>[] */
    public $subScopesStack;

    /**
     * @param string               $name
     * @param array<string, mixed> $initialCtx
     */
    public function __construct(string $name, array $initialCtx)
    {
        $this->subScopesStack = [new Pair($name, $initialCtx)];
    }

    /**
     * @param int $numberOfStackFramesToSkip
     *
     * @return string
     *
     * @phpstan-param 0|positive-int $numberOfStackFramesToSkip
     */
    public static function buildContextName(int $numberOfStackFramesToSkip): string
    {
        $callerInfo = DbgUtil::getCallerInfoFromStacktrace($numberOfStackFramesToSkip + 1);

        $classMethodPart = '';
        if ($callerInfo->class !== null) {
            $classMethodPart .= $callerInfo->class . '::';
        }
        Assert::assertNotNull($callerInfo->function);
        $classMethodPart .= $callerInfo->function;

        $fileLinePart = '';
        if ($callerInfo->file !== null) {
            $fileLinePart .= '[';
            $fileLinePart .= $callerInfo->file;
            $fileLinePart .= TextUtilForTests::combineWithSeparatorIfNotEmpty(':', TextUtilForTests::emptyIfNull($callerInfo->line));
            $fileLinePart .= ']';
        }

        return $classMethodPart . TextUtilForTests::combineWithSeparatorIfNotEmpty(' ', $fileLinePart);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(['subScopesStack count' => count($this->subScopesStack)]);
    }
}
