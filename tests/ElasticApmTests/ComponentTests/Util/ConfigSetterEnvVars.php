<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

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

        foreach ($this->parent->configuredOptions as $optName => $optVal) {
            $result[TestConfigUtil::envVarNameForOption($optName)] = strval($optVal);
        }

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting', ['result' => $result]);

        return $result;
    }

    public function tearDown(): void
    {
    }
}
