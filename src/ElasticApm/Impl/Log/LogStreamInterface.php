<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface LogStreamInterface
{
    /**
     * @param mixed $value
     */
    public function toLogAs($value): void;

    public function isLastLevel(): bool;
}
