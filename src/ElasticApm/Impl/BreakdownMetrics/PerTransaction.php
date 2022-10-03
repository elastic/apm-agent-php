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

namespace Elastic\Apm\Impl\BreakdownMetrics;

use Closure;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\MetricSet;
use Elastic\Apm\Impl\Transaction;

/**
 * An error or a logged error message captured by an agent occurring in a monitored service
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class PerTransaction implements LoggableInterface
{
    use LoggableTrait;

    public const TRANSACTION_BREAKDOWN_COUNT_SAMPLE_KEY = 'transaction.breakdown.count';
    public const SPAN_SELF_TIME_COUNT_SAMPLE_KEY = 'span.self_time.count';
    public const SPAN_SELF_TIME_SUM_US_SAMPLE_KEY = 'span.self_time.sum.us';
    public const TRANSACTION_SPAN_TYPE = 'app';

    /** @var Transaction */
    private $transaction;

    /** @var array<string, PerSpanTypeData> */
    private $perSpanTypeData = [];

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function addSpanSelfTime(string $type, ?string $subtype, float $selfTimeInMicroseconds): void
    {
        if (!array_key_exists($type, $this->perSpanTypeData)) {
            $this->perSpanTypeData[$type] = new PerSpanTypeData();
        }
        $this->perSpanTypeData[$type]->add($subtype, $selfTimeInMicroseconds);
    }

    /**
     * @param Closure(MetricSet): void $consumeMetricSet
     */
    public function forEachMetricSet(Closure $consumeMetricSet): void
    {
        $metricSet = new MetricSet();
        $metricSet->timestamp = $this->transaction->getTimestamp();
        $metricSet->transactionName = $this->transaction->getName();
        $metricSet->transactionType = $this->transaction->getType();

        foreach ($this->perSpanTypeData as $spanType => $entryPerSpanType) {
            $metricSet->spanType = $spanType;

            if ($entryPerSpanType->noSubtypeData !== null) {
                $metricSet->spanSubtype = null;
                self::setSelfTimeSamples($entryPerSpanType->noSubtypeData, $metricSet);
                $consumeMetricSet($metricSet);
            }

            foreach ($entryPerSpanType->perSubtypeData as $spanSubtype => $leafData) {
                $metricSet->spanSubtype = $spanSubtype;
                self::setSelfTimeSamples($leafData, $metricSet);
                $consumeMetricSet($metricSet);
            }
        }

        $metricSet->spanType = null;
        $metricSet->spanSubtype = null;
        $metricSet->clearSamples();
        $metricSet->setSample(self::TRANSACTION_BREAKDOWN_COUNT_SAMPLE_KEY, 1);
        $consumeMetricSet($metricSet);
    }

    private static function setSelfTimeSamples(LeafData $leafData, MetricSet $dstMetricSet): void
    {
        $dstMetricSet->setSample(self::SPAN_SELF_TIME_COUNT_SAMPLE_KEY, $leafData->count);
        $dstMetricSet->setSample(self::SPAN_SELF_TIME_SUM_US_SAMPLE_KEY, $leafData->sumMicroseconds);
    }
}
