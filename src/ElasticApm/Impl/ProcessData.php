<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ProcessData extends EventData implements ProcessDataInterface, JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /** @var int */
    protected $pid;

    /** @inheritDoc */
    public function pid(): int
    {
        return $this->pid;
    }
}
