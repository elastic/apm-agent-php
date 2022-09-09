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

use PHPUnit\Framework\TestCase;

final class TestInfraDataPerRequest extends TestInfraData
{
    /** @var ?AppCodeTarget */
    public $appCodeTarget = null;

    /** @var null|array<string, mixed> */
    public $appCodeArguments = null;

    /** @var string */
    public $spawnedProcessInternalId;

    public static function withSpawnedProcessInternalId(string $spawnedProcessInternalId): self
    {
        $result = new TestInfraDataPerRequest();
        $result->spawnedProcessInternalId = $spawnedProcessInternalId;
        return $result;
    }

    /**
     * @param mixed $decodedJson
     *
     * @return mixed
     */
    protected function deserializePropertyValue(string $propertyName, $decodedJson)
    {
        switch ($propertyName) {
            case 'appCodeTarget':
                if ($decodedJson === null) {
                    return null;
                }
                $appCodeTarget = new AppCodeTarget();
                TestCase::assertIsArray($decodedJson);
                $appCodeTarget->deserializeFromDecodedJson($decodedJson);
                return $appCodeTarget;
            default:
                return parent::deserializePropertyValue($propertyName, $decodedJson);
        }
    }
}
