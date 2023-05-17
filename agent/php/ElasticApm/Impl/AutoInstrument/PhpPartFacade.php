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

namespace Elastic\Apm\Impl\AutoInstrument;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use Elastic\Apm\Impl\Util\HiddenConstructorTrait;
use RuntimeException;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * Called by elastic_apm extension
 *
 * @noinspection PhpUnused
 */
final class PhpPartFacade
{
    /**
     * Constructor is hidden because instance() should be used instead
     */
    use HiddenConstructorTrait;

    /** @var self|null */
    private static $singletonInstance = null;

    /** @var TransactionForExtensionRequest|null */
    private $transactionForExtensionRequest = null;

    /** @var InterceptionManager|null */
    private $interceptionManager = null;

    private function __construct(float $requestInitStartTime)
    {
        if (!ElasticApmExtensionUtil::isLoaded()) {
            throw new RuntimeException(ElasticApmExtensionUtil::EXTENSION_NAME . ' extension is not loaded');
        }

        $tracer = self::buildTracer();
        if ($tracer === null) {
            BootstrapStageLogger::logDebug(
                'Cutting bootstrap sequence short - tracing is disabled',
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        $this->transactionForExtensionRequest = new TransactionForExtensionRequest($tracer, $requestInitStartTime);
        $this->interceptionManager = new InterceptionManager($tracer);
    }

    /**
     * Called by elastic_apm extension
     *
     * @noinspection PhpUnused
     *
     * @param int   $maxEnabledLogLevel
     * @param float $requestInitStartTime
     *
     * @return bool
     */
    public static function bootstrap(int $maxEnabledLogLevel, float $requestInitStartTime): bool
    {
        BootstrapStageLogger::configure($maxEnabledLogLevel);
        BootstrapStageLogger::logDebug(
            'Starting bootstrap sequence...' . " maxEnabledLogLevel: $maxEnabledLogLevel",
            __LINE__,
            __FUNCTION__
        );

        if (self::$singletonInstance !== null) {
            BootstrapStageLogger::logCritical(
                'bootstrap() is called even though singleton instance is already created'
                . ' (probably bootstrap() is called more than once)',
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        try {
            self::$singletonInstance = new self($requestInitStartTime);
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'One of the steps in bootstrap sequence let a throwable escape',
                __LINE__,
                __FUNCTION__
            );
            return false;
        }

        BootstrapStageLogger::logDebug('Successfully completed bootstrap sequence', __LINE__, __FUNCTION__);
        return true;
    }

    private static function singletonInstance(): self
    {
        if (self::$singletonInstance === null) {
            throw new RuntimeException(
                'Trying to use singleton instance that is not set'
                . ' (probably either before call to bootstrap() or after failed call to bootstrap())'
            );
        }

        return self::$singletonInstance;
    }

    /**
     * Called by elastic_apm extension
     *
     * @noinspection PhpUnused
     *
     * @param int         $interceptRegistrationId
     * @param object|null $thisObj
     * @param mixed       ...$interceptedCallArgs
     *
     * @return bool
     */
    public static function interceptedCallPreHook(
        int $interceptRegistrationId,
        ?object $thisObj,
        ...$interceptedCallArgs
    ): bool {
        $interceptionManager = self::singletonInstance()->interceptionManager;
        if ($interceptionManager === null) {
            return false;
        }

        self::ensureHaveLatestDataDeferredByExtension();

        return $interceptionManager->interceptedCallPreHook(
            $interceptRegistrationId,
            $thisObj,
            $interceptedCallArgs
        );
    }

    /**
     * Called by elastic_apm extension
     *
     * @noinspection PhpUnused
     *
     * @param bool  $hasExitedByException
     * @param mixed $returnValueOrThrown
     */
    public static function interceptedCallPostHook(bool $hasExitedByException, $returnValueOrThrown): void
    {
        $interceptionManager = self::singletonInstance()->interceptionManager;
        assert($interceptionManager !== null);

        self::ensureHaveLatestDataDeferredByExtension();

        $interceptionManager->interceptedCallPostHook(
            1 /* <- $numberOfStackFramesToSkip */,
            $hasExitedByException,
            $returnValueOrThrown
        );
    }

    /**
     * @param string  $dbgCallDesc
     * @param Closure $implFunc
     *
     * @return void
     *
     * @phpstan-param Closure(self): void $implFunc
     */
    private static function callFromExtension(string $dbgCallDesc, Closure $implFunc): void
    {
        BootstrapStageLogger::logDebug(
            'Starting to handle ' . $dbgCallDesc . ' call...',
            __LINE__,
            __FUNCTION__
        );

        if (self::$singletonInstance === null) {
            BootstrapStageLogger::logWarning(
                'Received ' . $dbgCallDesc . ' call but singleton instance is not created'
                . ' (probably because bootstrap sequence failed)',
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        try {
            $implFunc(self::singletonInstance());
        } catch (Throwable $throwable) {
            BootstrapStageLogger::logCriticalThrowable(
                $throwable,
                'Handling ' . $dbgCallDesc
                . ' call let a throwable escape - skipping the rest of the steps',
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        BootstrapStageLogger::logDebug(
            'Successfully finished handling ' . $dbgCallDesc . ' call...',
            __LINE__,
            __FUNCTION__
        );
    }

    /**
     * @param string  $dbgCallDesc
     * @param Closure $implFunc
     *
     * @return void
     *
     * @phpstan-param Closure(TransactionForExtensionRequest): void $implFunc
     */
    private static function callWithTransactionForExtensionRequest(string $dbgCallDesc, Closure $implFunc): void
    {
        self::callFromExtension(
            $dbgCallDesc,
            function (PhpPartFacade $singletonInstance) use ($implFunc): void {
                if ($singletonInstance->transactionForExtensionRequest === null) {
                    BootstrapStageLogger::logDebug(
                        'Received call but transactionForExtensionRequest is null'
                        . ' - just returning...',
                        __LINE__,
                        __FUNCTION__
                    );
                    return;
                }

                $implFunc($singletonInstance->transactionForExtensionRequest);
            }
        );
    }

    public static function ensureHaveLatestDataDeferredByExtension(): void
    {
        self::callWithTransactionForExtensionRequest(
            __FUNCTION__,
            function (TransactionForExtensionRequest $transactionForExtensionRequest): void {
                self::ensureHaveLastErrorData($transactionForExtensionRequest);
            }
        );
    }

    private static function ensureHaveLastErrorData(
        TransactionForExtensionRequest $transactionForExtensionRequest
    ): void {
        if (!$transactionForExtensionRequest->getConfig()->captureErrors()) {
            return;
        }

        /**
         * The last thrown should be fetched before last PHP error because if the error is for "Uncaught Exception"
         * agent will use the last thrown exception
         */
        self::ensureHaveLastThrown($transactionForExtensionRequest);
        self::ensureHaveLastPhpError($transactionForExtensionRequest);
    }

    private static function ensureHaveLastThrown(TransactionForExtensionRequest $transactionForExtensionRequest): void
    {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @var mixed $lastThrown
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $lastThrown = \elastic_apm_get_last_thrown();
        if ($lastThrown === null) {
            return;
        }

        $transactionForExtensionRequest->setLastThrown($lastThrown);
    }

    /**
     * @param string $expectedType
     * @param mixed  $actualValue
     *
     * @return void
     */
    private static function logUnexpectedType(string $expectedType, $actualValue): void
    {
        BootstrapStageLogger::logCritical(
            'Actual type does not match the expected type'
            . '; ' . 'expected type: ' . $expectedType
            . ', ' . 'actual type: ' . DbgUtil::getType($actualValue)
            . ', ' . 'actual value: ' . LoggableToString::convert($actualValue),
            __LINE__,
            __FUNCTION__
        );
    }

    /**
     * @param string              $expectedKey
     * @param array<mixed, mixed> $actualArray
     *
     * @return bool
     */
    private static function verifyKeyExists(string $expectedKey, array $actualArray): bool
    {
        if (array_key_exists($expectedKey, $actualArray)) {
            return true;
        }

        BootstrapStageLogger::logCritical(
            'Expected key does not exist'
            . '; ' . 'expected key: ' . $expectedKey
            . ', ' . 'actual array keys: ' . json_encode(array_keys($actualArray)),
            __LINE__,
            __FUNCTION__
        );
        return false;
    }

    /**
     * @param array<mixed, mixed> $dataFromExt
     * @param string              $key
     *
     * @return ?int
     */
    private static function getIntFromPhpErrorData(array $dataFromExt, string $key): ?int
    {
        if (!self::verifyKeyExists($key, $dataFromExt)) {
            return null;
        }
        $value = $dataFromExt[$key];
        if (!is_int($value)) {
            self::logUnexpectedType('int', $value);
            return null;
        }
        return $value;
    }

    /**
     * @param array<mixed, mixed> $dataFromExt
     * @param string              $key
     *
     * @return ?string
     */
    private static function getNullableStringFromPhpErrorData(array $dataFromExt, string $key): ?string
    {
        if (!self::verifyKeyExists($key, $dataFromExt)) {
            return null;
        }
        $value = $dataFromExt[$key];
        if (!($value === null || is_string($value))) {
            self::logUnexpectedType('string|null', $value);
            return null;
        }
        return $value;
    }

    /**
     * @param array<mixed, mixed> $dataFromExt
     * @param string              $key
     *
     * @return null|array<string, mixed>[]
     */
    private static function getStackTraceFromPhpErrorData(array $dataFromExt, string $key): ?array
    {
        if (!self::verifyKeyExists($key, $dataFromExt)) {
            return null;
        }
        $stackTrace = $dataFromExt[$key];
        if (!is_array($stackTrace)) {
            self::logUnexpectedType('array', $stackTrace);
            return null;
        }
        if (!ArrayUtil::isList($stackTrace)) {
            BootstrapStageLogger::logCritical(
                'Stack trace array should be a list but it is not'
                . '; ' . 'stackTrace keys: ' . json_encode(array_keys($stackTrace))
                . ', ' . 'stackTrace: ' . LoggableToString::convert($stackTrace),
                __LINE__,
                __FUNCTION__
            );
            return null;
        }

        /** @var array<string, mixed>[] $stackTrace */
        return $stackTrace;
    }

    /**
     * @param array<mixed, mixed> $dataFromExt
     *
     * @return PhpErrorData
     */
    private static function buildPhpErrorData(array $dataFromExt): PhpErrorData
    {
        $result = new PhpErrorData();
        $result->type = self::getIntFromPhpErrorData($dataFromExt, 'type');
        $result->fileName = self::getNullableStringFromPhpErrorData($dataFromExt, 'fileName');
        $result->lineNumber = self::getIntFromPhpErrorData($dataFromExt, 'lineNumber');
        $result->message = self::getNullableStringFromPhpErrorData($dataFromExt, 'message');
        $result->stackTrace = self::getStackTraceFromPhpErrorData($dataFromExt, 'stackTrace');
        return $result;
    }

    private static function ensureHaveLastPhpError(TransactionForExtensionRequest $transactionForExtensionRequest): void
    {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $lastPhpErrorData = \elastic_apm_get_last_php_error();
        if ($lastPhpErrorData === null) {
            return;
        }

        if (is_array($lastPhpErrorData)) {
            BootstrapStageLogger::logDebug(
                'Type of value returned by elastic_apm_get_last_php_error(): ' . DbgUtil::getType($lastPhpErrorData),
                __LINE__,
                __FUNCTION__
            );
        } else {
            BootstrapStageLogger::logCritical(
                'Value returned by elastic_apm_get_last_php_error() is not an array'
                . ', ' . 'returned value type: ' . DbgUtil::getType($lastPhpErrorData)
                . ', ' . 'returned value: ' . $lastPhpErrorData,
                __LINE__,
                __FUNCTION__
            );
            return;
        }
        /** @var array<mixed, mixed> $lastPhpErrorData */

        $transactionForExtensionRequest->onPhpError(self::buildPhpErrorData($lastPhpErrorData));
    }

    /**
     * Called by elastic_apm extension
     *
     * @noinspection PhpUnused
     */
    public static function shutdown(): void
    {
        self::callWithTransactionForExtensionRequest(
            __FUNCTION__,
            function (TransactionForExtensionRequest $transactionForExtensionRequest): void {
                $transactionForExtensionRequest->onShutdown();
            }
        );

        self::$singletonInstance = null;
    }

    /**
     * @return Tracer|null
     */
    private static function buildTracer(): ?Tracer
    {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!GlobalTracerHolder::isValueSet())
        && $assertProxy->withContext(
            '!GlobalTracerHolder::isSet()',
            ['GlobalTracerHolder::get()' => GlobalTracerHolder::getValue()]
        );

        $tracer = GlobalTracerHolder::getValue();
        if ($tracer->isNoop()) {
            return null;
        }

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that($tracer instanceof Tracer)
        && $assertProxy->withContext('$tracer instanceof Tracer', ['get_class($tracer)' => get_class($tracer)]);
        assert($tracer instanceof Tracer);

        return $tracer;
    }

    /**
     * Called by elastic_apm extension
     *
     * @noinspection PhpUnused
     */
    public static function emptyMethod(): void
    {
    }
}
