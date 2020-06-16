<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Constants;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class PdoAutoInstrumentation
{
    use AutoInstrumentationTrait;

    public static function register(RegistrationContextInterface $ctx): void
    {
        self::pdoExec($ctx);
    }

    public static function pdoExec(RegistrationContextInterface $ctx): void
    {
        $ctx->interceptCallsToMethod(
            'PDO',
            'exec',
            function (...$interceptedCallArgs): callable {
                $span = ElasticApm::beginCurrentSpan(
                // TODO: Sergey Kleyman: Implement constructing span name from SQL statement
                    count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO::exec',
                    Constants::SPAN_TYPE_DB,
                    Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
                );

                /**
                 * @param bool            $hasExitedByException
                 * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
                 */
                return function (bool $hasExitedByException, $returnValueOrThrown) use ($span): void {
                    if (!$hasExitedByException) {
                        // TODO: Sergey Kleyman: use the corresponding property in Intake API
                        // https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L106
                        $span->setLabel('rows_affected', (int)$returnValueOrThrown);
                    }

                    self::endSpan($span, $hasExitedByException, $returnValueOrThrown);
                };
            }
        );
    }
}
