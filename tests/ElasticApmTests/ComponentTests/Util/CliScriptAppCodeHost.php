<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\TestLogCategory;

final class CliScriptAppCodeHost extends AppCodeHostBase
{
    public const CLASS_CMD_OPT_NAME = 'class';
    public const METHOD_CMD_OPT_NAME = 'method';

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

    protected function parseArgs(): void
    {
        $longOpts = [];

        // --class=MyClass - required value
        $longOpts[] = self::CLASS_CMD_OPT_NAME . ':';

        // --method=myMethod - required value
        $longOpts[] = self::METHOD_CMD_OPT_NAME . ':';

        $parsedCliOptions = getopt(/* shortOpts */ '', $longOpts);

        $this->appCodeClass = $this->checkRequiredCliOption(self::CLASS_CMD_OPT_NAME, $parsedCliOptions);
        $this->appCodeMethod = $this->checkRequiredCliOption(self::METHOD_CMD_OPT_NAME, $parsedCliOptions);
    }

    protected function runImpl(): void
    {
        $this->callAppCode();
    }

    protected function cliHelpOptions(): string
    {
        return ' --' . self::CLASS_CMD_OPT_NAME . /** @lang text */ '=<class name>'
               . ' --' . self::METHOD_CMD_OPT_NAME . /** @lang text */ '=<method name>';
    }
}
