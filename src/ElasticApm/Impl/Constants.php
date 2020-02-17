<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Constants
{
    /** @var int */
    public const EXECUTION_SEGMENT_ID_SIZE_IN_BYTES = 8;

    /** @var int */
    public const TRACE_ID_SIZE_IN_BYTES = 16;
}
