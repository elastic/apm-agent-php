<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ProcessData extends EventData implements ProcessDataInterface, JsonSerializable
{
    /** @var int */
    protected $pid;

    /** @inheritDoc */
    public function pid(): int
    {
        return $this->pid;
    }

    public static function dataToString(ProcessDataInterface $data, string $type): string
    {
        $builder = new ObjectToStringBuilder($type);
        $builder->add('pid', $data->pid());
        return $builder->build();
    }

    public function __toString(): string
    {
        return self::dataToString($this, 'ProcessData');
    }
}
