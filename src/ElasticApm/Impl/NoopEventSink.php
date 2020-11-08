<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\NoopObjectTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopEventSink implements EventSinkInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function consume(
        Metadata $metadata,
        array $spansData,
        array $errorsData,
        ?TransactionData $transactionData
    ): void {
    }
}
