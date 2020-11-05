<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class LogStream implements LogStreamInterface
{
    /** @var mixed */
    public $value;

    public function isLastLevel(): bool
    {
        return false;
    }

    public function toLogAs($value): void
    {
        $this->value = $value;
    }
}
