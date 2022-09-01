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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\SpanInterface;
use mysqli_stmt;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySQLiAutoInstrumentation extends AutoInstrumentationBase
{
    use AutoInstrumentationTrait;

    private const DYNAMICALLY_ATTACHED_PROPERTY_MYSQLI_QUERY = 'Elastic_APM_dynamically_attached_property_mysqli_query';

    /** @var Logger */
    private $logger;

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
        return InstrumentationNames::MYSQLI;
    }

    /** @inheritDoc */
    public function otherNames(): array
    {
        return [InstrumentationNames::DB];
    }

    /** @inheritDoc */
    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('mysqli')) {
            return;
        }

        $this->interceptCallsToConstructWithOneArg($ctx, 'mysqli', '__construct', 'mysqli_connect');
        $this->interceptCallsToQuery($ctx);
        $this->interceptCallsToPrepare($ctx);
        $this->interceptCallsToExecute($ctx);
        $this->interceptCallsWithoutArg($ctx, 'mysqli', 'ping', 'mysqli_ping');
        $this->interceptCallsWithoutArg($ctx, 'mysqli', 'close', 'mysqli_close');
        $this->interceptCallsWithoutArg($ctx, 'mysqli', 'begin_transaction', 'mysqli_begin_transaction');
        $this->interceptCallsWithoutArg($ctx, 'mysqli', 'commit', 'mysqli_commit');
        $this->interceptCallsWithoutArg($ctx, 'mysqli', 'rollback', 'mysqli_rollback');
        $this->interceptCallsWithOneArg($ctx, 'mysqli', 'select_db', 'mysqli_select_db');
        $this->interceptCallsWithOneArg($ctx, 'mysqli', 'set_charset', 'mysqli_set_charset');
        $this->interceptCallsWithOneArg($ctx, 'mysqli', 'autocommit', 'mysqli_autocommit');
        $this->interceptCallsWithOneArg($ctx, 'mysqli', 'kill', 'mysqli_kill');
    }

    private function interceptCallsToConstructWithOneArg(
        RegistrationContextInterface $ctx,
        string                       $className,
        string                       $methodName,
        string                       $funcName
    ): void {
        $preHook = function (
            $interceptedCallThis,
            array $interceptedCallArgs,
            ?string $className,
            string $funcName
        ): ?callable {
            return $this->createSpan($interceptedCallArgs, $funcName, $className);
        };

        $this->interceptCallsTo($ctx, $className, $methodName, $funcName, $preHook);
    }

    private function interceptCallsWithOneArg(
        RegistrationContextInterface $ctx,
        string                       $className,
        string                       $methodName,
        string                       $funcName
    ): void {
        $preHook = function (
            ?object $interceptedCallThis,
            array   $interceptedCallArgs,
            ?string $className,
            string  $funcName
        ): ?callable {
            return $this->createSpan($interceptedCallArgs, $funcName, $className);
        };

        $this->interceptCallsTo($ctx, $className, $methodName, $funcName, $preHook);
    }

    private function createSpan(array $interceptedCallArgs, string $funcName, string $className = null): ?callable
    {
        if(!$this->checkArgumentsForExistence($interceptedCallArgs)){
            return null;
        }

        $firstArg = $interceptedCallArgs[0];

        $query = (isset($firstArg) && is_string($firstArg)) ? $firstArg : null;
        $spanName = $funcName . '(' . $firstArg . ')';

        if ($className) {
            $spanName = 'mysqli->' . $funcName . '(' . $firstArg . ')';
        }

        return self::createPostHookFromEndSpan(
            $this->beginDbSpan($spanName, Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL, $query)
        );
    }

    private function interceptCallsWithoutArg(
        RegistrationContextInterface $ctx,
        string                       $className,
        string                       $methodName,
        string                       $funcName
    ): void {
        $preHook = function (
            ?object $interceptedCallThis,
            array   $interceptedCallArgs,
            ?string $className,
            string  $funcName
        ): ?callable {
            $spanName = $className ? 'mysqli->' . $funcName : $funcName . '()';
            return self::createPostHookFromEndSpan(
                $this->beginDbSpan($spanName, Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL, null)
            );
        };

        $this->interceptCallsTo($ctx, $className, $methodName, $funcName, $preHook);
    }

    private function interceptCallsToQuery(RegistrationContextInterface $ctx): void
    {
        $preHook = function (?object $interceptedCallThis, array $interceptedCallArgs): ?callable {
            if(!$this->checkArgumentsForExistence($interceptedCallArgs)){
                return null;
            }

            $query = $interceptedCallArgs[1];
            $spanName = $interceptedCallArgs[0];

            return self::createPostHookFromEndSpan(
                $this->beginDbSpan($spanName, Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL, $query)
            );
        };

        $this->interceptCallsTo($ctx, 'mysqli', 'query', 'mysqli_query', $preHook);
    }

    private function interceptCallsToPrepare(RegistrationContextInterface $ctx): void
    {
        $preHook = function (?object $interceptedCallThis, array $interceptedCallArgs): ?callable {
            if(!$this->checkArgumentsForExistence($interceptedCallArgs)){
                return null;
            }

            $query = $interceptedCallArgs[0];

            return function (
                int $numberOfStackFramesToSkip,
                bool $hasExitedByException,
                $returnValueOrThrown
            ) use (
                $query
            ): void {
                if (!$hasExitedByException) {
                    self::setDynamicallyAttachedProperty(
                        $returnValueOrThrown,
                        self::DYNAMICALLY_ATTACHED_PROPERTY_MYSQLI_QUERY,
                        $query
                    );
                }
            };
        };

        $this->interceptCallsTo($ctx, 'mysqli', 'prepare', 'mysqli_prepare', $preHook);
    }

    private function interceptCallsTo(
        RegistrationContextInterface $ctx,
        string                       $className,
        string                       $methodName,
        string                       $funcName,
        callable                     $preHook
    ): void {
        $ctx->interceptCallsToFunction(
            $funcName, function (array $interceptedCallArgs) use ($preHook, $funcName): ?callable {
            if(!$this->checkArgumentsForExistence($interceptedCallArgs)){
                return null;
            }

            return $preHook($interceptedCallArgs[0], array_slice($interceptedCallArgs, 1), null, $funcName);
        }
        );

        $ctx->interceptCallsToMethod(
            $className,
            $methodName,
            function (?object $interceptedCallThis, array $interceptedCallArgs) use (
                $preHook,
                $className,
                $methodName
            ): ?callable {
                return $preHook($interceptedCallThis, $interceptedCallArgs, $className, $methodName);
            }
        );
    }

    private function interceptCallsToExecute(RegistrationContextInterface $ctx): void
    {
        $preHook = function (?object $interceptedCallThis, array $interceptedCallArgs): ?callable {
            if (!$interceptedCallThis instanceof mysqli_stmt) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Unexpected argument type',
                    ['interceptedThis type' => DbgUtil::getType($interceptedCallThis)]
                );

                return null;
            }

            $query = self::getDynamicallyAttachedProperty(
                $interceptedCallThis,
                self::DYNAMICALLY_ATTACHED_PROPERTY_MYSQLI_QUERY,
                null /* <- defaultValue */
            );
            /** @var ?string $query */

            $span = $this->beginDbSpan(
                $query ?? 'mysqli_stmt execute', Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL,
                $query
            );

            $span->context()->destination()->setService(
                Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL,
                Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL,
                Constants::SPAN_TYPE_DB
            );

            return self::createPostHookFromEndSpan($span);
        };

        $this->interceptCallsTo(
            $ctx,
            'mysqli_stmt',
            'execute',
            'mysqli_stmt_execute',
            $preHook
        );
    }

    private function beginDbSpan(string $name, string $subtype, ?string $statement): SpanInterface
    {
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            $name,
            Constants::SPAN_TYPE_DB,
            $subtype,
            Constants::SPAN_ACTION_DB_QUERY
        );

        if ($statement !== null) {
            $span->context()->db()->setStatement($statement);
        }

        $span->context()->destination()->setService($subtype, $subtype, $subtype);

        return $span;
    }

    private static function setDynamicallyAttachedProperty(object $obj, string $propName, $val): void
    {
        $obj->{$propName} = $val;
    }

    private static function getDynamicallyAttachedProperty(?object $obj, string $propName, $defaultValue)
    {
        return ($obj !== null) && isset($obj->{$propName}) ? $obj->{$propName} : $defaultValue;
    }

    private function checkArgumentsForExistence($interceptedCallArgs): bool
    {
        if (count($interceptedCallArgs) < 1) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Number of received arguments for call is less than expected.');

            return false;
        }

        return true;
    }
}
