<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

require __DIR__ . '/ElasticApm/Impl/AutoInstrument/BootstrapShutdownHelper.php';

/** Called by elasticapm extension */
/** @noinspection PhpUnused */
function bootstrapTracerPhpPart(int $maxEnabledLogLevel): bool
{
    return BootstrapShutdownHelper::bootstrap($maxEnabledLogLevel, /* srcDir */ __DIR__);
}

/** Called by elasticapm extension */
/** @noinspection PhpUnused */
function shutdownTracerPhpPart(): void
{
    BootstrapShutdownHelper::shutdown();
}
