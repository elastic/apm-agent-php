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

use Elastic\Apm\Impl\Config\SnapshotTrait;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\WildcardListMatcher;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ConfigSnapshotForTests implements LoggableInterface
{
    use SnapshotTrait;
    use LoggableTrait;

    /** @var ?AppCodeHostKind */
    private $appCodeHostKind = null;

    /** @var ?string */
    public $appCodePhpExe;

    /** @var ?string */
    public $appCodePhpIni;

    /** @var TestInfraDataPerProcess */
    public $dataPerProcess;

    /** @var ?TestInfraDataPerRequest */
    public $dataPerRequest = null;

    /** @var bool */
    public $deleteTempPhpIni;

    /** @var ?WildcardListMatcher */
    public $envVarsToPassThrough;

    /** @var int */
    public $escalatedRerunsMaxCount;

    /** @var ?string */
    public $group;

    /** @var int */
    public $logLevel;

    /** @var ?string */
    public $mysqlHost;

    /** @var ?int */
    public $mysqlPort;

    /** @var ?string */
    public $mysqlUser;

    /** @var ?string */
    public $mysqlPassword;

    /** @var ?string */
    public $mysqlDb;

    /** @var ?string */
    public $runBeforeEachTest;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->dataPerProcess = new TestInfraDataPerProcess();
        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }

    public function isEnvVarToPassThrough(string $envVarName): bool
    {
        if ($this->envVarsToPassThrough === null) {
            return false;
        }

        return $this->envVarsToPassThrough->match($envVarName) !== null;
    }

    public function appCodeHostKind(): AppCodeHostKind
    {
        if ($this->appCodeHostKind === null) {
            $optionName = AllComponentTestsOptionsMetadata::APP_CODE_HOST_KIND_OPTION_NAME;
            $envVarName = ConfigUtilForTests::testOptionNameToEnvVarName($optionName);
            throw new RuntimeException(
                'Required configuration option ' . $optionName
                . " (environment variable $envVarName)" . ' is not set'
            );
        }

        return $this->appCodeHostKind;
    }

    public function isSmoke(): bool
    {
        return $this->group === 'smoke';
    }
}
