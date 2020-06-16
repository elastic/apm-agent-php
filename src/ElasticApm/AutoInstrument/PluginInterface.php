<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface PluginInterface
{
    public function register(RegistrationContextInterface $ctx): void;
}
