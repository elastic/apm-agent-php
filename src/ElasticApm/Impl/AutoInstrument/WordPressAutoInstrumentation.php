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

use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WordPressAutoInstrumentation extends AutoInstrumentationBase
{
    private const WORDPRESS_PLUGINS_SUBDIR_SUBPATH
        = DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;

    private const CALLBACK_WRAPPERS_MAX_COUNT = 1000;

    private const LOG_DBG_COUNT_STAT_EVERY = 1000;

    // private const GC_COLLECT_CYCLES_EVERY_WRAPPERS_COUNT = 1000;

    // private const UNIQUE_CALLBACKS_MAX_COUNT = 1000;
    //
    // private const CALLBACK_TYPE_STRING_KEY = 'string';
    // private const CALLBACK_TYPE_CLASS_STATIC_METHOD_KEY = 'class::staticMethod';
    // private const CALLBACK_TYPE_OBJECT_METHOD_KEY = 'object->method';
    // private const CALLBACK_TYPE_OTHER_KEY = 'OTHER';
    //
    // private const STAT_UNIQUE_COUNT_KEY = 'unique count';
    // private const STAT_TOTAL_COUNT_KEY = 'total count';
    // private const STAT_UNIQUE_REACHED_MAX_KEY = 'unique reached max';
    // private const STAT_REPEATED_MAX_COUNT_KEY = 'repeated max count';
    // private const STAT_REPEATED_MIN_COUNT_KEY = 'repeated min count';

    /** @var Logger */
    private $logger;

    /** @var bool */
    private $isInFailedMode = false;

    /** @var array<string, array<string, WordPressFilterCallbackWrapper>> */
    private $hookNameToOriginalCallbackIdToWrapper = [];

    /** @var int */
    private $callbackWrappersTotalCount = 0;

    /** @var bool */
    private $wpPluginDirectoryConstantsPreHookWasCalled = false;

    /** @var bool */
    private $doActionPluginsLoadedWasCalled = false;

    /** @var int */
    private $dbgStatCountTimesCallbackNotWrappedBecauseReachedMax = 0;

    /** @var int */
    private $dbgStatCountTimesCallbackWrapperNotFound = 0;

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
        return InstrumentationNames::WORDPRESS;
    }

    /** @inheritDoc */
    public function keywords(): array
    {
        return [];
    }

    public function register(RegistrationContextInterface $ctx): void
    {
    }

    private function switchToFailedMode(): void
    {
        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('Switching to FAILED mode');
        $this->isInFailedMode = true;
    }

    public static function findPluginSubDirNameInStackTraceFrameFilePath(string $filePath): ?string
    {
        $pluginsSubDirPos = strpos($filePath, self::WORDPRESS_PLUGINS_SUBDIR_SUBPATH);
        if ($pluginsSubDirPos === false) {
            return null;
        }
        $posAfterPluginsSubDir = $pluginsSubDirPos + strlen(self::WORDPRESS_PLUGINS_SUBDIR_SUBPATH);
        $dirSeparatorAfterPluginPos = strpos($filePath, DIRECTORY_SEPARATOR, $posAfterPluginsSubDir);
        if ($dirSeparatorAfterPluginPos === false) {
            return null;
        }
        return substr($filePath, $posAfterPluginsSubDir, $dirSeparatorAfterPluginPos - $posAfterPluginsSubDir);
    }

    private function findPluginName(): ?string
    {
        static $callsCount = 0;
        ++$callsCount;

        /** @var array<string, array<string, mixed>> $positiveCache */
        static $positiveCache = [];
        $positiveCacheCountOnEntry = count($positiveCache);
        $addToPositiveCache = function (string $filePath, string $pluginName) use (&$positiveCache): void {
            $positiveCache[$filePath] = [];
            $cache =& $positiveCache[$filePath];
            $cache['plugin name'] = $pluginName;
            $cache['fetch count'] = 0;
        };

        /** @var array<string, array<string, mixed>> $negativeCache */
        static $negativeCache = [];
        $negativeCacheCountOnEntry = count($negativeCache);
        $addToNegativeCache = function (string $filePath, string $reason) use (&$negativeCache): void {
            $negativeCache[$filePath] = [];
            $cache =& $negativeCache[$filePath];
            $cache['reason'] = $reason;
            $cache['fetch count'] = 0;
        };
        $tryToFetchFromCache = function (
            string $filePath,
            ?string &$pluginName
        ) use (
            &$positiveCache,
            &$negativeCache
        ): bool {
            if (array_key_exists($filePath, $positiveCache)) {
                $cache =& $positiveCache[$filePath];
                $isPositive = true;
            } elseif (array_key_exists($filePath, $negativeCache)) {
                $cache =& $negativeCache[$filePath];
                $isPositive = false;
            } else {
                return false;
            }
            ++$cache['fetch count'];
            if ($isPositive) {
                $pluginName = $cache['plugin name'];
                return true;
            }
            $pluginName = null;
            return true;
        };

        $stackTrace = StackTraceUtil::captureInClassicFormatExcludeElasticApm(
            null /* <- loggerFactory */,
            0 /* <- offset */,
            0 /* <- options */
        );

        $retVal = null;
        // $forceLogResultAndStats = false;
        // $expectedRetVal = null;
        foreach ($stackTrace as $stackTraceFrame) {
            $filePath = $stackTraceFrame->file;
            if ($filePath === null) {
                continue;
            }

            if ($tryToFetchFromCache($filePath, /* ref */ $pluginName)) {
                if ($pluginName === null) {
                    continue;
                }
                $retVal = $pluginName;
                break;
            }

            $pluginName = self::findPluginSubDirNameInStackTraceFrameFilePath($filePath);
            if ($pluginName !== null) {
                $retVal = $pluginName;
                $addToPositiveCache($filePath, $pluginName);
                break;
            }

            $addToNegativeCache($filePath, '');
            /** @var ?string $pluginName */
            $pluginName = null;
            $fetchRetVal = $tryToFetchFromCache($filePath, /* ref */ $pluginName);
            assert($fetchRetVal === true, LoggableToString::convert(['fetchRetVal' => $fetchRetVal]));
            assert($pluginName === null, LoggableToString::convert(['pluginName' => $pluginName]));
        }

        // if ($expectedRetVal !== null) {
        //     assert(
        //         $retVal === $expectedRetVal,
        //         LoggableToString::convert(['expectedRetVal' => $expectedRetVal, 'retVal' => $retVal])
        //     );
        // }

        // if ($forceLogResultAndStats) {
        //     ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
        //     && $loggerProxy->log('', ['retVal' => $retVal]);
        // }

        // if ($forceLogResultAndStats || (($callsCount % 1000) === 1)) {
        if (($callsCount % 1000) === 1) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('', ['callsCount' => $callsCount, 'positiveCache' => $positiveCache]);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('', ['callsCount' => $callsCount, 'negativeCache' => $negativeCache]);
        } else {
            if ($positiveCacheCountOnEntry === 0 && count($positiveCache) === 1) { // @phpstan-ignore-line
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('', ['count' => count($positiveCache), 'positiveCache' => $positiveCache]);
            }
            if ($negativeCacheCountOnEntry === 0 && count($negativeCache) === 1) { // @phpstan-ignore-line
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('', ['count' => count($negativeCache), 'negativeCache' => $negativeCache]);
            }
        }

        return $retVal;
    }

    /**
     * @param string  $funcName
     * @param mixed[] $funcArgs
     */
    public function onFunctionPreHook(string $funcName, array $funcArgs): void
    {
        if ($this->isInFailedMode) {
            return;
        }

        // We should all the function instrumented by wordPressInstrumentationOnModuleInit
        // in src/ext/WordPress_instrumentation.c
        switch ($funcName) {
            case 'wp_plugin_directory_constants':
                $this->preHookWpPluginDirectoryConstants();
                return;
            case 'do_action':
                $this->preHookDoAction($funcArgs);
                return;
            case 'add_filter':
            case 'add_action':
                $this->preHookAddFilter($funcArgs);
                return;
            case 'remove_filter':
            case 'remove_action':
                $this->preHookRemoveFilter($funcArgs);
                return;
            default:
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Unknown function', ['funcName' => $funcName]);
                $this->switchToFailedMode();
        }
    }

    private function preHookWpPluginDirectoryConstants(): void
    {
        if (!$this->wpPluginDirectoryConstantsPreHookWasCalled) {
            $this->wpPluginDirectoryConstantsPreHookWasCalled = true;
        }
    }

    /**
     * @param mixed[] $funcArgs
     */
    private function preHookDoAction(array $funcArgs): void
    {
        if ($this->doActionPluginsLoadedWasCalled) {
            return;
        }

        if (count($funcArgs) !== 1) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected number of passed parameters to be 1',
                ['count($funcArgs)' => count($funcArgs), 'funcArgs' => $funcArgs]
            );
            $this->switchToFailedMode();
            return;
        }

        $hookName = $funcArgs[0];
        if (!is_string($hookName)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected 1st argument (hookName) to be a string',
                ['hookName type' => DbgUtil::getType($hookName), 'hookName' => $hookName]
            );
            $this->switchToFailedMode();
            return;
        }

        if ($hookName === 'plugins_loaded') {
            $this->doActionPluginsLoadedWasCalled = true;
        }
    }

    /**
     * @param mixed $callback
     *
     * @return string
     */
    private function buildOriginalCallbackId($callback): ?string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_object($callback)) {
            return spl_object_hash($callback);
        }

        if (!is_array($callback)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected callback to be an array but it is not',
                ['callback type' => DbgUtil::getType($callback), 'callback' => $callback]
            );
            return null;
        }

        if (count($callback) != 2) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected callback to be an array of two element but it is not',
                ['count($callback)' => count($callback), 'callback' => $callback]
            );
            return null;
        }

        $callbackSecondElement = $callback[1];
        if (!is_string($callbackSecondElement)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected callback array second element to be a string but it is not',
                [
                    'callbackSecondElement type' => DbgUtil::getType($callbackSecondElement),
                    'callbackSecondElement' => $callbackSecondElement,
                    'callback' => $callback,
                ]
            );
            return null;
        }

        $callbackFirstElement = $callback[0];
        if (is_object($callbackFirstElement)) {
            return spl_object_hash($callbackFirstElement) . '->' . $callbackSecondElement;
        }

        if (!is_string($callbackFirstElement)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected callback array first element to be a string but it is not',
                [
                    'callbackFirstElement type' => DbgUtil::getType($callbackFirstElement),
                    'callbackFirstElement' => $callbackFirstElement,
                    'callback' => $callback,
                ]
            );
            return null;
        }

        return $callbackFirstElement . '::' . $callbackSecondElement;
    }

    /**
     * @param int                  $dbgCountStat
     * @param string               $dbgCountStatDesc
     * @param string               $logMsg
     * @param array<string, mixed> $ctx
     * @param int                  $firstOccurrenceLogLevel
     * @param int                  $followingOccurrencesLogLevel
     *
     * @return void
     */
    private function incrementDbgCountStat(
        int &$dbgCountStat,
        string $dbgCountStatDesc,
        string $logMsg,
        array $ctx = [],
        int $firstOccurrenceLogLevel = LogLevel::WARNING,
        int $followingOccurrencesLogLevel = LogLevel::INFO
    ): void {
        ++$dbgCountStat;
        if (($dbgCountStat % self::LOG_DBG_COUNT_STAT_EVERY) === 1) {
            $logLevel = $dbgCountStat === 1 ? $firstOccurrenceLogLevel : $followingOccurrencesLogLevel;
            ($loggerProxy = $this->logger->ifLevelEnabled($logLevel, __LINE__, __FUNCTION__))
            && $loggerProxy->log($logMsg, array_merge($ctx, [$dbgCountStatDesc => $dbgCountStat]));
        }
    }

    /**
     * @param mixed[] $funcArgs
     *
     * @return void
     */
    private function preHookAddFilter(array $funcArgs): void
    {
        if (!$this->wpPluginDirectoryConstantsPreHookWasCalled) {
            return;
        }

        if ($this->callbackWrappersTotalCount === self::CALLBACK_WRAPPERS_MAX_COUNT) {
            $this->incrementDbgCountStat(
                $this->dbgStatCountTimesCallbackNotWrappedBecauseReachedMax /* <- ref */,
                'countTimesCallbackNotWrappedBecauseReachedMax' /* <- dbgCountStatDesc */,
                'Reached max number of callback wrappers' /* <- logMsg */
            );
            return;
        }

        $pluginName = $this->findPluginName();
        if ($pluginName === null) {
            $this->dbgAddFilterStats(/* isAdd */ true, $pluginName);
            return;
        }
        $this->dbgAddFilterStats(/* isAdd */ true, $pluginName);

        if (!$this->wrapAndTrackFilterCallack($funcArgs, $pluginName)) {
            $this->switchToFailedMode();
        }
    }

    /**
     * @param mixed[] $funcArgs
     *
     * @return void
     */
    private function preHookRemoveFilter(array $funcArgs): void
    {
        $this->dbgAddFilterStats(/* isAdd */ false, /* pluginName */ null);

        if (!$this->preHookRemoveFilterImpl($funcArgs)) {
            $this->switchToFailedMode();
        }
    }

    /**
     * @param mixed[] $funcArgs
     *
     * @return bool
     */
    private function verifyAddRemoveFilterArgs(array $funcArgs): bool
    {
        if (count($funcArgs) !== 2) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected number of passed parameters to be 2',
                ['count($funcArgs)' => count($funcArgs), 'funcArgs' => $funcArgs]
            );
            return false;
        }

        $hookNameArg = $funcArgs[0];
        if (!is_string($hookNameArg)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected 1st argument (hookName) to be a string',
                ['hookNameArg type' => DbgUtil::getType($hookNameArg), 'hookNameArg' => $hookNameArg]
            );
            return false;
        }

        $callbackArg = $funcArgs[1];
        if (!is_callable($callbackArg, /* syntax_only: */ true)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Expected 2nd argument (callback) to be a callable',
                ['callbackArg type' => DbgUtil::getType($callbackArg), 'callback' => $callbackArg]
            );
            return false;
        }

        return true;
    }

    /**
     * @param mixed[] $funcArgs
     * @param string  $pluginName
     *
     * @return bool
     */
    private function wrapAndTrackFilterCallack(array $funcArgs, string $pluginName): bool
    {
        if (!$this->verifyAddRemoveFilterArgs($funcArgs)) {
            return false;
        }
        /** @var string $hookName */
        $hookName = $funcArgs[0];
        $callback =& $funcArgs[1];

        $originalCallbackId = $this->buildOriginalCallbackId($callback);
        if ($originalCallbackId === null) {
            return false;
        }

        $originalCallbackToWrapper
            = ArrayUtil::getOrAdd($hookName, /* defaultValue */ [], $this->hookNameToOriginalCallbackIdToWrapper);

        $wrapper = new WordPressFilterCallbackWrapper($hookName, $callback, $pluginName);
        $originalCallbackToWrapper[$originalCallbackId] = $wrapper;
        ++$this->callbackWrappersTotalCount;

        $callback = $wrapper;

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Added callback wrapper',
            ['hookName' => $hookName, 'originalCallbackId' => $originalCallbackId]
        );
        return true;
    }

    /**
     * @param mixed[] $funcArgs
     *
     * @return bool
     */
    private function preHookRemoveFilterImpl(array $funcArgs): bool
    {
        if (!$this->verifyAddRemoveFilterArgs($funcArgs)) {
            return false;
        }
        /** @var string $hookName */
        $hookName = $funcArgs[0];
        $callback =& $funcArgs[1];

        $wrapper = null;
        $originalCallbackId = null;
        /** @var ?array<string, WordPressFilterCallbackWrapper> $originalCallbackToWrapper */
        $originalCallbackToWrapper = null;
        if (array_key_exists($hookName, $this->hookNameToOriginalCallbackIdToWrapper)) {
            $originalCallbackToWrapper =& $this->hookNameToOriginalCallbackIdToWrapper[$hookName];
            $originalCallbackId = $this->buildOriginalCallbackId($callback);
            if ($originalCallbackId === null) {
                return false;
            }
            if (array_key_exists($originalCallbackId, $originalCallbackToWrapper)) {
                $wrapper = $originalCallbackToWrapper[$originalCallbackId];
            }
        }

        if ($wrapper === null) {
            $this->incrementDbgCountStat(
                $this->dbgStatCountTimesCallbackWrapperNotFound /* <- ref */,
                'countTimesCallbackWrapperNotFound' /* <- dbgCountStatDesc */,
                'Not found wrapper for callback' /* <- logMsg */,
                ['hookName' => $hookName, 'originalCallbackId' => $originalCallbackId],
                LogLevel::TRACE /* <- firstOccurrenceLogLevel */,
                LogLevel::TRACE  /* <- followingOccurrencesLogLevel */
            );
            return true;
        }
        /** @var array<string, WordPressFilterCallbackWrapper> $originalCallbackToWrapper */

        unset($originalCallbackToWrapper[$originalCallbackId]);
        --$this->callbackWrappersTotalCount;

        $callback = $wrapper;

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Removed callback wrapper',
            ['hookName' => $hookName, 'originalCallbackId' => $originalCallbackId]
        );
        return true;
    }

    // /**
    //  * @param mixed $hookName
    //  * @param mixed &$callback
    //  *
    //  * @return void
    //  */
    // public function preprocessAddFilterParameters($hookName, /* in,out */ &$callback): void
    // {
    //     // static $callsCount = 0;
    //     // ++$callsCount;
    //     // if (($callsCount % 1000) === 1) {
    //     //     ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
    //     //     && $loggerProxy->log('', ['callsCount' => $callsCount]);
    //     // }
    //
    //     if (!is_string($hookName)) {
    //         ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
    //         && $loggerProxy->log(
    //             'Expected 1st argument (hookName) to be a string',
    //             ['hookName type' => DbgUtil::getType($hookName), 'hookName' => $hookName]
    //         );
    //         return;
    //     }
    //     /** @var string $callback */
    //
    //     if (!is_callable($callback, /* syntax_only: */ true)) {
    //         ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
    //         && $loggerProxy->log(
    //             'Expected 2nd argument (callback) to be a callable',
    //             ['callback type' => DbgUtil::getType($callback), 'callback' => $callback]
    //         );
    //         return;
    //     }
    //     /** @var callable|string $callback */
    //
    //     $pluginName = $this->findPluginName();
    //     if ($pluginName === null) {
    //         return;
    //     }
    //
    //     $this->dumpFirstStackTraceForEachPlugin($pluginName);
    //
    //     // ++$this->callsCount;
    //     // $this->addStats($callback);
    //     //
    //     // if (($this->callsCount % self::LOG_STATS_EVERY_CALL_COUNT) === 1) {
    //     //     ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
    //     //     && $loggerProxy->log(
    //     //         '',
    //     //         [
    //     //             'callsCount' => $this->callsCount,
    //     //             'callbackWrappersCount' => $this->callbackWrappersCount,
    //     //             'callbackStatsByType' => $this->callbackStatsByType,
    //     //         ]
    //     //     );
    //     // }
    //
    //     if ($this->callbackWrappersCount >= self::CALLBACK_WRAPPERS_MAX_COUNT) {
    //         return;
    //     }
    //     ++$this->callbackWrappersCount;
    //
    //     // if (($this->callbackWrappersCount % self::GC_COLLECT_CYCLES_EVERY_WRAPPERS_COUNT) === 0) {
    //     //     ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
    //     //     && $loggerProxy->log('Calling gc_collect_cycles()...');
    //     //
    //     //     $collectedCyclesCount = gc_collect_cycles();
    //     //
    //     //     ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
    //     //     && $loggerProxy->log('Called gc_collect_cycles()', ['collectedCyclesCount' => $collectedCyclesCount]);
    //     // }
    //
    //     if ($this->callbackWrappersCount === self::CALLBACK_WRAPPERS_MAX_COUNT) {
    //         ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
    //         && $loggerProxy->log('Callback wrappers count reached max (' . self::CALLBACK_WRAPPERS_MAX_COUNT . ')');
    //     }
    //
    //     // $wrappedCallback = function () use ($hookName, $callback, $isWordPressCore) {
    //     //     $callbackAsString = self::callableToString($callback);
    //     //
    //     //     $name = $hookName;
    //     //     $type = $isWordPressCore ? 'wordpress' : 'wordpress_plugin';
    //     //     $subtype = $isWordPressCore ? 'wordpress_core' : 'gp-premium';
    //     //     $action = $callbackAsString;
    //     //
    //     //     // $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);
    //     //     // $span->context()->setLabel($key, $value);
    //     //
    //     //     // call_user_func_array($callback, func_get_args());
    //     //     $callback(...func_get_args());
    //     //
    //     //     // $span->end();
    //     // };
    //
    //     $callback = new WordPressFilterCallbackWrapper($hookName, $callback);
    // }

    // private function addStatsForType(string $callbackTypeKey, ?string $callbackAsString): void
    // {
    //     $stats =& ArrayUtil::getOrAdd($callbackTypeKey, [], /* ref */ $this->callbackStatsByType);
    //     $totalCount =& ArrayUtil::getOrAdd(self::STAT_TOTAL_COUNT_KEY, 0, /* ref */ $stats);
    //     ++$totalCount;
    //
    //     if ($callbackAsString === null) {
    //         return;
    //     }
    //
    //     $shouldUpdateMinMax = true;
    //     $uniqueCallbacks =& ArrayUtil::getOrAdd($callbackTypeKey, [], /* ref */ $this->uniqueCallbacksByType);
    //     if (array_key_exists($callbackAsString, $uniqueCallbacks)) {
    //         ++$uniqueCallbacks[$callbackAsString];
    //     } else {
    //         if (count($uniqueCallbacks) < self::UNIQUE_CALLBACKS_MAX_COUNT) {
    //             $uniqueCallbacks[$callbackAsString] = 1;
    //             $stats[self::STAT_UNIQUE_COUNT_KEY] = count($uniqueCallbacks);
    //         } else {
    //             $stats[self::STAT_UNIQUE_REACHED_MAX_KEY] = true;
    //             $shouldUpdateMinMax = false;
    //         }
    //     }
    //
    //     if ($shouldUpdateMinMax) {
    //         /** @var ?int $minRepeatCount */
    //         $minRepeatCount = null;
    //         /** @var ?int $minRepeatCount */
    //         $maxRepeatCount = null;
    //         foreach ($uniqueCallbacks as $repeatCount) {
    //             $minRepeatCount = min($repeatCount, $minRepeatCount ?? $repeatCount);
    //             $maxRepeatCount = max($repeatCount, $maxRepeatCount ?? $repeatCount);
    //         }
    //         if ($minRepeatCount !== null) {
    //             $stats[self::STAT_REPEATED_MIN_COUNT_KEY] = $minRepeatCount;
    //         }
    //         if ($maxRepeatCount !== null) {
    //             $stats[self::STAT_REPEATED_MAX_COUNT_KEY] = $maxRepeatCount;
    //         }
    //     }
    // }
    //
    // /**
    //  * @param callable|string $callback
    //  *
    //  * @return void
    //  */
    // private function addStats($callback): void
    // {
    //     if (is_string($callback)) {
    //         $this->addStatsForType(self::CALLBACK_TYPE_STRING_KEY, $callback);
    //         return;
    //     }
    //
    //     if (!is_array($callback)) {
    //         $this->addStatsForType(self::CALLBACK_TYPE_OTHER_KEY . ' !is_array($callback)', null);
    //         return;
    //     }
    //
    //     if (count($callback) !== 2) {
    //         $this->addStatsForType(self::CALLBACK_TYPE_OTHER_KEY . ' count($callback) !== 2', null);
    //         return;
    //     }
    //
    //     if (!is_string($callback[1])) {
    //         $this->addStatsForType(self::CALLBACK_TYPE_OTHER_KEY . ' !is_string($callback[1])', null);
    //         return;
    //     }
    //
    //     if (is_object($callback[0])) {
    //         $this->addStatsForType(self::CALLBACK_TYPE_OBJECT_METHOD_KEY, null);
    //         return;
    //     }
    //
    //     if (!is_string($callback[0])) {
    //         $this->addStatsForType(self::CALLBACK_TYPE_OTHER_KEY . ' !is_string($callback[0])', null);
    //         return;
    //     }
    //
    //     $this->addStatsForType(self::CALLBACK_TYPE_CLASS_STATIC_METHOD_KEY, $callback[0] . '::' . $callback[1]);
    // }

    // private function dumpFirstStackTraceForEachPlugin(string $pluginName): void
    // {
    //     /** @var array<string, true> $encounteredPlugins */
    //     static $encounteredPlugins = [];
    //     if (array_key_exists($pluginName, $encounteredPlugins)) {
    //         return;
    //     }
    //     $encounteredPlugins[$pluginName] = true;
    //
    //     $stackTrace = StackTraceUtil::captureInClassicFormatExcludeElasticApm(
    //         null /* <- loggerFactory */,
    //         0 /* <- offset */,
    //         0 /* <- options */
    //     );
    //     ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
    //     && $loggerProxy->log('', ['pluginName' => $pluginName, 'stackTrace' => $stackTrace]);
    // }

    private function dbgAddFilterStats(bool $isAdd, ?string $pluginName): void
    {
        /** @var array<string, int> $callTypeToStats */
        static $callTypeToStats = [];
        $callType = $isAdd ? 'add' : 'remove';
        $statsForCurrentCallType =& ArrayUtil::getOrAdd($callType, [], $callTypeToStats);
        $totalCurrentCallTypeCount =& ArrayUtil::getOrAdd('total calls count', 0, $statsForCurrentCallType);
        ++$totalCurrentCallTypeCount;

        $eventType =
            ($this->wpPluginDirectoryConstantsPreHookWasCalled ? 'after' : 'before') . ' wp_plugin_directory_constants';
        $eventType .= ' | ';
        $eventType .=
            ($this->doActionPluginsLoadedWasCalled ? 'after' : 'before') . ' do_action(\'plugins_loaded\')';
        $eventType .= ' | ';
        $eventType .= '$pluginName: ' . ($pluginName === null ? 'null' : $pluginName);

        $eventCount =& ArrayUtil::getOrAdd($eventType, 0, $statsForCurrentCallType);
        ++$eventCount;

        if (($totalCurrentCallTypeCount % 100) === 1) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                $callType . ': ' . $totalCurrentCallTypeCount,
                ['callTypeToStats' => $callTypeToStats]
            );
        }
    }

    /** @inheritDoc */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(parent::propertiesExcludedFromLog(), ['hookNameToOriginalCallbackIdToWrapper']);
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        parent::toLogLoggableTraitImpl(
            $stream,
            /* customPropValues */
            [
                'hookNameToOriginalCallbackIdToWrapper top level count (number of hookName-s)'
                => count($this->hookNameToOriginalCallbackIdToWrapper)
            ]
        );
    }
}
