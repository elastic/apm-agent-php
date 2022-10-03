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

final class TransactionExpectations extends ExecutionSegmentExpectations
{
    /** @var ?bool */
    public static $defaultIsSampled = true;

    /** @var ?bool */
    public $isSampled;

    /** @var ?int */
    public static $defaultDroppedSpansCount = 0;

    /** @var ?int */
    public $droppedSpansCount;

    public static function setDefaults(): void
    {
        self::$defaultIsSampled = true;
        self::$defaultDroppedSpansCount = 0;
    }

    public function __construct()
    {
        parent::__construct();
        $this->isSampled = self::$defaultIsSampled;
        $this->droppedSpansCount = self::$defaultDroppedSpansCount;
    }
}
