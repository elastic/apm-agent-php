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
use PDO;
use PDOStatement;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation extends AutoInstrumentationBase
{
    use AutoInstrumentationTrait;

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
        return InstrumentationNames::PDO;
    }

    /** @inheritDoc */
    public function otherNames(): array
    {
        return [InstrumentationNames::DB];
    }

    /** @inheritDoc */
    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('pdo')) {
            return;
        }

        $this->pdoConstruct($ctx);
        $this->pdoExec($ctx);
        $this->pdoQuery($ctx);
        $this->pdoPrepare($ctx);
        // $this->pdoCommit($ctx);
        $this->pdoStatementExecute($ctx);
    }

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

    private function pdoConstruct(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            '__construct',
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs): ?callable {
                if (!($interceptedCallThis instanceof PDO)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'interceptedCallThis is not an instance of class PDO',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                if (count($interceptedCallArgs) < 1) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Number of received arguments for PDO::__construct call is less than expected.'
                        . 'PDO::__construct is expected to have at least one argument (Data Source Name - DSN)',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                $dsn = $interceptedCallArgs[0];
                if (!is_string($dsn)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'The first received argument for PDO::__construct call is not a string'
                        . ' but PDO::__construct is expected to have Data Source Name (DSN) as the first argument ',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                /** var string */
                $dbSpanSubtype = '';
                DataSourceNameParser::parse($dsn, /* ref */ $dbSpanSubtype);
                self::setDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    $dbSpanSubtype
                );

                return null; // no post-hook
            }
        );
    }

    private function pdoInterceptCallToSpan(RegistrationContextInterface $ctx, string $methodName): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            $methodName,
            /**
             * @param object|null $interceptedCallThis Intercepted call $this
             * @param mixed[]     $interceptedCallArgs Intercepted call arguments
             *
             * @return callable
             */
            function (?object $interceptedCallThis, array $interceptedCallArgs) use ($methodName): ?callable {
                if (!($interceptedCallThis instanceof PDO)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'interceptedCallThis is not an instance of class PDO',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                if (count($interceptedCallArgs) > 0) {
                    $statement = $interceptedCallArgs[0];
                    if (!is_string($statement)) {
                        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'The first received argument for PDO::' . $methodName . ' call is not a string'
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
                    Constants::SPAN_TYPE_DB_SUBTYPE_UNKNOWN /* <- defaultValue */
                );
                /** @var string $spanSubtype */

                $span = $this->beginDbSpan($statement ?? ('PDO->' . $methodName), $spanSubtype, $statement);

                return self::createPostHookFromEndSpan($span);
            }
        );
    }

    private function beginDbSpan(string $name, string $spanSubtype, ?string $statement): SpanInterface
    {
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            $name,
            Constants::SPAN_TYPE_DB,
            $spanSubtype,
            Constants::SPAN_ACTION_DB_QUERY
        );

        if ($statement !== null) {
            $span->context()->db()->setStatement($statement);
        }

        self::setService($span, $spanSubtype);

        return $span;
    }

    private static function setService(SpanInterface $span, string $spanSubtype): void
    {
        $span->context()->destination()->setService($spanSubtype, $spanSubtype, $spanSubtype);
    }

    private function pdoExec(RegistrationContextInterface $ctx): void
    {
        $this->pdoInterceptCallToSpan($ctx, 'exec');
    }

    private function pdoQuery(RegistrationContextInterface $ctx): void
    {
        $this->pdoInterceptCallToSpan($ctx, 'query');
    }

    // private function pdoCommit(RegistrationContextInterface $ctx): void
    // {
    //     $this->interceptCallToSpan($ctx, 'commit');
    // }


    private function pdoPrepare(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
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
                if (!($interceptedCallThis instanceof PDO)) {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'interceptedCallThis is not an instance of class PDO',
                        ['interceptedCallThis' => $interceptedCallThis]
                    );
                    return null; // no post-hook
                }

                $spanSubtype = self::getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    Constants::SPAN_TYPE_DB_SUBTYPE_UNKNOWN /* <- defaultValue */
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

                    if (!($returnValueOrThrown instanceof PDOStatement)) {
                        ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'returnValueOrThrown is not an instance of class PDOStatement',
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

    private function pdoStatementExecute(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDOStatement',
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
                    $interceptedCallThis instanceof PDOStatement
                    && isset($interceptedCallThis->queryString) // @phpstan-ignore-line
                    && is_string($interceptedCallThis->queryString)
                )
                    ? $interceptedCallThis->queryString
                    : null;

                $spanSubtype = self::getDynamicallyAttachedProperty(
                    $interceptedCallThis,
                    self::DYNAMICALLY_ATTACHED_PROPERTY_DB_SPAN_SUBTYPE,
                    Constants::SPAN_TYPE_DB_SUBTYPE_UNKNOWN /* <- defaultValue */
                );
                /** @var string $spanSubtype */

                $span = $this->beginDbSpan($statement ?? 'PDOStatement->execute', $spanSubtype, $statement);

                self::setService($span, $spanSubtype);

                return self::createPostHookFromEndSpan($span);
            }
        );
    }
}
