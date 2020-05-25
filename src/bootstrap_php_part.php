<?php

declare(strict_types=1);

use Elastic\Apm\Impl\SrcRootDir;

require __DIR__ . '/ElasticApm/Impl/SrcRootDir.php';
SrcRootDir::$fullPath = __DIR__;

require __DIR__ . '/ElasticApm/Impl/AutoInstrument/bootstrap_php_part.php';
