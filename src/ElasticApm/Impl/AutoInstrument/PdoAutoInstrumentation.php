<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation
{
    public static function register(RegistrationContextInterface $ctx): void
    {
        self::pdoConstruct($ctx);
        self::pdoExec($ctx);
    }

    public static function pdoConstruct(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            '__construct',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook(?object $interceptedCallThis, ...$interceptedCallArgs): void
                    {
                        self::assertInterceptedCallThisIsNotNull($interceptedCallThis, ...$interceptedCallArgs);

                        $this->span = ElasticApm::beginCurrentSpan(
                            'PDO::__construct('
                            . (count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : '')
                            . ')',
                            Constants::SPAN_TYPE_DB,
                            Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
                        );
                    }

                    public function postHook(bool $hasExitedByException, $returnValueOrThrown): void
                    {
                        self::endSpan($this->span, $hasExitedByException, $returnValueOrThrown);
                    }
                };
            }
        );
    }

    public static function pdoExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'exec',
            function (): InterceptedCallTrackerInterface {
                return new class implements InterceptedCallTrackerInterface {
                    use InterceptedCallTrackerTrait;

                    /** @var SpanInterface */
                    private $span;

                    public function preHook($thisObj, ...$interceptedCallArgs): void
                    {
                        $this->span = ElasticApm::beginCurrentSpan(
                            count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO::exec',
                            Constants::SPAN_TYPE_DB,
                            Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
                        );
                    }

                    public function postHook(bool $hasExitedByException, $returnValueOrThrown): void
                    {
                        if (!$hasExitedByException) {
                            $this->span->setLabel('rows_affected', (int)$returnValueOrThrown);
                        }

                        self::endSpan($this->span, $hasExitedByException, $returnValueOrThrown);
                    }
                };
            }
        );
    }
}
