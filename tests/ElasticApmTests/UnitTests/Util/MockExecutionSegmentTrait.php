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

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\TimeUtil;

trait MockExecutionSegmentTrait
{
    protected function constructMockExecutionSegmentDataTrait(?string $name): void
    {
        $this->id = $this->getTransaction()->tracer->generateExecutionSegmentId();
        $this->timestamp = $this->getTransaction()->tracer->getCurrentTime();

        $prefix = ClassNameUtil::fqToShort(get_called_class());
        $this->name = $name ?? ($prefix . ' name');
        $this->type = $prefix . '_type';

        $this->sampleRate = 1.0;
    }

    public function beginChildSpan(?string $name = null): MockSpan
    {
        $transaction = $this->getTransaction();
        ++$transaction->startedSpansCount;
        return new MockSpan($name, $transaction, /* parent */ $this);
    }

    public function beginChildTransaction(?string $name = null): MockTransaction
    {
        return new MockTransaction($name, $this->getTransaction()->tracer, /* parent */ $this);
    }

    public function end(): void
    {
        $timestampEnd = $this->getTransaction()->tracer->getCurrentTime();
        $this->duration = TimeUtil::calcDurationInMillisecondsClampNegativeToZero($this->timestamp, $timestampEnd);
    }
}
