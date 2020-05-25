<?php

declare(strict_types=1);

require __DIR__ . '/../../../bootstrap.php';

use Elastic\Apm\Tests\ComponentTests\Util\BuiltinHttpServerAppCodeHost;

/** @noinspection PhpUnhandledExceptionInspection */
BuiltinHttpServerAppCodeHost::run(__FILE__);
