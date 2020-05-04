<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Backend
{
    /** @var int */
    private $maxEnabledLevel;

    /** @var bool */
    private static $isElasticApmExtensionLoaded;

    public function __construct()
    {
        $this->maxEnabledLevel = Level::TRACE;
    }

    public function maxEnabledLevel(): int
    {
        return $this->maxEnabledLevel;
    }

    /**
     * @param int          $statementLevel
     * @param string       $message
     * @param array<mixed> $context
     * @param int          $sourceCodeLine
     * @param string       $sourceCodeMethod
     * @param LoggerData   $loggerData
     * @param int          $numberOfStackFramesToSkip
     */
    public function log(
        int $statementLevel,
        string $message,
        array $context,
        int $sourceCodeLine,
        string $sourceCodeMethod,
        LoggerData $loggerData,
        int $numberOfStackFramesToSkip
    ): void {
        self::logEx(
            $statementLevel,
            $message,
            array_merge($loggerData->attachedContext, $context),
            $loggerData->sourceCodeFile,
            $sourceCodeLine,
            /* sourceCodeFunc */ $loggerData->className . '::' . $sourceCodeMethod,
            $numberOfStackFramesToSkip + 1
        );
    }

    /**
     * @param int          $statementLevel
     * @param string       $message
     * @param array<mixed> $context
     * @param string       $sourceCodeFile
     * @param int          $sourceCodeLine
     * @param string       $sourceCodeFunc
     * @param int          $numberOfStackFramesToSkip
     */
    public static function logEx(
        int $statementLevel,
        string $message,
        array $context,
        string $sourceCodeFile,
        int $sourceCodeLine,
        string $sourceCodeFunc,
        int $numberOfStackFramesToSkip
    ): void {
        if (!isset(self::$isElasticApmExtensionLoaded)) {
            self::$isElasticApmExtensionLoaded = extension_loaded('elasticapm');
        }

        if (!self::$isElasticApmExtensionLoaded) {
            return;
        }

        $contextToStringBuilder = new ObjectToStringBuilder();
        foreach ($context as $key => $value) {
            $contextToStringBuilder->add($key, $value);
        }

        $messageWithContext = $message . $contextToStringBuilder->build();

        if ($statementLevel <= Level::ERROR) {
            $messageWithContext .= PHP_EOL;
            $messageWithContext .= TextUtil::indent('Stack trace:');
            $messageWithContext .= PHP_EOL;
            $messageWithContext .= TextUtil::indent(
                DbgUtil::formatCurrentStackTrace($numberOfStackFramesToSkip + 1),
                /*level: */ 2
            );
        }

        /**
         * elasticapm_* functions are provided by the elasticapm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        \elasticapm_log(
            0 /* $isForced */,
            $statementLevel,
            $sourceCodeFile,
            $sourceCodeLine,
            $sourceCodeFunc,
            $messageWithContext
        );
    }
}
