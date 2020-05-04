<?php

declare(strict_types=1);

error_reporting(E_ALL | E_STRICT);

use Elastic\Apm\Tests\Deserialization\ServerApiSchemaValidator;

// Ensure that composer has installed all dependencies
if (!file_exists(dirname(__DIR__) . '/composer.lock')) {
    die(
        "Dependencies must be installed using composer:\n\nphp composer.phar install --dev\n\n"
        . "See http://getcomposer.org for help with installing composer\n"
    );
}

ServerApiSchemaValidator::$pathToSpecsRootDir = __DIR__ . '/APM_Server_intake_API_schema';

require __DIR__ . '/../vendor/autoload.php';
