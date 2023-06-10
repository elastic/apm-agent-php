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

use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\RangeUtil;

/**
 * @extends ExpectationsBase<StackTraceFrame[]>
 */
final class StackTraceExpectations extends ExpectationsBase
{
    /** @var bool */
    public $allowToBePrefixOfActual = true;

    /** @var StackTraceFrameExpectations[] */
    public $frames = [];

    /**
     * @param StackTraceFrame[] $inputFrames
     * @param bool              $allowToBePrefixOfActual
     *
     * @return self
     */
    public static function fromFrames(array $inputFrames, bool $allowToBePrefixOfActual = false): self
    {
        $framesExpectations = [];
        foreach ($inputFrames as $inputFrame) {
            $framesExpectations[] = StackTraceFrameExpectations::fromFrame($inputFrame);
        }
        return self::fromFramesExpectations($framesExpectations, $allowToBePrefixOfActual);
    }

    /**
     * @param StackTraceFrameExpectations[] $framesExpectations
     * @param bool                          $allowToBePrefixOfActual
     *
     * @return self
     */
    public static function fromFramesExpectations(array $framesExpectations, bool $allowToBePrefixOfActual = false): self
    {
        $result = new self();
        $result->frames = $framesExpectations;
        $result->allowToBePrefixOfActual = $allowToBePrefixOfActual;

        return $result;
    }

    /**
     * @param StackTraceFrame[] $actual
     *
     * @return void
     */
    public function assertMatches(array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $dbgCtx->add(['this' => $this]);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(min(count($this->frames), count($actual))) as $i) {
            $dbgCtx->clearCurrentSubScope(['i' => $i]);
            $this->frames[$i]->assertMatches($actual[$i]);
        }
        $dbgCtx->popSubScope();

        if ($this->allowToBePrefixOfActual) {
            TestCaseBase::assertGreaterThanOrEqual(count($this->frames), count($actual));
        } else {
            TestCaseBase::assertSame(count($this->frames), count($actual));
        }
    }
}
