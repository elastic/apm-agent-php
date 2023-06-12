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

use Elastic\Apm\Impl\Util\StackTraceUtil;
use ElasticApmTests\UnitTests\Util\SourceCodeLocation;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;

final class StackTraceUtilTestDummyCodeArgs
{
    /** @var StackTraceUtil */
    public $stackTraceUtil;

    /** @var StackTraceUtilTestDummyCodeCallKind[] */
    public $callKindsSeq;

    /** @var int */
    public $currentCallIndex;

    /** @var SourceCodeLocation */
    public $calledFrom;

    /** @var StackTraceFrameExpectations[] */
    public $expectations;

    /**
     * @param StackTraceUtil                        $stackTraceUtil
     * @param StackTraceUtilTestDummyCodeCallKind[] $callKindsSeq
     * @param int                                   $currentCallIndex
     * @param SourceCodeLocation                    $calledFrom
     * @param StackTraceFrameExpectations[]         $expectations
     */
    public function __construct(StackTraceUtil $stackTraceUtil, array $callKindsSeq, int $currentCallIndex, SourceCodeLocation $calledFrom, array $expectations)
    {
        $this->stackTraceUtil = $stackTraceUtil;
        $this->callKindsSeq = $callKindsSeq;
        $this->currentCallIndex = $currentCallIndex;
        $this->calledFrom = $calledFrom;
        $this->expectations = $expectations;
    }

    public function isThereMoreCallsToMake(): bool
    {
        return $this->currentCallIndex + 1 < count($this->callKindsSeq);
    }

    private function isCurrentCallToCodeToHide(): bool
    {
        return $this->callKindsSeq[$this->currentCallIndex]->isToCodeToHide;
    }

    public function isPrevCallToCodeToHide(): bool
    {
        return $this->currentCallIndex !== 0 && $this->callKindsSeq[$this->currentCallIndex - 1]->isToCodeToHide;
    }

    public function selectCalledFrom(SourceCodeLocation $calledFromCurrentFunc): SourceCodeLocation
    {
        return $this->isCurrentCallToCodeToHide() ? $this->calledFrom : $calledFromCurrentFunc;
    }

    /**
     * @param StackTraceFrameExpectations[] &$expectations
     * @param StackTraceFrameExpectations    $newTop
     */
    public function addCurrentCallExpectations(array &$expectations, StackTraceFrameExpectations $newTop): void
    {
        if (!$this->isCurrentCallToCodeToHide()) {
            array_unshift($expectations, $newTop);
        }
    }

    /**
     * @param SourceCodeLocation            $calledFromCurrentFunc
     * @param StackTraceFrameExpectations[] $expectations
     *
     * @return array{callable, mixed[]}
     */
    public function getNextCallData(SourceCodeLocation $calledFromCurrentFunc, array $expectations): array
    {
        TestCaseBase::assertLessThan(count($this->callKindsSeq), $this->currentCallIndex + 1);
        $argsObj = new self($this->stackTraceUtil, $this->callKindsSeq, $this->currentCallIndex + 1, $this->selectCalledFrom($calledFromCurrentFunc), $expectations);

        $nextCall = $this->callKindsSeq[$this->currentCallIndex + 1];
        $nextCallArgs = array_merge($nextCall->argsPrefix, [$argsObj]);
        return [$nextCall->callable, $nextCallArgs];
    }
}
