<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface as ConfigRawSnapshotSourceInterface;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TracerDependencies
{
    /** @var ?ClockInterface */
    public $clock = null;

    /** @var ?ConfigRawSnapshotSourceInterface */
    public $configRawSnapshotSource = null;

    /** @var ?EventSinkInterface */
    public $eventSink = null;

    /** @var ?Log\SinkInterface */
    public $logSink = null;

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder();
        $builder->add('clock', self::depToString($this->clock));
        $builder->add('configRawSnapshotSource', self::depToString($this->configRawSnapshotSource));
        $builder->add('eventSink', self::depToString($this->eventSink));
        $builder->add('logSink', self::depToString($this->logSink));
        return $builder->build();
    }

    private static function depToString(?object $dep): string
    {
        return is_null($dep) ? 'null' : get_class($dep);
    }
}
