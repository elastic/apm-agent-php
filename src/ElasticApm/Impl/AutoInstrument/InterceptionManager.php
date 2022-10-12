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

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
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

    /** @var int|null */
    private $interceptedCallInProgressRegistrationId;

    /** @var Registration|null */
    private $interceptedCallInProgressRegistration;

    /**
     * @var null|callable
     * @phpstan-var null|callable(int, bool, mixed): void
     */
    private $interceptedCallInProgressPreHookRetVal;

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
     * @param int     $interceptRegistrationId
     * @param ?object $thisObj
     * @param mixed[] $interceptedCallArgs
     *
     * @return bool
     */
    public function interceptedCallPreHook(
        int $interceptRegistrationId,
        ?object $thisObj,
        array $interceptedCallArgs
    ): bool {
        $localLogger = $this->logger->inherit()->addAllContext(
            [
                'interceptRegistrationId' => $interceptRegistrationId,
                'thisObj type' => DbgUtil::getType($thisObj),
                'interceptedCallArgs count' => count($interceptedCallArgs),
                'thisObj' => $this->logger->possiblySecuritySensitive($thisObj),
                'interceptedCallArgs' => $this->logger->possiblySecuritySensitive($interceptedCallArgs),
            ]
        );
        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        $interceptRegistration
            = ArrayUtil::getValueIfKeyExistsElse($interceptRegistrationId, $this->interceptedCallRegistrations, null);
        if ($interceptRegistration === null) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There is no registration with the given interceptRegistrationId');
            return false;
        }
        $localLogger->addContext('interceptRegistration', $interceptRegistration);

        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Calling preHook...');
        try {
            $preHookRetVal = ($interceptRegistration->preHook)($thisObj, $interceptedCallArgs);
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable(
                $throwable,
                'preHook has let a Throwable to escape'
            );
            return false;
        }

        $shouldCallPostHook = ($preHookRetVal !== null);
        if ($shouldCallPostHook) {
            $this->interceptedCallInProgressRegistrationId = $interceptRegistrationId;
            $this->interceptedCallInProgressRegistration = $interceptRegistration;
            $this->interceptedCallInProgressPreHookRetVal = $preHookRetVal;
        }

        ($loggerProxy = $localLogger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('preHook completed successfully', ['shouldCallPostHook' => $shouldCallPostHook]);
        return $shouldCallPostHook;
    }

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown                 Return value of the intercepted call
     *                                                             or the object thrown by the intercepted call
     */
    public function interceptedCallPostHook(
        int $numberOfStackFramesToSkip,
        bool $hasExitedByException,
        $returnValueOrThrown
    ): void {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered');

        if ($this->interceptedCallInProgressRegistrationId === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There is no intercepted call in progress');
            return;
        }
        assert($this->interceptedCallInProgressRegistration !== null);
        assert($this->interceptedCallInProgressPreHookRetVal !== null);

        $localLogger = $this->logger->inherit()->addAllContext(
            [
                'interceptRegistrationId' => $this->interceptedCallInProgressRegistrationId,
                'interceptRegistration'   => $this->interceptedCallInProgressRegistration,
            ]
        );

        try {
            ($this->interceptedCallInProgressPreHookRetVal)(
                $numberOfStackFramesToSkip + 1,
                $hasExitedByException,
                $returnValueOrThrown
            );
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable(
                $throwable,
                'postHook has let a Throwable to escape'
            );
        }

        $this->interceptedCallInProgressRegistrationId = null;
        $this->interceptedCallInProgressPreHookRetVal = null;
    }
}
