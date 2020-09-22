<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\TestLogCategory;

final class ConfigSetterEnvVars extends ConfigSetterBase
{
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }
    public function appCodePhpCmd(): string
    {
        return self::buildAppCodePhpCmd(AmbientContext::config()->appCodePhpIni());
    }

    /** @inheritDoc */
    public function additionalEnvVars(): array
    {
        $result = [];

        $addEnvVarIfOptionIsConfigured = function (string $optName, ?string $configuredValue) use (&$result): void {
            if (is_null($configuredValue)) {
                return;
            }

            $envVarName
                = EnvVarsRawSnapshotSource::optionNameToEnvVarName(EnvVarsRawSnapshotSource::DEFAULT_PREFIX, $optName);
            $result[$envVarName] = $configuredValue;
        };

        $addEnvVarIfOptionIsConfigured(OptionNames::API_KEY, $this->parent->configuredApiKey);
        $addEnvVarIfOptionIsConfigured(OptionNames::ENVIRONMENT, $this->parent->configuredEnvironment);
        $addEnvVarIfOptionIsConfigured(OptionNames::SECRET_TOKEN, $this->parent->configuredSecretToken);
        $addEnvVarIfOptionIsConfigured(OptionNames::SERVICE_NAME, $this->parent->configuredServiceName);
        $addEnvVarIfOptionIsConfigured(OptionNames::SERVICE_VERSION, $this->parent->configuredServiceVersion);

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting', ['result' => $result]);

        return $result;
    }

    public function tearDown(): void
    {
    }
}
