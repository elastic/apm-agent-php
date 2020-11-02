<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Elastic\Apm\Impl\EventData;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class StacktraceFrame extends EventData
{
    /**
     * @var string
     *
     * The relative filename of the code involved in the stack frame, used e.g. to do error checksumming
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/stacktrace_frame.json#L19
     */
    public $filename;

    /**
     * @var string|null
     *
     * The function involved in the stack frame
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/stacktrace_frame.json#L23
     */
    public $function = null;

    /**
     * @var int
     *
     * The line number of code part of the stack frame, used e.g. to do error checksumming
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/stacktrace_frame.json#L31
     */
    public $lineno;

    public function __construct(string $filename, int $lineno)
    {
        $this->filename = $filename;
        $this->lineno = $lineno;
    }

    public function __toString(): string
    {
        // TODO: Sergey Kleyman: Fix the issue wtih logging StacktraceFrame::function, filename
        return $this->toStringExcludeProperties(['filename', 'function']);
    }
}
