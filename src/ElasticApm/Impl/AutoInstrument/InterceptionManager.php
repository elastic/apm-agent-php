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
use Elastic\Apm\Impl\Util\ClassNameUtil;
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

    /** @var BuiltinPlugin */
    private $builtinPlugin;

    /** @var int|null */
    private $interceptedCallInProgressRegistrationId = null;

    /** @var Registration|null */
    private $interceptedCallInProgressRegistration = null;

    /**
     * @var null|callable(int, bool, mixed): void
     */
    private $interceptedCallInProgressPreHookRetVal = null;

    public function __construct(Tracer $tracer)
    {
        $this->logger = $tracer->loggerFactory()
                               ->loggerForClass(LogCategory::INTERCEPTION, __NAMESPACE__, __CLASS__, __FILE__);

        $this->loadPlugins($tracer);
    }

    private function loadPlugins(Tracer $tracer): void
    {
        $this->builtinPlugin = new BuiltinPlugin($tracer);
        $registerCtx = new RegistrationContext();
        $registerCtx->dbgCurrentPluginIndex = 0;
        $registerCtx->dbgCurrentPluginDesc = $this->builtinPlugin->getDescription();
        $this->builtinPlugin->register($registerCtx);
        $this->interceptedCallRegistrations = $registerCtx->interceptedCallRegistrations;
    }

    /**
     * @param int     $interceptRegistrationId
     * @param ?object $thisObj
     * @param mixed[] $interceptedCallArgs
     *
     * @return bool
     */
    public function internalFuncCallPreHook(
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
        $loggerProxyTrace = $localLogger->ifTraceLevelEnabledNoLine(__FUNCTION__);

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Entered');

        $interceptRegistration
            = ArrayUtil::getValueIfKeyExistsElse($interceptRegistrationId, $this->interceptedCallRegistrations, null);
        if ($interceptRegistration === null) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There is no registration with the given interceptRegistrationId');
            return false;
        }
        $localLogger->addContext('interceptRegistration', $interceptRegistration);

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Calling preHook...');
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

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'preHook completed successfully', ['shouldCallPostHook' => $shouldCallPostHook]);
        return $shouldCallPostHook;
    }

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown                 Return value of the intercepted call
     *                                                             or the object thrown by the intercepted call
     */
    public function internalFuncCallPostHook(
        int $numberOfStackFramesToSkip,
        bool $hasExitedByException,
        $returnValueOrThrown
    ): void {
        $localLogger = $this->logger->inherit()->addAllContext(
            [
                'interceptRegistrationId' => $this->interceptedCallInProgressRegistrationId,
                'interceptRegistration'   => $this->interceptedCallInProgressRegistration,
            ]
        );
        $loggerProxyTrace = $localLogger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Entered');

        if ($this->interceptedCallInProgressRegistrationId === null) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There is no intercepted call in progress');
            return;
        }
        assert($this->interceptedCallInProgressRegistration !== null);
        assert($this->interceptedCallInProgressPreHookRetVal !== null);

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Calling postHook...');
        try {
            ($this->interceptedCallInProgressPreHookRetVal)(
                $numberOfStackFramesToSkip + 1,
                $hasExitedByException,
                $returnValueOrThrown
            );
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'postHook completed without throwing');
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($throwable, 'postHook has thrown');
        }

        $this->interceptedCallInProgressRegistrationId = null;
        $this->interceptedCallInProgressPreHookRetVal = null;
    }

    public function astInstrumentationDirectCall(string $method): void
    {
        $localLogger = $this->logger->inherit()->addAllContext(['method' => $method]);

        $loggerProxyTrace = $localLogger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Entered');

        $wordPressAutoInstrumIfEnabled = $this->builtinPlugin->getWordPressAutoInstrumentationIfEnabled();
        if ($wordPressAutoInstrumIfEnabled === null) {
            static $loggedOnce = false;
            if (!$loggedOnce) {
                $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'WordPress instrumentation is DISABLED');
                $loggedOnce = true;
            }
            return;
        }

        static $dbgImplFuncDesc = null;
        if ($dbgImplFuncDesc === null) {
            $dbgImplFuncDesc = ClassNameUtil::fqToShort(WordPressAutoInstrumentation::class) . '->directCall';
        }
        /** @var string $dbgImplFuncDesc */
        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Calling ' . $dbgImplFuncDesc . '...');
        try {
            $wordPressAutoInstrumIfEnabled->directCall($method);
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, $dbgImplFuncDesc . ' completed without throwing');
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->logThrowable($throwable, $dbgImplFuncDesc . ' has thrown');
        }
    }

    /**
     * @param ?string $instrumentedClassFullName
     * @param string  $instrumentedFunction
     * @param mixed[] $capturedArgs
     *
     * @return null|callable(?Throwable $thrown, mixed $returnValue): void
     */
    public function astInstrumentationPreHook(?string $instrumentedClassFullName, string $instrumentedFunction, array $capturedArgs): ?callable
    {
        $localLogger = $this->logger->inherit()->addAllContext(['instrumentedClassFullName' => $instrumentedClassFullName]);

        $loggerProxyTrace = $localLogger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Entered');

        $wordPressAutoInstrumIfEnabled = $this->builtinPlugin->getWordPressAutoInstrumentationIfEnabled();
        if ($wordPressAutoInstrumIfEnabled === null) {
            static $loggedOnce = false;
            if (!$loggedOnce) {
                $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'WordPress instrumentation is DISABLED');
                $loggedOnce = true;
            }
            return null;
        }

        static $dbgImplFuncDesc = null;
        if ($dbgImplFuncDesc === null) {
            $dbgImplFuncDesc = ClassNameUtil::fqToShort(WordPressAutoInstrumentation::class) . '->preHook';
        }
        /** @var string $dbgImplFuncDesc */
        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Calling ' . $dbgImplFuncDesc . '...');
        try {
            $retVal = $wordPressAutoInstrumIfEnabled->preHook($instrumentedClassFullName, $instrumentedFunction, $capturedArgs);
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, $dbgImplFuncDesc . ' completed without throwing', ['retVal == null' => ($retVal == null)]);
            return $retVal;
        } catch (Throwable $throwable) {
            ($loggerProxy = $localLogger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($throwable, $dbgImplFuncDesc . ' has thrown');
            return null;
        }
    }
}
