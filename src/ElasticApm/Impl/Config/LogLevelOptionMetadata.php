<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\Level;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LogLevelOptionMetadata extends EnumOptionMetadata
{
    public function __construct(int $defaultValue)
    {
        parent::__construct(
            'log level',
            $defaultValue,
            [
                'OFF' => Level::OFF,
                'CRITICAL' => Level::CRITICAL,
                'ERROR' => Level::ERROR,
                'WARNING' => Level::WARNING,
                'NOTICE' => Level::NOTICE,
                'INFO' => Level::INFO,
                'DEBUG' => Level::DEBUG,
                'TRACE' => Level::TRACE
            ]
        );
    }
}
