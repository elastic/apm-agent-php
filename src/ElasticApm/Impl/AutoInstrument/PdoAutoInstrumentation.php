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

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\SpanInterface;
use PDOStatement;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation implements LoggableInterface
{
    use LoggableTrait;

    /** @var Tracer */
    private $tracer;

    /** @var Logger */
    private $logger;

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
        if (!extension_loaded('pdo')) {
            return;
        }

        $this->pdoConstruct($ctx);
        $this->pdoExec($ctx);
        // $this->pdoQuery($ctx);
        $this->pdoPrepare($ctx);
        $this->pdoCommit($ctx);
        $this->pdoStatementExecute($ctx);
    }

    public function pdoConstruct(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            '__construct',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook(?object $interceptedCallThis, array $interceptedCallArgs): void
                    {
                        self::assertInterceptedCallThisIsNotNull($interceptedCallThis, $interceptedCallArgs);

                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            'PDO->__construct('
                            . (count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : '')
                            . ')',
                            Constants::SPAN_TYPE_DB
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    public function pdoExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'exec',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook($thisObj, array $interceptedCallArgs): void
                    {
                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO->exec',
                            Constants::SPAN_TYPE_DB
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    public function pdoQuery(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'query',
            function (): InterceptedCallTrackerInterface {
                return new class ($this->tracer) implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var Logger */
                    private $logger;

                    /** @var SpanInterface */
                    private $span;

                    public function __construct(Tracer $tracer)
                    {
                        $this->logger = $tracer->loggerFactory()->loggerForClass(
                            LogCategory::AUTO_INSTRUMENTATION,
                            __NAMESPACE__,
                            __CLASS__,
                            __FILE__
                        )->addContext('this', $this);
                    }

                    public function preHook($thisObj, array $interceptedCallArgs): void
                    {
                        // ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
                        // && $loggerProxy->log(
                        //     'Entered',
                        //     [
                        //         'DbgUtil::getType($thisObj)'  => DbgUtil::getType($thisObj),
                        //         'count($interceptedCallArgs)' => count($interceptedCallArgs),
                        //     ]
                        // );

                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO->query',
                            Constants::SPAN_TYPE_DB
                        );

                        // ($loggerProxy = $this->logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
                        // && $loggerProxy->log('Exiting...');
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    public function pdoCommit(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'commit',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook($thisObj, array $interceptedCallArgs): void
                    {
                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            'PDO->commit',
                            Constants::SPAN_TYPE_DB
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    public function pdoPrepare(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'prepare',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook($thisObj, array $interceptedCallArgs): void
                    {
                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            'PDO->prepare'
                            . (count($interceptedCallArgs) > 0 ? (': ' . $interceptedCallArgs[0]) : ''),
                            Constants::SPAN_TYPE_DB
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    public function pdoStatementExecute(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDOStatement',
            'execute',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook($thisObj, array $interceptedCallArgs): void
                    {
                        $spanName
                            = (
                            !is_null($thisObj)
                            && is_object($thisObj)
                            && $thisObj instanceof PDOStatement
                            && isset($thisObj->queryString)
                            && is_string($thisObj->queryString)
                        )
                            ? $thisObj->queryString
                            : 'PDOStatement->execute';

                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            $spanName,
                            Constants::SPAN_TYPE_DB
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        self::endSpan(
                            $numberOfStackFramesToSkip + 1,
                            $this->span,
                            $hasExitedByException,
                            $returnValueOrThrown
                        );
                    }
                };
            }
        );
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return ['logger', 'tracer'];
    }
}
