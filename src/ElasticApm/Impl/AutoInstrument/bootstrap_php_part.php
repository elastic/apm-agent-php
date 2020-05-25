<?php

declare(strict_types=1);

use Elastic\Apm\Impl\AutoInstrument\Autoloader;

require __DIR__ . DIRECTORY_SEPARATOR . 'BootstrapStageLogger.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'Autoloader.php';
Autoloader::register();

require __DIR__ . DIRECTORY_SEPARATOR . 'PhpPartFacade.php';
