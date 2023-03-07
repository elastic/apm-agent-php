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

use ElasticApmTests\Util\ArrayUtilForTests;

final class RawDataFromAgent
{
    /** @var IntakeApiConnection[] */
    private $intakeApiConnections;

    /** @var null|IntakeApiRequest[] */
    private $allIntakeApiRequests = null;

    /**
     * @param IntakeApiConnection[] $intakeApiConnections
     */
    public function __construct(array $intakeApiConnections)
    {
        $this->intakeApiConnections = $intakeApiConnections;
    }

    /**
     * @return IntakeApiConnection[]
     */
    public function getIntakeApiConnections(): array
    {
        return $this->intakeApiConnections;
    }

    /**
     * @return IntakeApiRequest[]
     */
    public function getAllIntakeApiRequests(): array
    {
        if ($this->allIntakeApiRequests === null) {
            $this->allIntakeApiRequests = [];
            foreach ($this->intakeApiConnections as $intakeApiConnection) {
                ArrayUtilForTests::append(
                    $intakeApiConnection->getIntakeApiRequests() /* <- from */,
                    $this->allIntakeApiRequests /* <- to, ref */
                );
            }
        }
        return $this->allIntakeApiRequests;
    }

    public function getTimeAllDataReceivedAtApmServer(): float
    {
        return ArrayUtilForTests::getLastValue($this->getAllIntakeApiRequests())->timeReceivedAtApmServer;
    }
}
