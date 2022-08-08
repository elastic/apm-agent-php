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
use ElasticApmTests\Util\Deserialization\JsonDeserializableInterface;
use ElasticApmTests\Util\Deserialization\JsonDeserializableTrait;
use PHPUnit\Framework\TestCase;

final class AppCodeTarget implements JsonDeserializableInterface, LoggableInterface
{
    use LoggableTrait;
    use JsonDeserializableTrait;

    /** @var ?string */
    public $appCodeClass = null;

    /** @var ?string */
    public $appCodeMethod = null;

    /** @var ?string */
    public $appCodeTopLevelId = null;

    public static function asRouted(callable $appCodeClassMethod): AppCodeTarget
    {
        TestCase::assertIsCallable($appCodeClassMethod);
        TestCase::assertIsArray($appCodeClassMethod);
        /** @noinspection PhpParamsInspection */
        TestCase::assertCount(2, $appCodeClassMethod);

        $thisObj = new AppCodeTarget();
        $thisObj->appCodeClass = $appCodeClassMethod[0];
        $thisObj->appCodeMethod = $appCodeClassMethod[1];
        return $thisObj;
    }

    public static function asTopLevel(string $appCodeTopLevelId): AppCodeTarget
    {
        $thisObj = new AppCodeTarget();
        $thisObj->appCodeTopLevelId = $appCodeTopLevelId;
        return $thisObj;
    }
}
