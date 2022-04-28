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
use Elastic\Apm\Impl\Log\LoggableTrait;
use ElasticApmTests\Util\Optional;

class AppCodeRequestParams implements LoggableInterface
{
    use LoggableTrait;

    /** @var TestInfraDataPerRequest */
    public $dataPerRequest;

    /** @var bool */
    public $shouldVerifyRootTransaction = true;

    /** @var Optional<?string> */
    public $expectedTransactionName;

    /** @var Optional<?string> */
    public $expectedTransactionType;

    /** @var bool */
    public $shouldAssumeNoDroppedSpans = true;

    public function __construct(AppCodeTarget $appCodeTarget)
    {
        $this->dataPerRequest = new TestInfraDataPerRequest();
        $this->dataPerRequest->appCodeTarget = $appCodeTarget;
        $this->expectedTransactionName = new Optional();
        $this->expectedTransactionType = new Optional();
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public function setAppCodeArgs(array $appCodeArgs): void
    {
        $this->dataPerRequest->appCodeArguments = $appCodeArgs;
    }
}
