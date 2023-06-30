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

namespace Elastic\Apm\Impl;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetadataDiscoverer
{
    public const AGENT_NAME = 'php';
    public const LANGUAGE_NAME = 'PHP';
    // https://github.com/elastic/apm/blob/main/specs/agents/configuration.md#zero-configuration-support
    // ... the default value: unknown-${service.agent.name}-service ...
    public const DEFAULT_SERVICE_NAME = 'unknown-php-service';

    /** @var ConfigSnapshot */
    private $config;

    /** @var Logger */
    private $logger;

    /** @var array<string, callable(): ?NameVersionData> */
    private $serviceFrameworkDiscoverers = [];

    /** @var ?string */
    private $agentEphemeralId = null;

    public function __construct(ConfigSnapshot $config, LoggerFactory $loggerFactory)
    {
        $this->config = $config;
        $this->logger = $loggerFactory->loggerForClass(LogCategory::DISCOVERY, __NAMESPACE__, __CLASS__, __FILE__);
    }

    /**
     * @param string                       $dbgDiscovererName
     * @param callable(): ?NameVersionData $dbgDiscoverCall
     *
     * @return void
     */
    public function addServiceFrameworkDiscoverer(string $dbgDiscovererName, callable $dbgDiscoverCall): void
    {
        $this->serviceFrameworkDiscoverers[$dbgDiscovererName] = $dbgDiscoverCall;
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Added', ['dbgDiscovererName' => $dbgDiscovererName]);
    }

    public function setAgentEphemeralId(?string $agentEphemeralId): void
    {
        $this->agentEphemeralId = $agentEphemeralId;
    }

    public function discover(): Metadata
    {
        $result = new Metadata();

        $result->labels = $this->config->globalLabels();
        $result->process = $this->discoverProcessData();
        $result->service = $this->discoverServiceData();
        $result->system = $this->discoverSystemData();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Exiting ...', ['result' => $result]);
        return $result;
    }

    public static function adaptServiceName(string $configuredName): string
    {
        if (TextUtil::isEmptyString($configuredName)) {
            return self::DEFAULT_SERVICE_NAME;
        }

        $charsAdaptedName = preg_replace('/[^a-zA-Z0-9 _\-]/', '_', $configuredName);
        return $charsAdaptedName === null ? MetadataDiscoverer::DEFAULT_SERVICE_NAME : Tracer::limitKeywordString($charsAdaptedName);
    }

    private static function setKeywordStringIfNotNull(?string $srcCfgVal, ?string &$dstProp): void
    {
        if ($srcCfgVal !== null) {
            $dstProp = Tracer::limitKeywordString($srcCfgVal);
        }
    }

    private function discoverServiceData(): ServiceData
    {
        $result = new ServiceData();

        self::setKeywordStringIfNotNull($this->config->environment(), /* ref */ $result->environment);

        $result->name = $this->config->serviceName() === null ? MetadataDiscoverer::DEFAULT_SERVICE_NAME : MetadataDiscoverer::adaptServiceName($this->config->serviceName());

        self::setKeywordStringIfNotNull($this->config->serviceNodeName(), /* ref */ $result->nodeConfiguredName);
        self::setKeywordStringIfNotNull($this->config->serviceVersion(), /* ref */ $result->version);

        $result->agent = new ServiceAgentData();
        $result->agent->name = self::AGENT_NAME;
        $result->agent->version = ElasticApm::VERSION;

        $result->agent->ephemeralId = Tracer::limitNullableKeywordString($this->agentEphemeralId);

        $result->language = new NameVersionData(MetadataDiscoverer::LANGUAGE_NAME, PHP_VERSION);

        $result->runtime = $result->language;

        $result->framework = $this->discoverServiceFramework();

        return $result;
    }

    private function discoverServiceFramework(): ?NameVersionData
    {
        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        /** @var ?NameVersionData $result */
        $result = null;
        /** @var ?string $resultFrom */
        $resultFrom = null;
        foreach ($this->serviceFrameworkDiscoverers as $currentDbgDiscovererName => $currentDiscoverCall) {
            if (($currentDiscovererResult = $currentDiscoverCall()) === null) {
                $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, $currentDbgDiscovererName . ' did not discover service framework');
                continue;
            }
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, $currentDbgDiscovererName . ' discovered service framework', ['result' => $currentDiscovererResult]);
            if ($result !== null) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'More than one discover returned a non-null result',
                    ['1st' => ['result' => $result, 'from' => $resultFrom], '2nd' => ['result' => $currentDiscovererResult, 'from' => $currentDbgDiscovererName]]
                );
                return null;
            }
            $result = $currentDiscovererResult;
            $resultFrom = $currentDbgDiscovererName;
        }

        $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Exiting...', ['result' => $result, 'from' => $resultFrom]);
        return $result;
    }

    private function discoverSystemData(): SystemData
    {
        $result = new SystemData();

        $configuredHostname = $this->config->hostname();
        if ($configuredHostname !== null) {
            $result->configuredHostname = Tracer::limitKeywordString($configuredHostname);
            $result->hostname = $result->configuredHostname;
        } else {
            $detectedHostname = self::discoverHostname();
            if ($detectedHostname !== null) {
                $result->detectedHostname = $detectedHostname;
                $result->hostname = $detectedHostname;
            }
        }

        $result->containerId = $this->discoverContainerId();

        return $result;
    }

    public static function discoverHostname(): ?string
    {
        $detected = gethostname();
        if ($detected === false) {
            return null;
        }

        return Tracer::limitKeywordString($detected);
    }

    private const DETECT_CONTAINER_ID_FILENAME_TI_REGEX = [
        '/proc/self/mountinfo' => '/\/var\/lib\/docker\/containers\/([0-9a-f]+)\/hostname/m',
        '/proc/self/cgroup' => '/\/docker\/([0-9a-f]+)$/m',
    ];

    /**
     * @param callable(string $fileName): ?string $getFileContents
     *
     * @return ?string
     */
    public function discoverContainerIdImpl(callable $getFileContents): ?string
    {
        $loggerPxyDbg = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);
        foreach (self::DETECT_CONTAINER_ID_FILENAME_TI_REGEX as $fileName => $regex) {
            if (($fileContents = $getFileContents($fileName)) !== null) {
                if (preg_match($regex, $fileContents, $matches)) {
                    $loggerPxyDbg && $loggerPxyDbg->log(__LINE__, 'Found container ID in ' . $fileName, ['found container ID' => $matches[1], 'fileContents' => $fileContents, 'regex' => $regex]);
                    return $matches[1];
                }
                $loggerPxyDbg && $loggerPxyDbg->log(__LINE__, 'Could not find container ID in ' . $fileName, ['fileContents' => $fileContents, 'regex' => $regex]);
            }
        }

        $loggerPxyDbg && $loggerPxyDbg->log(__LINE__, 'Could not find container ID anywhere');
        return null;
    }

    private function getFileContentsForDetectContainerId(string $fileName): ?string
    {
        if (!file_exists($fileName)) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('File ' . $fileName . ' does not exit');
            return null;
        }
        $contents = file_get_contents($fileName);
        if ($contents === false) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Failed to get ' . $fileName . ' contents');
            return null;
        }
        return $contents;
    }

    private function discoverContainerId(): ?string
    {
        return self::discoverContainerIdImpl(
            function (string $fileName): ?string {
                return $this->getFileContentsForDetectContainerId($fileName);
            }
        );
    }

    private function discoverProcessData(): ProcessData
    {
        $result = new ProcessData();

        $currentProcessId = getmypid();
        if ($currentProcessId === false) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to get current process ID - setting it to 0 instead');
            $result->pid = 0;
        } else {
            $result->pid = $currentProcessId;
        }

        return $result;
    }
}
