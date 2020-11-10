<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation
{
    /** @var Tracer */
    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    public function register(RegistrationContextInterface $ctx): void
    {
        if (!extension_loaded('pdo')) {
            return;
        }

        $this->pdoConstruct($ctx);
        $this->pdoExec($ctx);
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
                            'PDO::__construct('
                            . (count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : '')
                            . ')',
                            Constants::SPAN_TYPE_DB,
                            Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
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
                            count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO::exec',
                            Constants::SPAN_TYPE_DB,
                            Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
                        );
                    }

                    public function postHook(
                        int $numberOfStackFramesToSkip,
                        bool $hasExitedByException,
                        $returnValueOrThrown
                    ): void {
                        if (!$hasExitedByException) {
                            $this->span->context()->setLabel('rows_affected', (int)$returnValueOrThrown);
                        }

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
