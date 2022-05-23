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
use Elastic\Apm\SpanInterface;
use mysqli;
use mysqli_stmt;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MySQLiAutoInstrumentation extends AutoInstrumentationBase
{
    use AutoInstrumentationTrait;

    private const MYSQLI_QUERY_ID = 1;
    private const DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE
        = 'Elastic_APM_dynamically_attached_property_DB_span_subtype';

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
        $this->registerDelegatingToHandleTracker($ctx, 'mysqli_query', self::MYSQLI_QUERY_ID);
        $this->mysqliConstruct($ctx);
        $this->mysqliQuery($ctx);
        $this->mysqliPrepare($ctx);
        $this->mysqliStatementExecute($ctx);
    }

    public function registerDelegatingToHandleTracker(
        RegistrationContextInterface $ctx,
        string $funcName,
        int $funcId
    ): void {
        $ctx->interceptCallsToFunction(
            $funcName,
            /**
             * @param mixed[] $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             *
             * @phpstan-return callable(int, bool, mixed): mixed
             */
            function (array $interceptedCallArgs) use ($funcName, $funcId): ?callable {
                $statement = (
                    $interceptedCallArgs instanceof mysqli_stmt
                    && isset($interceptedCallArgs[1]->mysqliQuery) // @phpstan-ignore-line
                    && is_string($interceptedCallArgs[1]->queryString)
                )
                    ? $interceptedCallArgs[1]->queryString
                    : null;

                $spanSubtype = self::getDynamicallyAttachedProperty(
                    (object)["hi" => $interceptedCallArgs],
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL /* <- defaultValue */
                );
                /** @var string $spanSubtype */

                $span = $this->beginDbSpan($statement ?? $funcName, $spanSubtype, $statement);

                $span->context()->destination()->setService('mysqli', 'mysql', Constants::SPAN_TYPE_DB);

                return self::createPostHookFromEndSpan($span);
            }
        );
    }    

    /**
     * @param string  $funcName
     * @param int     $funcId
     * @param mixed[] $interceptedCallArgs Intercepted call arguments
     *
     * @return callable
     * @phpstan-return callable(int, bool, mixed): mixed
     */


    /**
     * @param string  $funcName
     * @param int     $funcId
     * @param mixed[] $interceptedCallArgs Intercepted call arguments
     *
     * @return callable
     * @phpstan-return callable(int, bool, mixed): mixed
     */


    /**
     * @param object $obj
     * @param string $propName
     * @param mixed  $val
     *
     * @return void
     */
    private static function setDynamicallyAttachedProperty(object $obj, string $propName, $val): void
    {
        $obj->{$propName} = $val;
    }

    /**
     * @param ?object $obj
     * @param string  $propName
     * @param mixed   $defaultValue
     *
     * @return mixed
     */
    private static function getDynamicallyAttachedProperty(?object $obj, string $propName, $defaultValue)
    {
        return ($obj !== null) && isset($obj->{$propName}) ? $obj->{$propName} : $defaultValue;
    }

    private function mysqliConstruct(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'mysqli',
            '__construct',
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs): ?callable {
                if (!($interceptedCallThis instanceof mysqli)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'interceptedCallThis is not an instance of class mysqli',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                $dbSpanSubtype = 'mysql';
                self::setDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    $dbSpanSubtype
                );

                return null; // no post-hook
            }
        );
    }

    private function mysqliInterceptCallToSpan(RegistrationContextInterface $ctx, string $methodName): void
    {

        $ctx->interceptCallsToMethod(
            'mysqli',
            $methodName,
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             */
            
            function (?object $interceptedCallThis, array $interceptedCallArgs) use ($methodName): ?callable {
                if (!($interceptedCallThis instanceof mysqli)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'interceptedCallThis is not an instance of class mysqli',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                if (count($interceptedCallArgs) > 0) {
                    $statement = $interceptedCallArgs[0];
                    if (!is_string($statement)) {
                        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'The first received argument for mysqli::' . $methodName . ' call is not a string'
                            . ' so statement cannot be captured',
                            ['interceptedCallThis' => $interceptedCallThis]
                        );
                        $statement = null;
                    }
                } else {
                    $statement = null;
                }
                /** @var ?string $statement */

                $spanSubtype = self::getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL /* <- defaultValue */
                );
                /** @var string $spanSubtype */

                $span = $this->beginDbSpan($statement ?? ('mysqli->' . $methodName), $spanSubtype, $statement);

                return self::createPostHookFromEndSpan($span);
            }
            
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


    private function mysqliQuery(RegistrationContextInterface $ctx): void
    {

        $this->mysqliInterceptCallToSpan($ctx, 'query');
    }


    private function mysqliPrepare(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'mysqli',
            'prepare',
            /**
             * Pre-hook
             *
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             */
            function (
                ?object $interceptedCallThis,
                /** @noinspection PhpUnusedParameterInspection */ array $interceptedCallArgs
            ): ?callable {
                if (!($interceptedCallThis instanceof mysqli)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'interceptedCallThis is not an instance of class mysqli',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                $spanSubtype = self::getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL /* <- defaultValue */
                );

                /**
                 * Post-hook
                 *
                 * @param int   $numberOfStackFramesToSkip
                 * @param bool  $hasExitedByException
                 * @param mixed $returnValueOrThrown Return value of the intercepted call or thrown object
                 */
                return function (
                    int $numberOfStackFramesToSkip,
                    bool $hasExitedByException,
                    $returnValueOrThrown
                ) use (
                    $spanSubtype
                ): void {
                    if ($hasExitedByException || $returnValueOrThrown === false) {
                        return;
                    }

                    if (!($returnValueOrThrown instanceof mysqli_stmt)) {
                        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'returnValueOrThrown is not an instance of class mysqli_stmt',
                            ['returnValueOrThrown' => $returnValueOrThrown]
                        );
                        return;
                    }

                    self::setDynamicallyAttachedProperty(
                        $returnValueOrThrown,
                        self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                        $spanSubtype
                    );
                };
            }
        );
    }

    private function mysqliStatementExecute(RegistrationContextInterface $ctx, array $interceptedCallArgs= []): void
    {
        $ctx->interceptCallsToMethod(
            'mysqli_stmt',
            'execute',
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             *
             */
            function (
                ?object $interceptedCallThis,
                /** @noinspection PhpUnusedParameterInspection */ array $interceptedCallArgs
            ): ?callable {
                $statement = (
                    $interceptedCallThis instanceof mysqli_stmt
                    && isset($interceptedCallThis->mysqliQuery) // @phpstan-ignore-line
                    && is_string($interceptedCallThis->queryString)
                )
                    ? $interceptedCallThis->queryString
                    : null;

                $spanSubtype = self::getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    Constants::SPAN_TYPE_DB_SUBTYPE_MYSQL /* <- defaultValue */
                );
                /** @var string $spanSubtype */

                $span = $this->beginDbSpan($statement ?? 'mysqli_stmt->execute', $spanSubtype, $statement);

                $span->context()->destination()->setService('mysqli', 'mysql', Constants::SPAN_TYPE_DB);

                return self::createPostHookFromEndSpan($span);
            }
        );
    }

}
