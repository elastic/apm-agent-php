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
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;

final class ExpectedEventCounts implements LoggableInterface
{
    use LoggableTrait;

    /** @var array<string, ?int> */
    private $perDataKind;

    /** @var ?int */
    private $maxSpanCount;

    public function __construct()
    {
        $this->perDataKind = [];
        $this->errors(0);
        $this->setValueForKind(ApmDataKind::metadata(), 1);
        $this->metricSets(0);
        $this->spans(0);
        $this->transactions(1);

        $allApmDataKindsAsStrings = array_reduce(
            ApmDataKind::all(),
            function (array $carry, ApmDataKind $item): array {
                $carry[] = $item->asString();
                return $carry;
            },
            [] /* <- initial */
        );
        TestCaseBase::assertEqualAsSets($allApmDataKindsAsStrings, array_keys($this->perDataKind));
    }

    private function setValueForKind(ApmDataKind $apmDataKind, ?int $count): self
    {
        $this->perDataKind[$apmDataKind->asString()] = $count;
        return $this;
    }

    public function errors(int $count): self
    {
        return $this->setValueForKind(ApmDataKind::error(), $count);
    }

    public function transactions(?int $count): self
    {
        return $this->setValueForKind(ApmDataKind::transaction(), $count);
    }

    public function spans(int $minCount, ?int $maxCount = null): self
    {
        $this->maxSpanCount = $maxCount;
        return $this->setValueForKind(ApmDataKind::span(), $minCount);
    }

    public function metricSets(?int $minCount): self
    {
        return $this->setValueForKind(ApmDataKind::metricSet(), $minCount);
    }

    public function hasReachedCountForKind(ApmDataKind $apmDataKind, int $actualCount): bool
    {
        $dbgCtx = ['$apmDataKind' => $apmDataKind];
        $dbgCtxStr = LoggableToString::convert($dbgCtx);
        $expectedCount = ArrayUtil::getValueIfKeyExistsElse($apmDataKind->asString(), $this->perDataKind, null);
        if ($expectedCount === null) {
            return true;
        }

        if ($apmDataKind === ApmDataKind::span() && $this->maxSpanCount !== null) {
            TestCase::assertLessThanOrEqual($this->maxSpanCount, $actualCount, $dbgCtxStr);
            return $expectedCount <= $actualCount;
        }

        if ($apmDataKind === ApmDataKind::metadata() || $apmDataKind === ApmDataKind::metricSet()) {
            return $expectedCount <= $actualCount;
        }

        TestCase::assertLessThanOrEqual($expectedCount, $actualCount, $dbgCtxStr);
        return $expectedCount === $actualCount;
    }
}
