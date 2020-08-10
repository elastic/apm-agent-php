<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface ExecutionSegmentContextInterface
{
    /**
     * A flat mapping of user-defined labels with string keys and null, string, boolean or number values.
     *
     * The length of a key and a string value is limited to 1024.
     *
     * @param string                     $key
     * @param string|bool|int|float|null $value
     *
     * @see getLabels() For the description
     *
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L40
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json#L46
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L88
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/tags.json
     */
    public function setLabel(string $key, $value): void;

    /**
     * @see setLabel() For the description

     * @return array<string, mixed>
     */
    public function getLabels(): array;

    public function isNoop(): bool;
}
