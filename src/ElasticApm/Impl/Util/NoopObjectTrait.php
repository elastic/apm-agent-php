<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Elastic\Apm\Impl\Log\LogConsts;
use Elastic\Apm\Impl\Log\LogStreamInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait NoopObjectTrait
{
    use SingletonInstanceTrait;

    public function isNoop(): bool
    {
        return true;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs([LogConsts::TYPE_KEY => DbgUtil::fqToShortClassName(get_class($this))]);
    }
}
