<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\PluginInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Tracer;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BuiltinPlugin implements PluginInterface
{
    /** @var PdoAutoInstrumentation */
    private $pdoAutoInstrumentation;

    /** @var CurlAutoInstrumentation */
    private $curlAutoInstrumentation;

    public function __construct(Tracer $tracer)
    {
        $this->pdoAutoInstrumentation = new PdoAutoInstrumentation($tracer);
        $this->curlAutoInstrumentation = new CurlAutoInstrumentation($tracer);
    }

    public function register(RegistrationContextInterface $ctx): void
    {
        $this->pdoAutoInstrumentation->register($ctx);
        $this->curlAutoInstrumentation->register($ctx);
    }

    public function getDescription(): string
    {
        return 'BUILT-IN';
    }
}
