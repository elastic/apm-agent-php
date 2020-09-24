<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\TestLogCategory;

final class CliScriptAppCodeHost extends AppCodeHostBase
{
    /** @var Logger */
    private $logger;

    public function __construct(string $runScriptFile)
    {
        parent::__construct($runScriptFile);

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function processConfig(): void
    {
        parent::processConfig();

        $this->appCodeClass = self::getRequiredTestOption(AllComponentTestsOptionsMetadata::APP_CODE_CLASS_OPTION_NAME);
        $this->appCodeMethod = self::getRequiredTestOption(
            AllComponentTestsOptionsMetadata::APP_CODE_METHOD_OPTION_NAME
        );
    }

    protected function runImpl(): void
    {
        $this->callAppCode();
    }
}
