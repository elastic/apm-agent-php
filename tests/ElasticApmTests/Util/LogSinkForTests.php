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

namespace ElasticApmTests\Util;

use DateTime;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\SinkBase;
use ElasticApmTests\ComponentTests\Util\OsUtilForTests;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LogSinkForTests extends SinkBase
{
    /** @var string */
    private $dbgProcessName;

    /** @var bool */
    private $isStderrDefined;

    public function __construct(string $dbgProcessName)
    {
        $this->dbgProcessName = $dbgProcessName;

        if (!defined('STDERR')) {
            define('STDERR', fopen('php://stderr', 'w'));
        }
        $this->isStderrDefined = defined('STDERR');
    }

    protected function consumePreformatted(
        int $statementLevel,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        string $messageWithContext
    ): void {
        $formattedRecord = '[Elastic APM PHP tests]';
        $formattedRecord .= ' ' . (new DateTime())->format('Y-m-d H:i:s.v P');
        $formattedRecord .= ' [' . self::levelToString($statementLevel) . ']';
        $formattedRecord .= ' [PID: ' . getmypid() . ']';
        $formattedRecord .= ' [' . $this->dbgProcessName . ']';
        $formattedRecord .= ' [' . basename($srcCodeFile) . ':' . $srcCodeLine . ']';
        $formattedRecord .= ' [' . $srcCodeFunc . ']';
        $formattedRecord .= TextUtilForTests::combineWithSeparatorIfNotEmpty(' ', $messageWithContext);
        $this->consumeFormatted($statementLevel, $formattedRecord);
    }

    private function consumeFormatted(int $statementLevel, string $statementText): void
    {
        if (OsUtilForTests::isWindows()) {
            if (OutputDebugString::isEnabled()) {
                OutputDebugString::write($statementText . PHP_EOL);
            }
        } else {
            syslog(self::levelToSyslog($statementLevel), $statementText);
        }

        if ($this->isStderrDefined) {
            fwrite(STDERR, $statementText . PHP_EOL);
        }
    }

    public static function levelToString(int $level): string
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

            case Level::INFO:
            default:
                return LOG_INFO;

            case Level::DEBUG:
            case Level::TRACE:
                return LOG_DEBUG;
        }
    }
}
