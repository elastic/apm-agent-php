<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\SinkBase;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TestLogSink extends SinkBase
{
    /** @var string */
    private $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    protected function consumePreformatted(
        int $statementLevel,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        string $messageWithContext
    ): void {
        $formattedRecord = $this->prefix;
        $formattedRecord .= '[' . self::levelToString($statementLevel) . ']';
        $formattedRecord .= ' [' . $category . ']';
        $formattedRecord .= ' [' . basename($srcCodeFile) . ':' . $srcCodeLine . ']';
        $formattedRecord .= ' [' . $srcCodeFunc . ']';
        $formattedRecord .= ' ' . $messageWithContext;
        self::consumeFormatted($statementLevel, $formattedRecord);
    }

    private static function consumeFormatted(int $statementLevel, string $formattedRecord): void
    {
        if ($statementLevel <= Level::INFO) {
            print($formattedRecord . PHP_EOL);
        }

        syslog(
            self::levelToSyslog($statementLevel),
            TextUtil::prefixEachLine($formattedRecord, 'Elastic APM PHP tests | ')
        );
    }

    private static function levelToString(int $level): string
    {
        switch ($level) {
            case Level::OFF:
                return 'OFF';

            case Level::CRITICAL:
                return 'CRITICAL';

            case Level::ERROR:
                return 'ERROR';

            case Level::WARNING:
                return 'WARNING';

            case Level::NOTICE:
                return 'NOTICE';

            case Level::INFO:
                return 'INFO';

            case Level::DEBUG:
                return 'DEBUG';

            case Level::TRACE:
                return 'TRACE';

            default:
                return "UNKNOWN ($level)";
        }
    }

    private static function levelToSyslog(int $level): int
    {
        switch ($level) {
            case Level::OFF:
            case Level::CRITICAL:
                return LOG_CRIT;

            case Level::ERROR:
                return LOG_ERR;

            case Level::WARNING:
                return LOG_WARNING;

            case Level::NOTICE:
                return LOG_NOTICE;

            case Level::INFO:
            default:
                return LOG_INFO;

            case Level::DEBUG:
            case Level::TRACE:
                return LOG_DEBUG;
        }
    }
}
