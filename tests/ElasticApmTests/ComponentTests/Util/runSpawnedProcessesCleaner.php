<?php

declare(strict_types=1);

require __DIR__ . '/../../../bootstrap.php';

use Elastic\Apm\Tests\ComponentTests\Util\SpawnedProcessesCleaner;

/** @noinspection PhpUnhandledExceptionInspection */
SpawnedProcessesCleaner::run(__FILE__);
