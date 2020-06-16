<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface ProcessDataInterface
{
    /**
     * Process ID of the service
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/process.json#L6
     */
    public function pid(): int;
}
