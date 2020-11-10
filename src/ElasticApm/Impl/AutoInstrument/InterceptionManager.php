<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class InterceptionManager
{
    /** @var Registration[] */
    private $interceptedCallRegistrations;

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer)
    {
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::INTERCEPTION, __NAMESPACE__, __CLASS__, __FILE__);

        $this->loadPlugins($tracer);
    }

    private function loadPlugins(Tracer $tracer): void
    {
        $registerCtx = new RegistrationContext();
        $this->loadPluginsImpl($tracer, $registerCtx);

        $this->interceptedCallRegistrations = $registerCtx->interceptedCallRegistrations;
    }

    private function loadPluginsImpl(Tracer $tracer, RegistrationContext $registerCtx): void
    {
        $builtinPlugin = new BuiltinPlugin($tracer);
        $registerCtx->dbgCurrentPluginIndex = 0;
        $registerCtx->dbgCurrentPluginDesc = $builtinPlugin->getDescription();
        $builtinPlugin->register($registerCtx);

        // self::loadConfiguredPlugins();
    }

    /**
     * @param int         $numberOfStackFramesToSkip
     * @param int         $interceptRegistrationId
     * @param object|null $thisObj
     * @param mixed[]     $interceptedCallArgs
     *
     * @return mixed
     * @throws Throwable
     */
    public function interceptedCall(
        int $numberOfStackFramesToSkip,
        int $interceptRegistrationId,
        ?object $thisObj,
        array $interceptedCallArgs
    ) {
        $localLogger = $this->logger->inherit()->addContext('interceptRegistrationId', $interceptRegistrationId);

        $callTracker = $this->interceptedCallPreHook(
            $localLogger,
            $interceptRegistrationId,
            $thisObj,
            $interceptedCallArgs
        );

        $hasExitedByException = false;
        try {
            /**
             * elastic_apm_* functions are provided by the elastic_apm extension
             *
             * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
             * @phpstan-ignore-next-line
             */
            $returnValueOrThrown = \elastic_apm_call_intercepted_original();
        } catch (Throwable $throwable) {
            $hasExitedByException = true;
            $returnValueOrThrown = $throwable;
        }

        if (!is_null($callTracker)) {
            $this->interceptedCallPostHook(
                $numberOfStackFramesToSkip + 1,
                $localLogger,
                $callTracker,
                $hasExitedByException,
                $returnValueOrThrown
            );
        }

        if ($hasExitedByException) {
            throw $returnValueOrThrown;
        }

        return $returnValueOrThrown;
    }

    /**
     * @param Logger      $localLogger
     * @param int         $interceptRegistrationId
     * @param object|null $thisObj
     * @param mixed[]     $interceptedCallArgs
     *
     * @return InterceptedCallTrackerInterface|null
     */
    private function interceptedCallPreHook(
        Logger $localLogger,
        int $interceptRegistrationId,
        ?object $thisObj,
        array $interceptedCallArgs
    ) {
        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        $registration
            = ArrayUtil::getValueIfKeyExistsElse($interceptRegistrationId, $this->interceptedCallRegistrations, null);
        if (is_null($registration)) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There is no registration with the given interceptRegistrationId - returning null');
            return null;
        }

        $localLogger->addContext('registration', $registration);

        try {
            /** @var InterceptedCallTrackerInterface $callTracker */
            $callTracker = ($registration->factory)();
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable(
                $throwable,
                ClassNameUtil::fqToShort(InterceptedCallTrackerInterface::class)
                . ' factory has let a Throwable to escape - returning null'
            );
            return null;
        }

        try {
            $callTracker->preHook($thisObj, $interceptedCallArgs);
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable(
                $throwable,
                ClassNameUtil::fqToShort(InterceptedCallTrackerInterface::class)
                . ' preHook() has let a Throwable to escape - returning null'
            );
            return null;
        }

        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting - returning non-null');
        return $callTracker;
    }

    /**
     * @param int                             $numberOfStackFramesToSkip
     * @param Logger                          $localLogger
     * @param InterceptedCallTrackerInterface $callTracker
     * @param bool                            $hasExitedByException
     * @param mixed|Throwable                 $returnValueOrThrown Return value of the intercepted call
     *                                                             or the object thrown by the intercepted call
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    private function interceptedCallPostHook(
        int $numberOfStackFramesToSkip,
        Logger $localLogger,
        InterceptedCallTrackerInterface $callTracker,
        bool $hasExitedByException,
        $returnValueOrThrown
    ): void {
        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        try {
            $callTracker->postHook($numberOfStackFramesToSkip + 1, $hasExitedByException, $returnValueOrThrown);
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable(
                $throwable,
                ClassNameUtil::fqToShort(InterceptedCallTrackerInterface::class)
                . ' postHook() has let a Throwable to escape'
            );
        }
    }
}
