<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Pdo;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\CallToSpanTrackerFactory;
use Elastic\Apm\Impl\Constants;
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
        $ctx->interceptMethod(
            'PDO',
            'exec',
            new CallToSpanTrackerFactory(
                [__CLASS__, 'execBegin'],
                [__CLASS__, 'execNormalEnd']
            )
        );
    }

    /**
     * @param mixed ...$interceptedCallArgs
     *
     * @return SpanInterface
     */
    public static function execBegin(...$interceptedCallArgs): SpanInterface
    {
        return ElasticApm::beginCurrentSpan(
            count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO::exec',
            Constants::SPAN_TYPE_DB,
            Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
        );
    }

    /**
     * @param SpanInterface $span
     * @param mixed         $returnValue Return value of the intercepted call
     *
     * @return mixed Value to return to the caller of the intercepted function
     */
    public static function execNormalEnd(SpanInterface $span, $returnValue)
    {
        $span->setLabel('Return value', (int)$returnValue);
        $span->end();
        return $returnValue;
    }
}
