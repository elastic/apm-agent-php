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
final class DefaultSink implements SinkInterface
{
    /** @var bool */
    private static $isElasticApmExtensionLoaded;

    /**
     * @param int          $statementLevel
     * @param string       $message
     * @param array<mixed> $statementCtx
     * @param string       $category
     * @param string       $sourceCodeFile
     * @param int          $srcCodeLine
     * @param string       $srcCodeFunc
     * @param int          $numberOfStackFramesToSkip
     * @param array<mixed> $attachedCtx
     */
    public static function consume(
        int $statementLevel,
        string $message,
        array $statementCtx,
        string $category,
        string $sourceCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        int $numberOfStackFramesToSkip,
        array $attachedCtx = []
    ): void {
        if (!isset(self::$isElasticApmExtensionLoaded)) {
            self::$isElasticApmExtensionLoaded = extension_loaded('elasticapm');
        }

        if (!self::$isElasticApmExtensionLoaded) {
            return;
        }

        $contextToStringBuilder = new ObjectToStringBuilder();
        foreach ($statementCtx as $key => $value) {
            $contextToStringBuilder->add($key, $value);
        }
        foreach ($attachedCtx as $key => $value) {
            $contextToStringBuilder->add($key, $value);
        }

        $messageWithContext = $message . ' ' . $contextToStringBuilder->build();

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
            $category,
            $sourceCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $messageWithContext
        );
    }
}
