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

abstract class AgentConfigSetter implements LoggableInterface
{
    use LoggableTrait;

    /** @var array<string, string> */
    public $optionNameToValue = [];

    abstract public function appCodePhpCmd(): string;

    /**
     * @return array<string, string>
     */
    abstract public function additionalEnvVars(): array;

    abstract public function tearDown(): void;

    /**
     * @param string $optName
     * @param string $optVal
     *
     * @return $this
     */
    public function set(string $optName, string $optVal): self
    {
        $this->optionNameToValue[$optName] = $optVal;

        return $this;
    }

    protected static function buildAppCodePhpCmd(?string $appCodePhpIni): string
    {
        $result = AmbientContext::testConfig()->appCodePhpExe ?? 'php';
        if (!is_null($appCodePhpIni)) {
            $result .= ' -c ' . $appCodePhpIni;
        }
        return $result;
    }
}
