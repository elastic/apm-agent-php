<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BuiltinHttpServerTestEnv extends HttpServerTestEnvBase
{
    private const APP_CODE_HOST_ROUTER_SCRIPT = 'routeToCliBuiltinHttpServerAppCodeHost.php';

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

    protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void
    {
        if (isset($this->appCodeHostServerPort)) {
            return;
        }

        $appCodeHostServerPort = $this->findFreePortToListen();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Starting ' . DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class) . '...',
            ['appCodeHostServerPort' => $appCodeHostServerPort]
        );
        TestProcessUtil::startBackgroundProcess(
            $testProperties->configSetter->appCodePhpCmd()
            . " -S localhost:$appCodeHostServerPort"
            . ' "' . __DIR__ . DIRECTORY_SEPARATOR . self::APP_CODE_HOST_ROUTER_SCRIPT . '"',
            $this->buildEnvVars($testProperties->configSetter->additionalEnvVars())
        );
        $this->ensureHttpServerRunning(
            $appCodeHostServerPort,
            /* dbgServerDesc */ DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class)
        );

        $this->appCodeHostServerPort = $appCodeHostServerPort;
    }
}
