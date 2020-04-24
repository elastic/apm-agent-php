<?php
namespace ElasticApm\Impl;

$wasBootstrapPhpPartCalledFlag = false;

function bootstrapPhpPart(): void
{
    global $wasBootstrapPhpPartCalledFlag;
    $wasBootstrapPhpPartCalledFlag = true;
}

function wasBootstrapPhpPartCalled(): bool
{
    global $wasBootstrapPhpPartCalledFlag;
    return $wasBootstrapPhpPartCalledFlag;
}
