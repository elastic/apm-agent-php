<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface as ConfigRawSnapshotSourceInterface;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TracerDependencies implements LoggableInterface
{
    /** @var ?ClockInterface */
    public $clock = null;

    /** @var ?ConfigRawSnapshotSourceInterface */
    public $configRawSnapshotSource = null;

    /** @var ?EventSinkInterface */
    public $eventSink = null;

    /** @var ?Log\SinkInterface */
    public $logSink = null;

    public function toLog(LogStreamInterface $stream): void
    {
        $getDependencyType = function (?object $dep): ?string {
            return is_null($dep) ? null : get_class($dep);
        };

        $stream->toLogAs(
            [
                'clock'                   => $getDependencyType($this->clock),
                'configRawSnapshotSource' => $getDependencyType($this->configRawSnapshotSource),
                'eventSink'               => $getDependencyType($this->eventSink),
                'logSink'                 => $getDependencyType($this->logSink),
            ]
        );
    }
}
