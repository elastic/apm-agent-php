<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Pdo;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\AutoInstrumentationTrait;
use Elastic\Apm\Impl\AutoInstrument\InterceptedCallToSpanBase;
use Elastic\Apm\Impl\Constants;

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
        self::interceptCallsToMethod($ctx, 'PDO', '__construct', [__CLASS__, 'pdoConstruct']);
        self::interceptCallsToMethod($ctx, 'PDO', 'exec', [__CLASS__, 'pdoExec']);
    }

    public static function pdoConstruct(): InterceptedCallToSpanBase
    {
        return new class extends InterceptedCallToSpanBase {
            /** @inheritDoc */
            public function beginSpan(...$interceptedCallArgs): void
            {
                $this->span = ElasticApm::beginCurrentSpan(
                    'PDO::__construct'
                    . (count($interceptedCallArgs) > 0 ? '(' . $interceptedCallArgs[0] . ')' : ''),
                    Constants::SPAN_TYPE_DB,
                    // TODO: Sergey Kleyman: Deduce actual DB subtype
                    Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
                );
            }
        };
    }

    public static function pdoExec(): InterceptedCallToSpanBase
    {
        return new class extends InterceptedCallToSpanBase {
            /** @inheritDoc */
            public function beginSpan(...$interceptedCallArgs): void
            {
                $this->span = ElasticApm::beginCurrentSpan(
                // TODO: Sergey Kleyman: Implement constructing span name from SQL statement
                    count($interceptedCallArgs) > 0 ? $interceptedCallArgs[0] : 'PDO::exec',
                    Constants::SPAN_TYPE_DB,
                    Constants::SPAN_TYPE_DB_SUBTYPE_SQLITE
                );
            }

            /** @inheritDoc */
            public function endSpan(bool $hasExitedByException, $returnValueOrThrown): void
            {
                if (!$hasExitedByException) {
                    // TODO: Sergey Kleyman: use the corresponding property in Intake API
                    // https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L106
                    $this->span->setLabel('rows_affected', (int)$returnValueOrThrown);
                }

                parent::endSpan($hasExitedByException, $returnValueOrThrown);
            }
        };
    }
}
