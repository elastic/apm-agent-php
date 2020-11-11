<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\SpanInterface;
use PDOStatement;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation
{
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
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook($thisObj, array $interceptedCallArgs): void
                    {
                        $this->span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
                            count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO->query',
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
}
