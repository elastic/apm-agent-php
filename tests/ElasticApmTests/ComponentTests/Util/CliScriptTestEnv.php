<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\LogCategoryForTests;
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
            LogCategoryForTests::TEST_UTIL,
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
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Running ' . ClassNameUtil::fqToShort(CliScriptAppCodeHost::class) . '...',
            ['testProperties' => $testProperties]
        );

        TestProcessUtil::startProcessAndWaitUntilExit(
            $testProperties->agentConfigSetter->appCodePhpCmd()
            . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::SCRIPT_TO_RUN_APP_CODE_HOST . '"',
            self::inheritedEnvVars(/* keepElasticApmEnvVars */ false)
            + [
                TestConfigUtil::envVarNameForTestOption(
                    AllComponentTestsOptionsMetadata::SHARED_DATA_PER_PROCESS_OPTION_NAME
                ) => SerializationUtil::serializeAsJson($this->buildSharedDataPerProcess()),
                TestConfigUtil::envVarNameForTestOption(
                    AllComponentTestsOptionsMetadata::SHARED_DATA_PER_REQUEST_OPTION_NAME
                ) => SerializationUtil::serializeAsJson($testProperties->sharedDataPerRequest),
            ]
            + $testProperties->agentConfigSetter->additionalEnvVars()
        );
    }

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        parent::verifyRootTransactionName($testProperties, $rootTransaction);

        if (is_null($testProperties->expectedTransactionName)) {
            TestCase::assertSame(self::SCRIPT_TO_RUN_APP_CODE_HOST, $rootTransaction->name);
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        parent::verifyRootTransactionType($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_CLI, $rootTransaction->type);
        }
    }
}
