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
     * @param array<mixed> $list
     */
    public function writeList(array $list): void;

    /**
     * @param array<string, mixed> $map
     */
    public function writeMap(array $map): void;
}
