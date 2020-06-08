<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\PluginInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BuiltinPlugin implements PluginInterface
{
    public function register(RegistrationContextInterface $ctx): void
    {
        PdoAutoInstrumentation::register($ctx);
        CurlAutoInstrumentation::register($ctx);
    }

    public function getDescription(): string
    {
        return 'BUILT-IN';
    }
}
