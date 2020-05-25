<?php

declare(strict_types=1);

require __DIR__ . '/../../../bootstrap.php';

use Elastic\Apm\Tests\ComponentTests\Util\CliScriptAppCodeHost;

/** @noinspection PhpUnhandledExceptionInspection */
CliScriptAppCodeHost::run(__FILE__);
