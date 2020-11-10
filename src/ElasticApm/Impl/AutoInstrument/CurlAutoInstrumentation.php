<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CurlAutoInstrumentation
{
    use LoggableTrait;

    private const HANDLE_TRACKER_MAX_COUNT_HIGH_WATER_MARK = 2000;
    private const HANDLE_TRACKER_MAX_COUNT_LOW_WATER_MARK = 1000;

    private const CURL_INIT = 'curl_init';
    public const CURL_SETOPT = 'curl_setopt';
    public const CURL_SETOPT_ARRAY = 'curl_setopt_array';
    public const CURL_COPY_HANDLE = 'curl_copy_handle';
    public const CURL_EXEC = 'curl_exec';
    private const CURL_CLOSE = 'curl_close';

    /** @var Tracer */
    private $tracer;

    /** @var Logger */
    private $logger;

    /** @var array<int, CurlHandleTracker> */
    private $handleIdToTracker = [];

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;

        $this->logger = $tracer->loggerFactory()->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('curl')) {
            return;
        }

        $this->registerDelegatingToHandleTracker($ctx, self::CURL_INIT);
        $this->registerDelegatingToHandleTracker($ctx, self::CURL_SETOPT);
        $this->registerDelegatingToHandleTracker($ctx, self::CURL_SETOPT_ARRAY);
        $this->registerDelegatingToHandleTracker($ctx, self::CURL_COPY_HANDLE);
        $this->registerDelegatingToHandleTracker($ctx, self::CURL_EXEC);
        $this->registerDelegatingToHandleTracker($ctx, self::CURL_CLOSE);
    }

    public function registerDelegatingToHandleTracker(RegistrationContextInterface $ctx, string $functionName): void
    {
        $ctx->interceptCallsToFunction(
            $functionName,
            function () use ($functionName): InterceptedCallTrackerInterface {
                return new class ($this, $functionName) implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var CurlAutoInstrumentation */
                    private $owner;

                    /** @var string */
                    private $functionName;

                    /** @var CurlHandleTracker|null */
                    private $curlHandleTracker;

                    /** @var mixed[] */
                    private $interceptedCallArgs;

                    public function __construct(CurlAutoInstrumentation $owner, string $functionName)
                    {
                        $this->owner = $owner;
                        $this->functionName = $functionName;
                    }

                    public function preHook(?object $interceptedCallThis, $interceptedCallArgs): void
                    {
                        self::assertInterceptedCallThisIsNull($interceptedCallThis, $interceptedCallArgs);

                        $this->curlHandleTracker = $this->owner->preHook($this->functionName, $interceptedCallArgs);
                        if (!is_null($this->curlHandleTracker)) {
                            $this->interceptedCallArgs = $interceptedCallArgs;
                        }
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::assertInterceptedCallNotExitedByException(
                            $hasExitedByException,
                            ['functionName' => $this->functionName]
                        );

                        if (!is_null($this->curlHandleTracker)) {
                            $this->owner->postHook(
                                $this->curlHandleTracker,
                                $this->functionName,
                                $numberOfStackFramesToSkip + 1,
                                $this->interceptedCallArgs,
                                $returnValueOrThrown
                            );
                        }
                    }
                };
            }
        );
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
     * @param string  $dbgFunctionName
     * @param mixed[] $interceptedCallArgs
     *
     * @return resource|null
     */
    public static function extractCurlHandleFromArgs(
        Logger $logger,
        string $dbgFunctionName,
        array $interceptedCallArgs
    ) {
        if (count($interceptedCallArgs) !== 0 && is_resource($interceptedCallArgs[0])) {
            return $interceptedCallArgs[0];
        }

        $ctxToLog = [
            'functionName'                => $dbgFunctionName,
            'count($interceptedCallArgs)' => count($interceptedCallArgs),
        ];
        if (count($interceptedCallArgs) !== 0) {
            $ctxToLog['firstArgumentType'] = DbgUtil::getType($interceptedCallArgs[0]);
            $ctxToLog['interceptedCallArgs'] = $logger->possiblySecuritySensitive($interceptedCallArgs);
        }
        ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Expected curl handle to be the first argument but it not', $ctxToLog);
        return null;
    }


    /**
     * @param string  $dbgFunctionName
     * @param mixed[] $interceptedCallArgs
     *
     * @return int|null
     */
    private function findHandleId(string $dbgFunctionName, array $interceptedCallArgs): ?int
    {
        $curlHandle = self::extractCurlHandleFromArgs($this->logger, $dbgFunctionName, $interceptedCallArgs);
        if (is_null($curlHandle)) {
            return null;
        }

        $handleId = intval($curlHandle);

        if (!array_key_exists($handleId, $this->handleIdToTracker)) {
            ($loggerProxy = $this->logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Not found in curl handle ID to CurlHandleTracker map', ['handleId' => $handleId]);
            return null;
        }

        return $handleId;
    }

    /**
     * @param string  $dbgFunctionName
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker|null
     */
    private function findHandleTracker(string $dbgFunctionName, array $interceptedCallArgs): ?CurlHandleTracker
    {
        $handleId = $this->findHandleId($dbgFunctionName, $interceptedCallArgs);

        return is_null($handleId) ? null : $this->handleIdToTracker[$handleId];
    }

    /**
     * @param string  $functionName
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker|null
     */
    public function preHook(string $functionName, array $interceptedCallArgs): ?CurlHandleTracker
    {
        switch ($functionName) {
            case self::CURL_INIT:
                return $this->curlInitPreHook($interceptedCallArgs);

            case self::CURL_COPY_HANDLE:
                return $this->curlCopyHandlePreHook($interceptedCallArgs);

            case self::CURL_CLOSE:
                $this->curlClosePreHook($interceptedCallArgs);
                return null;

            default:
                $curlHandleTracker = $this->findHandleTracker($functionName, $interceptedCallArgs);
                if (!is_null($curlHandleTracker)) {
                    $curlHandleTracker->preHook($functionName, $interceptedCallArgs);
                }
                return $curlHandleTracker;
        }
    }

    /**
     * @param CurlHandleTracker $curlHandleTracker
     * @param string            $functionName
     * @param int               $numberOfStackFramesToSkip
     * @param mixed[]           $interceptedCallArgs
     * @param mixed             $returnValueOrThrown
     */
    public function postHook(
        CurlHandleTracker $curlHandleTracker,
        string $functionName,
        int $numberOfStackFramesToSkip,
        array $interceptedCallArgs,
        $returnValueOrThrown
    ): void {
        switch ($functionName) {
            case self::CURL_INIT:
            case self::CURL_COPY_HANDLE:
                $this->setTrackerHandle($curlHandleTracker, $returnValueOrThrown);
                return;

            // no need to handle self::CURL_CLOSE because null is returned in preHook

            default:
                $curlHandleTracker->postHook(
                    $functionName,
                    $numberOfStackFramesToSkip + 1,
                    $interceptedCallArgs,
                    $returnValueOrThrown
                );
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
     * @param mixed             $returnValueOrThrown
     */
    public function setTrackerHandle(CurlHandleTracker $curlHandleTracker, $returnValueOrThrown): void
    {
        $handleId = $curlHandleTracker->setHandle($returnValueOrThrown);
        if (!is_null($handleId)) {
            $this->addToHandleIdToTracker($handleId, $curlHandleTracker);
        }
    }

    /**
     * @param mixed[] $interceptedCallArgs
     *
     * @return CurlHandleTracker|null
     */
    public function curlCopyHandlePreHook(array $interceptedCallArgs): ?CurlHandleTracker
    {
        $srcCurlHandleTracker = $this->findHandleTracker(self::CURL_COPY_HANDLE, $interceptedCallArgs);
        if (is_null($srcCurlHandleTracker)) {
            return null;
        }

        return $srcCurlHandleTracker->copy();
    }

    /**
     * @param mixed[] $interceptedCallArgs
     */
    public function curlClosePreHook(array $interceptedCallArgs): void
    {
        $handleId = $this->findHandleId(self::CURL_CLOSE, $interceptedCallArgs);
        if (is_null($handleId)) {
            return;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Removing from curl handle ID to CurlHandleTracker map...', ['handleId' => $handleId]);

        unset($this->handleIdToTracker[$handleId]);
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['logger', 'tracer'];
    }
}
