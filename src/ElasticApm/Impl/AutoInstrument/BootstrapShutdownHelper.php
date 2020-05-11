<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\TransactionInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BootstrapShutdownHelper
{
    private const AUTOLOAD_FQ_CLASS_NAME_PREFIX = 'Elastic\\Apm\\';

    /** @var TransactionInterface */
    private static $transactionForRequest;

    /** @var int */
    private static $autoloadFqClassNamePrefixLength;

    /** @var string */
    private static $elasticApmSrcDir;

    /** @var int */
    private static $maxEnabledLogLevel;

    public static function bootstrap(int $maxEnabledLogLevel, string $srcDir): bool
    {
        if (!extension_loaded('elasticapm')) {
            return false;
        }

        self::$maxEnabledLogLevel = $maxEnabledLogLevel;
        self::$elasticApmSrcDir = $srcDir . DIRECTORY_SEPARATOR . 'ElasticApm';
        self::$autoloadFqClassNamePrefixLength = strlen(self::AUTOLOAD_FQ_CLASS_NAME_PREFIX);

        self::logDebug(
            'Starting bootstrap sequence...'
            . " maxEnabledLogLevel: $maxEnabledLogLevel. srcDir: `$srcDir'.",
            __LINE__,
            __FUNCTION__
        );

        try {
            self::registerAutoloader();

            /** @var Tracer|null */
            $tracer = self::buildTracer();
            if (is_null($tracer)) {
                self::logDebug(
                    'Successfully completed bootstrap sequence - tracing is disabled',
                    __LINE__,
                    __FUNCTION__
                );
                return true;
            }

            self::beginTransactionForRequest();
            InterceptionManager::init($tracer);
        } catch (Throwable $ex) {
            self::logCritical(
                'One of the steps in bootstrap sequence failed.'
                . ' Exception message: ' . $ex->getMessage()
                . PHP_EOL . 'Stack trace:' . PHP_EOL . $ex->getTraceAsString(),
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        self::logDebug('Successfully completed bootstrap sequence', __LINE__, __FUNCTION__);
        return true;
    }

    public static function shutdown(): void
    {
        self::logDebug('Starting shutdown sequence...', __LINE__, __FUNCTION__);

        try {
            self::endTransactionForRequest();
        } catch (Throwable $ex) {
            self::logCritical(
                'One of the steps in shutdown sequence failed - skipping the rest of the steps.'
                . ' Exception message: ' . $ex->getMessage()
                . PHP_EOL . 'Stack trace:' . PHP_EOL . $ex->getTraceAsString(),
                __LINE__,
                __FUNCTION__
            );
        }

        self::logDebug('Successfully completed shutdown sequence', __LINE__, __FUNCTION__);
    }

    private static function registerAutoloader(): void
    {
        spl_autoload_register(__CLASS__ . '::' . 'autoloadCodeForClass', /* throw: */ true);
    }

    private static function shouldAutoloadCodeForClass(string $fqClassName): bool
    {
        // does the class use the namespace prefix?
        return strncmp(self::AUTOLOAD_FQ_CLASS_NAME_PREFIX, $fqClassName, self::$autoloadFqClassNamePrefixLength) === 0;
    }

    /**
     * @param string $fqClassName
     *
     * @see          registerAutoloader()
     * @noinspection PhpUnused
     */
    public static function autoloadCodeForClass(string $fqClassName): void
    {
        // Example of $fqClassName: Elastic\Apm\Impl\Util\Assert

        self::logTrace("Entered with fqClassName: `$fqClassName'", __LINE__, __FUNCTION__);

        if (!self::shouldAutoloadCodeForClass($fqClassName)) {
            self::logTrace(
                "shouldAutoloadCodeForClass returned false."
                . " fqClassName: `$fqClassName'",
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        // get the relative class name
        $relativeClass = substr($fqClassName, self::$autoloadFqClassNamePrefixLength);
        $classSrcFileRelative = ((DIRECTORY_SEPARATOR === '\\')
                ? $relativeClass
                : str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)) . '.php';
        $classSrcFileAbsolute = self::$elasticApmSrcDir . DIRECTORY_SEPARATOR . $classSrcFileRelative;

        if (file_exists($classSrcFileAbsolute)) {
            self::logTrace("About to execute require `$classSrcFileAbsolute' ...", __LINE__, __FUNCTION__);
            /** @noinspection PhpIncludeInspection */
            require $classSrcFileAbsolute;
        } else {
            self::logTrace(
                "File with the code for class doesn't exist."
                . " classSrcFile: `$classSrcFileAbsolute'. fqClassName: `$fqClassName'",
                __LINE__,
                __FUNCTION__
            );
        }
    }

    /**
     * @return mixed
     */
    private static function buildTracer()
    {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!GlobalTracerHolder::isSet())
        && $assertProxy->info(
            '!GlobalTracerHolder::isSet()',
            ['GlobalTracerHolder::get()' => GlobalTracerHolder::get()]
        );

        return GlobalTracerHolder::get()->isNoop() ? null : GlobalTracerHolder::get();
    }

    private static function beginTransactionForRequest(): void
    {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!isset(self::$transactionForRequest))
        && $assertProxy->info(
            '!isset(self::$transactionForRequest)',
            ['transactionForRequest' => self::$transactionForRequest]
        );

        $timestamp = null;
        $timestampAsString = '';
        if (ArrayUtil::getValueIfKeyExists('REQUEST_TIME_FLOAT', $_SERVER, /* ref */ $timestampAsString)) {
            $timestampInSeconds = floatval($timestampAsString);
            $timestamp = $timestampInSeconds * 1000000;
            if (PHP_INT_SIZE >= 8) {
                self::logTrace('Using intval($timestamp): ' . intval($timestamp), __LINE__, __FUNCTION__);
            } else {
                self::logTrace("Using timestamp: $timestamp", __LINE__, __FUNCTION__);
            }
        }

        $txName = 'UNKNOWN';
        if (array_key_exists('REQUEST_URI', $_SERVER)) {
            $txName = '';
            if (array_key_exists('REQUEST_METHOD', $_SERVER)) {
                $txName = $_SERVER['REQUEST_METHOD'] . ' ';
            }
            $txName .= $_SERVER['REQUEST_URI'];
        }
        // TODO: Sergey Kleyman: Implement: beginTransactionForRequest - get real data
        self::$transactionForRequest = ElasticApm::beginCurrentTransaction(
            $txName,
            Constants::TRANSACTION_TYPE_REQUEST,
            $timestamp
        );
    }

    private static function endTransactionForRequest(): void
    {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(isset(self::$transactionForRequest))
        && $assertProxy->info('isset(self::$transactionForRequest)', []);

        if (self::$transactionForRequest->hasEnded()) {
            return;
        }

        // TODO: Sergey Kleyman: Implement: beginTransactionForRequest - fill data before ending
        // TODO: Sergey Kleyman: Implement: beginTransactionForRequest - get real duration and fill other data
        self::$transactionForRequest->end();
    }

    private static function logTrace(
        string $message,
        int $sourceCodeLine,
        string $sourceCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::log(
        /**
         * ELASTICAPM_* constants are provided by the elasticapm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTICAPM_LOG_LEVEL_TRACE,
            $message,
            $sourceCodeLine,
            $sourceCodeFunc
        );
    }

    private static function logDebug(
        string $message,
        int $sourceCodeLine,
        string $sourceCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::log(
        /**
         * ELASTICAPM_* constants are provided by the elasticapm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTICAPM_LOG_LEVEL_DEBUG,
            $message,
            $sourceCodeLine,
            $sourceCodeFunc
        );
    }

    private static function logCritical(
        string $message,
        int $sourceCodeLine,
        string $sourceCodeFunc
    ): void {
        /** @noinspection PhpUndefinedConstantInspection */
        self::log(
        /**
         * ELASTICAPM_* constants are provided by the elasticapm extension
         *
         * @phpstan-ignore-next-line
         */
            ELASTICAPM_LOG_LEVEL_CRITICAL,
            $message,
            $sourceCodeLine,
            $sourceCodeFunc
        );
    }

    private static function log(
        int $statementLevel,
        string $message,
        int $sourceCodeLine,
        string $sourceCodeFunc
    ): void {
        if (self::$maxEnabledLogLevel < $statementLevel) {
            return;
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
            'Bootstrap' /* category */,
            __FILE__,
            $sourceCodeLine,
            $sourceCodeFunc,
            $message
        );
    }
}
