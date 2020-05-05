<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Pdo;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\CallToSpanTrackerFactory;
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
        $className = 'PDO';

        $ctx->interceptCallsToInternalMethod(
            $className,
            '__construct',
            new CallToSpanTrackerFactory(
                [__CLASS__, 'pdoConstructBegin'],
                [__CLASS__, 'pdoConstructNormalEnd']
            )
        );
        $ctx->interceptCallsToInternalMethod(
            $className,
            'exec',
            new CallToSpanTrackerFactory(
                [__CLASS__, 'pdoExecBegin'],
                [__CLASS__, 'pdoExecNormalEnd']
            )
        );
    }

    /**
     * @param mixed ...$interceptedCallArgs
     *
     * @return SpanInterface
     */
    public static function pdoConstructBegin(...$interceptedCallArgs): SpanInterface
    {
        return ElasticApm::beginCurrentSpan(
            'PDO::__construct' . ( count($interceptedCallArgs) > 0 ? '(' . $interceptedCallArgs[0] . ')' : '' ),
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
    public static function pdoConstructNormalEnd(SpanInterface $span, $returnValue)
    {
        $span->setLabel('Return value type', DbgUtil::getType($returnValue));
        $span->end();
        return $returnValue;
    }

    /**
     * @param mixed ...$interceptedCallArgs
     *
     * @return SpanInterface
     */
    public static function pdoExecBegin(...$interceptedCallArgs): SpanInterface
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
    public static function pdoExecNormalEnd(SpanInterface $span, $returnValue)
    {
        $span->setLabel('Return value', (int)$returnValue);
        $span->end();
        return $returnValue;
    }
}
