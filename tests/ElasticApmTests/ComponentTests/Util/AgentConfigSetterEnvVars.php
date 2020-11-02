<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\LogCategoryForTests;

final class AgentConfigSetterEnvVars extends AgentConfigSetterBase
{
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    public function appCodePhpCmd(): string
    {
        return self::buildAppCodePhpCmd(AmbientContext::testConfig()->appCodePhpIni);
    }

    /** @inheritDoc */
    public function additionalEnvVars(): array
    {
        $result = [];

        foreach ($this->optionNameToValue as $optName => $optVal) {
            $result[TestConfigUtil::envVarNameForAgentOption($optName)] = $optVal;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting', ['result' => $result]);

        return $result;
    }

    public function tearDown(): void
    {
    }
}
