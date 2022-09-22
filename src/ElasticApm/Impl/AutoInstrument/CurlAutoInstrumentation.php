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

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\AutoInstrument\Util\AutoInstrumentationUtil;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlAutoInstrumentation extends AutoInstrumentationBase
{
    private const HANDLE_TRACKER_MAX_COUNT_HIGH_WATER_MARK = 2000;
    private const HANDLE_TRACKER_MAX_COUNT_LOW_WATER_MARK = 1000;

    private const CURL_INIT_ID = 1;
    public const CURL_SETOPT_ID = 2;
    public const CURL_SETOPT_ARRAY_ID = 3;
    public const CURL_COPY_HANDLE_ID = 4;
    public const CURL_EXEC_ID = 5;
    private const CURL_CLOSE_ID = 6;

    /** @var Logger */
    private $logger;

    /** @var array<int, CurlHandleTracker> */
    private $handleIdToTracker = [];

    public function __construct(Tracer $tracer)
    {
        parent::__construct($tracer);

        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    /** @inheritDoc */
    public function name(): string
    {
        return InstrumentationNames::CURL;
    }

    /** @inheritDoc */
    public function otherNames(): array
    {
        return [InstrumentationNames::HTTP_CLIENT];
    }

    /** @inheritDoc */
    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('curl')) {
            return;
        }

        $this->registerDelegatingToHandleTracker($ctx, 'curl_init', self::CURL_INIT_ID);
        $this->registerDelegatingToHandleTracker($ctx, 'curl_setopt', self::CURL_SETOPT_ID);
        $this->registerDelegatingToHandleTracker($ctx, 'curl_setopt_array', self::CURL_SETOPT_ARRAY_ID);
        $this->registerDelegatingToHandleTracker($ctx, 'curl_copy_handle', self::CURL_COPY_HANDLE_ID);
        $this->registerDelegatingToHandleTracker($ctx, 'curl_exec', self::CURL_EXEC_ID);
        $this->registerDelegatingToHandleTracker($ctx, 'curl_close', self::CURL_CLOSE_ID);
    }

    public function registerDelegatingToHandleTracker(
        RegistrationContextInterface $ctx,
        string $funcName,
        int $funcId
    ): void {
        $ctx->interceptCallsToFunction(
            $funcName,
            /**
             * @param mixed[] $interceptedCallArgs
             *
             * @return null|callable(int, bool, mixed): void
             */
            function (array $interceptedCallArgs) use ($funcName, $funcId): ?callable {
                return $this->preHook($funcName, $funcId, $interceptedCallArgs);
            }
        );
    }

    /**
     * @param string  $funcName
     * @param int     $funcId
     * @param mixed[] $interceptedCallArgs Intercepted call arguments
     *
     * @return null|callable(int, bool, mixed): void
     */
    private function preHook(string $funcName, int $funcId, array $interceptedCallArgs): ?callable
    {
        $curlHandleTracker = $this->createHandleTracker($funcName, $funcId, $interceptedCallArgs);
        if ($curlHandleTracker === null) {
            return null;
        }

        /**
         * @param int   $numberOfStackFramesToSkip
         * @param bool  $hasExitedByException
         * @param mixed $returnValueOrThrown Return value of the intercepted call or thrown object
         */
        return function (
            int $numberOfStackFramesToSkip,
            bool $hasExitedByException,
            $returnValueOrThrown
        ) use (
            $funcName,
            $funcId,
            $interceptedCallArgs,
            $curlHandleTracker
        ): void {
            $this->postHook(
                $funcName,
                $funcId,
                $interceptedCallArgs,
                $curlHandleTracker,
                $numberOfStackFramesToSkip + 1,
                $hasExitedByException,
                $returnValueOrThrown
            );
        };
    }

    /**
     * @param string            $dbgFuncName
     * @param int               $funcId
     * @param mixed[]           $interceptedCallArgs
     * @param CurlHandleTracker $curlHandleTracker
     * @param int               $numberOfStackFramesToSkip
     * @param bool              $hasExitedByException
     * @param mixed             $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    private function postHook(
        string $dbgFuncName,
        int $funcId,
        array $interceptedCallArgs,
        CurlHandleTracker $curlHandleTracker,
        int $numberOfStackFramesToSkip,
        bool $hasExitedByException,
        $returnValueOrThrown
    ): void {
        AutoInstrumentationUtil::assertInterceptedCallNotExitedByException(
            $hasExitedByException,
            ['functionName' => $dbgFuncName]
        );

        switch ($funcId) {
            case self::CURL_INIT_ID:
            case self::CURL_COPY_HANDLE_ID:
                $this->setTrackerHandle($curlHandleTracker, $returnValueOrThrown);
                return;

            // no need to handle self::CURL_CLOSE because null is returned in preHook

            default:
                $curlHandleTracker->postHook(
                    $dbgFuncName,
                    $funcId,
                    $numberOfStackFramesToSkip + 1,
                    $interceptedCallArgs,
                    $returnValueOrThrown
                );
        }
    }

    private function addToHandleIdToTracker(int $handleId, CurlHandleTracker $curlHandleTracker): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Adding to curl handle ID to CurlHandleTracker map...', ['handleId' => $handleId]);

        $handleIdToTrackerCount = count($this->handleIdToTracker);
        if ($handleIdToTrackerCount >= self::HANDLE_TRACKER_MAX_COUNT_HIGH_WATER_MARK) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'curl handle ID to CurlHandleTracker map reached its max capacity - purging it...',
                ['handleIdToTrackerCount' => $handleIdToTrackerCount]
            );

            $this->handleIdToTracker = array_slice(
                $this->handleIdToTracker,
                $handleIdToTrackerCount - self::HANDLE_TRACKER_MAX_COUNT_LOW_WATER_MARK
            );
        }

        $this->handleIdToTracker[$handleId] = $curlHandleTracker;
    }

    /**
     * @param Logger  $logger
     * @param string  $dbgFuncName
     * @param mixed[] $interceptedCallArgs
     *
     * @return ?CurlHandleWrapped
     */
    public static function extractCurlHandleFromArgs(
        Logger $logger,
        string $dbgFuncName,
        array $interceptedCallArgs
    ): ?CurlHandleWrapped {
        if (count($interceptedCallArgs) !== 0) {
            $curlHandle = $interceptedCallArgs[0];
            if (CurlHandleWrapped::isValidValue($curlHandle)) {
                /** @var resource|object $curlHandle */
                return new CurlHandleWrapped($curlHandle);
            }
        }

        $ctxToLog = [
            'functionName'                => $dbgFuncName,
            'count($interceptedCallArgs)' => count($interceptedCallArgs),
        ];
        if (count($interceptedCallArgs) !== 0) {
            $ctxToLog['firstArgumentType'] = DbgUtil::getType($interceptedCallArgs[0]);
            $ctxToLog['interceptedCallArgs'] = $logger->possiblySecuritySensitive($interceptedCallArgs);
        }
        ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Expected curl handle to be the first argument but it is not', $ctxToLog);
        return null;
    }


    /**
     * @param string  $dbgFuncName
     * @param mixed[] $interceptedCallArgs
     *
     * @return int|null
     */
    private function findHandleId(string $dbgFuncName, array $interceptedCallArgs): ?int
    {
        $curlHandle = self::extractCurlHandleFromArgs($this->logger, $dbgFuncName, $interceptedCallArgs);
        if ($curlHandle === null) {
            return null;
        }

        $handleId = $curlHandle->asInt();

        if (!array_key_exists($handleId, $this->handleIdToTracker)) {
            ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Not found in curl handle ID to CurlHandleTracker map', ['handleId' => $handleId]);
            return null;
        }

        return $handleId;
    }

    /**
     * @param string  $dbgFuncName
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker|null
     */
    private function findHandleTracker(string $dbgFuncName, array $interceptedCallArgs): ?CurlHandleTracker
    {
        $handleId = $this->findHandleId($dbgFuncName, $interceptedCallArgs);

        return $handleId === null ? null : $this->handleIdToTracker[$handleId];
    }

    /**
     * @param string  $dbgFuncName
     * @param int     $funcId
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker|null
     */
    public function createHandleTracker(
        string $dbgFuncName,
        int $funcId,
        array $interceptedCallArgs
    ): ?CurlHandleTracker {
        switch ($funcId) {
            case self::CURL_INIT_ID:
                return $this->curlInitPreHook($interceptedCallArgs);

            case self::CURL_COPY_HANDLE_ID:
                return $this->curlCopyHandlePreHook($dbgFuncName, $interceptedCallArgs);

            case self::CURL_CLOSE_ID:
                $this->curlClosePreHook($dbgFuncName, $interceptedCallArgs);
                return null;

            default:
                $curlHandleTracker = $this->findHandleTracker($dbgFuncName, $interceptedCallArgs);
                if ($curlHandleTracker !== null) {
                    $curlHandleTracker->preHook($dbgFuncName, $funcId, $interceptedCallArgs);
                }
                return $curlHandleTracker;
        }
    }

    /**
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker
     */
    public function curlInitPreHook(array $interceptedCallArgs): CurlHandleTracker
    {
        $curlHandleTracker = new CurlHandleTracker($this->tracer);
        $curlHandleTracker->curlInitPreHook($interceptedCallArgs);
        return $curlHandleTracker;
    }

    /**
     * @param CurlHandleTracker $curlHandleTracker
     * @param mixed             $curlHandle
     */
    public function setTrackerHandle(CurlHandleTracker $curlHandleTracker, $curlHandle): void
    {
        $handleId = $curlHandleTracker->setHandle($curlHandle);
        if ($handleId !== null) {
            $this->addToHandleIdToTracker($handleId, $curlHandleTracker);
        }
    }

    /**
     * @param string  $dbgFuncName
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker|null
     */
    public function curlCopyHandlePreHook(string $dbgFuncName, array $interceptedCallArgs): ?CurlHandleTracker
    {
        $srcCurlHandleTracker = $this->findHandleTracker($dbgFuncName, $interceptedCallArgs);
        if ($srcCurlHandleTracker === null) {
            return null;
        }

        return $srcCurlHandleTracker->copy();
    }

    /**
     * @param string  $dbgFuncName
     * @param mixed[] $interceptedCallArgs
     */
    public function curlClosePreHook(string $dbgFuncName, array $interceptedCallArgs): void
    {
        $handleId = $this->findHandleId($dbgFuncName, $interceptedCallArgs);
        if ($handleId === null) {
            return;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Removing from curl handle ID to CurlHandleTracker map...', ['handleId' => $handleId]);

        unset($this->handleIdToTracker[$handleId]);
    }
}
