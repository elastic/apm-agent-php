<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\ServerComm\SerializationUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\TestCase;

final class CliScriptTestEnv extends TestEnvBase
{
    private const SCRIPT_TO_RUN_APP_CODE_HOST = 'runCliScriptAppCodeHost.php';

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function isHttp(): bool
    {
        return false;
    }

    protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void
    {
        $this->ensureMockApmServerRunning();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Running ' . DbgUtil::fqToShortClassName(CliScriptAppCodeHost::class) . '...',
            ['testProperties' => $testProperties]
        );

        $additionalEnvVars
            = $testProperties->configSetter->additionalEnvVars() +
              [
                  TestConfigUtil::envVarNameForTestsOption(
                      AllComponentTestsOptionsMetadata::APP_CODE_CLASS_OPTION_NAME
                  ) => $testProperties->appCodeClass,
                  TestConfigUtil::envVarNameForTestsOption(
                      AllComponentTestsOptionsMetadata::APP_CODE_METHOD_OPTION_NAME
                  ) => $testProperties->appCodeMethod,
              ];

        if (!is_null($testProperties->appCodeArgs)) {
            $additionalEnvVars[TestConfigUtil::envVarNameForTestsOption(
                AllComponentTestsOptionsMetadata::APP_CODE_ARGUMENTS_OPTION_NAME
            )]
                = SerializationUtil::serializeAsJson(
                    $testProperties->appCodeArgs,
                    AllComponentTestsOptionsMetadata::APP_CODE_ARGUMENTS_OPTION_NAME
                );
        }

        TestProcessUtil::runProcessAndWaitUntilExit(
            $testProperties->configSetter->appCodePhpCmd()
            . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::SCRIPT_TO_RUN_APP_CODE_HOST . '"',
            $this->buildEnvVars($additionalEnvVars)
        );
    }

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionDataInterface $rootTransaction
    ): void {
        parent::verifyRootTransactionName($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionName)) {
            TestCase::assertSame(self::SCRIPT_TO_RUN_APP_CODE_HOST, $rootTransaction->getName());
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionDataInterface $rootTransaction
    ): void {
        parent::verifyRootTransactionType($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_CLI, $rootTransaction->getType());
        }
    }
}
