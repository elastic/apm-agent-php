<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection PhpUndefinedConstantInspection */

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
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\Util\AutoInstrumentationUtil;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WordPressAutoInstrumentation extends AutoInstrumentationBase
{
    private const SERVICE_FRAMEWORK_NAME = 'WordPress';

    public const SPAN_NAME_PART_FOR_CORE = 'WordPress core';

    public const THEME_KEYWORD = 'wordpress_theme';

    public const CALLBACK_GROUP_KIND_CORE = 'wordpress_core';
    public const CALLBACK_GROUP_KIND_PLUGIN = 'wordpress_plugin';
    public const CALLBACK_GROUP_KIND_THEME = self::THEME_KEYWORD;

    public const LABEL_KEY_FOR_WORDPRESS_THEME = self::THEME_KEYWORD;

    private const WORDPRESS_PLUGINS_SUBDIR_SUBPATH = '/wp-content/plugins/';
    private const WORDPRESS_MU_PLUGINS_SUBDIR_SUBPATH = '/wp-content/mu-plugins/';
    private const WORDPRESS_THEMES_SUBDIR_SUBPATH = '/wp-content/themes/';

    private const CALLBACK_SUBDIR_SUBPATH_TO_GROUP_KIND = [
        self::WORDPRESS_PLUGINS_SUBDIR_SUBPATH => self::CALLBACK_GROUP_KIND_PLUGIN,
        self::WORDPRESS_MU_PLUGINS_SUBDIR_SUBPATH => self::CALLBACK_GROUP_KIND_PLUGIN,
        self::WORDPRESS_THEMES_SUBDIR_SUBPATH => self::CALLBACK_GROUP_KIND_THEME,
    ];

    /**
     * \ELASTIC_APM_* constants are provided by the elastic_apm extension
     *
     * @phpstan-ignore-next-line
     */
    private const DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS = \ELASTIC_APM_WORDPRESS_DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS;

    /** @var Logger */
    private $logger;

    /** @var AutoInstrumentationUtil */
    private $util;

    /** @var bool */
    private $isInFailedMode = false;

    /** @var bool */
    private $isReadyToWrapFilterCallbacks = false;

    /** @var bool */
    private $isServiceFrameworkSet = false;

    /** @var ?NameVersionData */
    private $serviceFramework = null;

    public function __construct(Tracer $tracer)
    {
        parent::__construct($tracer);

        $this->logger = $tracer->loggerFactory()->loggerForClass(LogCategory::AUTO_INSTRUMENTATION, __NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);

        $this->util = new AutoInstrumentationUtil($tracer->loggerFactory());
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

    /** @inheritDoc */
    public function register(RegistrationContextInterface $ctx): void
    {
        $this->tracer->getMetadataDiscoverer()->addServiceFrameworkDiscoverer(
            ClassNameUtil::fqToShort(__CLASS__) /* <- dbgDiscovererName */,
            function (): ?NameVersionData {
                return $this->discoverServiceFramework();
            }
        );
    }

    /** @inheritDoc */
    public function requiresUserlandCodeInstrumentation(): bool
    {
        return true;
    }

    private function switchToFailedMode(): void
    {
        if ($this->isInFailedMode) {
            return;
        }

        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log('Switching to FAILED mode');
        $this->isInFailedMode = true;
    }

    private function setReadyToWrapFilterCallbacks(): void
    {
        $this->isReadyToWrapFilterCallbacks = true;
    }

    /**
     * @param string        $filePath
     * @param LoggerFactory $loggerFactory
     * @param ?string       $groupKind
     * @param ?string       $groupName
     *
     * @return void
     *
     * @param-out string  $groupKind
     * @param-out ?string $groupName
     */
    public static function findAddonInfoFromFilePath(string $filePath, LoggerFactory $loggerFactory, /* out */ ?string &$groupKind, /* out */ ?string &$groupName): void
    {
        $logger = null;
        $loggerProxyTrace = null;
        if ($loggerFactory->isEnabledForLevel(Level::TRACE)) {
            $logger = $loggerFactory->loggerForClass(LogCategory::AUTO_INSTRUMENTATION, __NAMESPACE__, __CLASS__, __FILE__)->addContext('filePath', $filePath);
            $loggerProxyTrace = $logger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        }

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Entered');

        $adaptedFilePath = str_replace('\\', '/', $filePath);
        $logger && $logger->addContext('adaptedFilePath', $adaptedFilePath);

        $groupKind = self::CALLBACK_GROUP_KIND_CORE;
        $groupName = null;

        $currentGroupKind = null;
        /** @var ?int $posAfterAddonsSubDir */
        $posAfterAddonsSubDir = null;
        foreach (self::CALLBACK_SUBDIR_SUBPATH_TO_GROUP_KIND as $subDirSubPath => $currentGroupKind) {
            $pluginsSubDirPos = strpos($adaptedFilePath, $subDirSubPath);
            if ($pluginsSubDirPos !== false) {
                $posAfterAddonsSubDir = $pluginsSubDirPos + strlen($subDirSubPath);
                break;
            }
        }
        if ($posAfterAddonsSubDir === null) {
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Not found any of the known sub-paths in the given path');
            return;
        }
        $logger && $logger->addContext('posAfterAddonsSubDir', $posAfterAddonsSubDir);

        $dirSeparatorAfterPluginPos = strpos($adaptedFilePath, '/', $posAfterAddonsSubDir);
        if ($dirSeparatorAfterPluginPos !== false && $dirSeparatorAfterPluginPos > $posAfterAddonsSubDir) {
            $groupKind = $currentGroupKind;
            $groupName = substr($adaptedFilePath, $posAfterAddonsSubDir, $dirSeparatorAfterPluginPos - $posAfterAddonsSubDir);
            return;
        }

        $fileExtAfterPluginPos = strpos($adaptedFilePath, '.php', $posAfterAddonsSubDir);
        if ($fileExtAfterPluginPos !== false && $fileExtAfterPluginPos > $posAfterAddonsSubDir) {
            $groupKind = $currentGroupKind;
            $groupName = substr($adaptedFilePath, $posAfterAddonsSubDir, $fileExtAfterPluginPos - $posAfterAddonsSubDir);
            return;
        }

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Found one of the known sub-paths but the suffix is not as expected');
    }

    /**
     * @param Closure|string $callback
     * @param Logger         $logger
     *
     * @return ?string
     *
     * @throws ReflectionException
     */
    private static function getCallbackSourceFilePathImplForFunc($callback, Logger $logger): ?string
    {
        $reflectFunc = new ReflectionFunction($callback);
        if (($srcFilePath = $reflectFunc->getFileName()) === false) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Failed to get file name from ReflectionFunction of captured callback');
            return null;
        }
        return $srcFilePath;
    }

    /**
     * @param object|string $classInstanceOrName
     * @param Logger         $logger
     *
     * @return ?string
     *
     * @throws ReflectionException
     */
    private static function getCallbackSourceFilePathImplForClass($classInstanceOrName, Logger $logger): ?string
    {
        /** @var object|class-string $classInstanceOrName */
        $reflectClass = new ReflectionClass($classInstanceOrName);
        if (($srcFilePath = $reflectClass->getFileName()) === false) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to get file name from ReflectionClass of captured callback', ['classInstanceOrName' => $classInstanceOrName]);
            return null;
        }
        return $srcFilePath;
    }

    /**
     * @param mixed  $callback
     * @param Logger $logger
     *
     * @return ?string
     *
     * @throws ReflectionException
     */
    private static function getCallbackSourceFilePathImpl($callback, Logger $logger): ?string
    {
        // If callback is a Closure or string but not 'Class::method'
        if ($callback instanceof Closure) {
            return self::getCallbackSourceFilePathImplForFunc($callback, $logger);
        }

        // If callback is a string but not 'Class::method'
        if (is_string($callback)) {
            if (($afterClassNamePos = strpos($callback, '::')) === false) {
                return self::getCallbackSourceFilePathImplForFunc($callback, $logger);
            }
            $className = substr($callback, /* offset */ 0, /* length */ $afterClassNamePos);
            return self::getCallbackSourceFilePathImplForClass($className, $logger);
        }

        if (!is_array($callback)) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('callback of unexpected type');
            return null;
        }

        if (ArrayUtil::isEmpty($callback)) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('callback is an empty array');
            return null;
        }

        $firstElement = $callback[0];

        if (is_string($firstElement) || is_object($firstElement)) {
            return self::getCallbackSourceFilePathImplForClass($firstElement, $logger);
        }

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('callback is an array but its first element is of unexpected type', ['firstElement type' => DbgUtil::getType($firstElement), 'firstElement' => $firstElement]);
        return null;
    }

    /**
     * @param mixed         $callback
     * @param LoggerFactory $loggerFactory
     *
     * @return ?string
     */
    public static function getCallbackSourceFilePath($callback, LoggerFactory $loggerFactory): ?string
    {
        $logger = $loggerFactory->loggerForClass(LogCategory::AUTO_INSTRUMENTATION, __NAMESPACE__, __CLASS__, __FILE__)
                                ->addAllContext(['callback type' => DbgUtil::getType($callback), 'callback' => $callback]);

        try {
            return self::getCallbackSourceFilePathImpl($callback, $logger);
        } catch (ReflectionException $e) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->logThrowable($e, 'Failed to reflect captured callback');
            return null;
        }
    }

    /**
     * @param mixed      $callback
     * @param-out string $groupKind
     * @param-out ?string $groupName
     */
    private function findCallbackInfo($callback, /* out */ ?string &$groupKind, /* out */ ?string &$groupName): void
    {
        if (($srcFilePath = self::getCallbackSourceFilePath($callback, $this->tracer->loggerFactory())) === null) {
            $groupKind = self::CALLBACK_GROUP_KIND_CORE;
            $groupName = null;
            return;
        }

        self::findAddonInfoFromFilePath($srcFilePath, $this->tracer->loggerFactory(), /* out */ $groupKind, /* out */ $groupName);
    }

    private function discoverServiceFramework(): ?NameVersionData
    {
        if ($this->isServiceFrameworkSet) {
            return $this->serviceFramework;
        }

        $loggerProxyDebug = $this->logger->ifDebugLevelEnabledNoLine(__FUNCTION__);

        if (!$this->isReadyToWrapFilterCallbacks) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Did not discover because expected WordPress code (_wp_filter_build_unique_id function) was not loaded');
            $this->isServiceFrameworkSet = true;
            return $this->serviceFramework;
        }

        global $wp_version;
        /** @var ?string $reasonNotDiscovered */
        $reasonNotDiscovered = null;
        if (isset($wp_version)) {
            if (is_string($wp_version)) {
                $this->serviceFramework = new NameVersionData(self::SERVICE_FRAMEWORK_NAME, $wp_version);
            } else {
                $reasonNotDiscovered = '$wp_version global variable is set but it is not a string; $wp_version: '
                                       . LoggableToString::convert(['type' => DbgUtil::getType($wp_version), 'value' => $wp_version]);
            }
        } else {
            $reasonNotDiscovered = '$wp_version global variable is not set';
        }

        if ($reasonNotDiscovered === null) {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Discovered', ['serviceFramework' => $this->serviceFramework]);
        } else {
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, 'Did not discover because ' . $reasonNotDiscovered);
        }

        $this->isServiceFrameworkSet = true;
        return $this->serviceFramework;
    }

    public function directCall(string $method): void
    {
        if ($this->isInFailedMode) {
            return;
        }

        $logger = $this->logger->inherit()->addAllContext(['method' => $method]);

        switch ($method) {
            case self::DIRECT_CALL_METHOD_SET_READY_TO_WRAP_FILTER_CALLBACKS:
                $this->setReadyToWrapFilterCallbacks();
                return;

            default:
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Unexpected  method');
                $this->switchToFailedMode();
        }
    }

    /**
     * @param ?string $instrumentedClassFullName
     * @param string  $instrumentedFunction
     * @param mixed[] $capturedArgs
     *
     * @return null|callable(?Throwable $thrown, mixed $returnValue): void
     */
    public function preHook(?string $instrumentedClassFullName, string $instrumentedFunction, array $capturedArgs): ?callable
    {
        if ($this->isInFailedMode) {
            return null /* <- null means there is no post-hook */;
        }

        $logger = $this->logger->inherit()->addAllContext(
            ['instrumentedClassFullName' => $instrumentedClassFullName, 'instrumentedFunction' => $instrumentedFunction, 'capturedArgs' => $capturedArgs]
        );

        // We should cover all the function instrumented in src/ext/WordPress_instrumentation.c

        if ($instrumentedClassFullName !== null) {
            if ($instrumentedClassFullName !== 'WP_Hook') {
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Unexpected instrumentedClassFullName');
                $this->switchToFailedMode();
                return null /* <- null means there is no post-hook */;
            }
            if ($instrumentedFunction !== 'add_filter') {
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Unexpected instrumentedFunction');
                $this->switchToFailedMode();
                return null /* <- null means there is no post-hook */;
            }

            $this->preHookAddFilter($capturedArgs);
            return null /* <- null means there is no post-hook */;
        }

        switch ($instrumentedFunction) {
            case '_wp_filter_build_unique_id':
                $this->preHookWpFilterBuildUniqueId($capturedArgs);
                return null /* <- null means there is no post-hook */;
            case 'get_template':
                /**
                 * @param ?Throwable $thrown
                 * @param mixed      $returnValue
                 */
                return function (?Throwable $thrown, $returnValue): void {
                    $this->postHookGetTemplate($thrown, $returnValue);
                };
            default:
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Unexpected instrumentedFunction');
                $this->switchToFailedMode();
        }
        return null /* <- null means there is no post-hook */;
    }

    /**
     * @param mixed[] $capturedArgs
     *
     * @return void
     */
    private function preHookAddFilter(array $capturedArgs): void
    {
        if (!$this->isReadyToWrapFilterCallbacks) {
            static $isFirstTime = true;
            if ($isFirstTime) {
                ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('First attempt to wrap callback but it is not ready yet');
                $isFirstTime = false;
            }
            return;
        }

        if (!$this->preHookAddFilterImpl($capturedArgs)) {
            $this->switchToFailedMode();
        }
    }

    /**
     * @param mixed[] $capturedArgs
     *
     * @return void
     */
    private function preHookWpFilterBuildUniqueId(array $capturedArgs): void
    {
        if (!$this->preHookWpFilterBuildUniqueIdImpl($capturedArgs)) {
            $this->switchToFailedMode();
        }
    }

    /**
     * @param mixed[] $capturedArgs
     *
     * @return bool
     */
    private function preHookAddFilterImpl(array $capturedArgs): bool
    {
        if (!$this->verifyHookNameCallbackArgs($capturedArgs)) {
            return false;
        }
        /** @var string $hookName */
        $hookName = $capturedArgs[0];
        $callback =& $capturedArgs[1];

        if ($callback instanceof WordPressFilterCallbackWrapper) {
            return true;
        }

        $originalCallback = $callback;
        $this->findCallbackInfo($originalCallback, /* out */ $callbackGroupKind, /* out */ $callbackGroupName);
        $wrapper = new WordPressFilterCallbackWrapper($hookName, $originalCallback, $callbackGroupKind, $callbackGroupName);
        $callback = $wrapper;

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Callback has been wrapped', ['original callback' => $originalCallback, 'wrapper' => $wrapper]);
        return true;
    }

    /**
     * @param mixed[] $capturedArgs
     *
     * @return bool
     */
    private function preHookWpFilterBuildUniqueIdImpl(array $capturedArgs): bool
    {
        if (!$this->verifyHookNameCallbackArgs($capturedArgs)) {
            return false;
        }
        $callback =& $capturedArgs[1];

        if (!($callback instanceof WordPressFilterCallbackWrapper)) {
            return true;
        }

        $wrapper = $callback;
        $originalCallback = $wrapper->getWrappedCallback();
        $callback = $originalCallback;

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Callback has been unwrapped', ['original callback' => $originalCallback, 'wrapper' => $wrapper]);
        return true;
    }

    /**
     * @param mixed[] $capturedArgs
     *
     * @return bool
     */
    private function verifyHookNameCallbackArgs(array $capturedArgs): bool
    {
        //
        // We should get (see src/ext/WordPress_instrumentation.c):
        //      [0] $hook_name parameter by value
        //      [1] $callback parameter by reference
        //
        // function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
        // function _wp_filter_build_unique_id($hook_name, $callback, $priority)

        return $this->util->verifyExactArgsCount(2, $capturedArgs)
               && $this->util->verifyIsString($capturedArgs[0], 'hook_name')
               && $this->util->verifyIsCallable($capturedArgs[1], /* shouldCheckSyntaxOnly */ true, '$callback');
    }

    /**
     * @param ?Throwable $thrown
     * @param mixed      $returnValue
     */
    private function postHookGetTemplate(?Throwable $thrown, $returnValue): void
    {
        $logger = $this->logger->inherit()->addAllContext(['thrown' => $thrown, 'returnValue type' => DbgUtil::getType($returnValue), 'returnValue' => $returnValue]);

        if ($thrown !== null) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Instrumented function has thrown so there is no return value');
            return;
        }

        if ($returnValue === null) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Return value is null');
            return;
        }

        if (!is_string($returnValue)) {
            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('Return value is not a string');
            return;
        }

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Recording WordPress theme as a label on transaction', ['theme' => $returnValue, 'label key' => self::LABEL_KEY_FOR_WORDPRESS_THEME]);
        ElasticApm::getCurrentTransaction()->context()->setLabel(self::LABEL_KEY_FOR_WORDPRESS_THEME, $returnValue);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(
            [
                'isInFailedMode'               => $this->isInFailedMode,
                'isReadyToWrapFilterCallbacks' => $this->isReadyToWrapFilterCallbacks,
                'isServiceFrameworkSet'        => $this->isServiceFrameworkSet,
                'serviceFramework'             => $this->serviceFramework,
                'wrapper ctor calls count'     => WordPressFilterCallbackWrapper::$ctorCalls,
                'wrapper dtor calls count'     => WordPressFilterCallbackWrapper::$dtorCalls,
            ]
        );
    }
}
