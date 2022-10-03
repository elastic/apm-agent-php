<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

error_reporting(E_ALL | E_STRICT);

use ElasticApmTests\TestsRootDir;

// Ensure that composer has installed all dependencies
if (!file_exists(dirname(__DIR__) . '/composer.lock')) {
    die("Dependencies must be installed using composer\n");
}

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/polyfills/load.php';
require __DIR__ . '/dummyFuncForTestsWithoutNamespace.php';
require __DIR__ . '/ElasticApmTests/dummyFuncForTestsWithNamespace.php';

require __DIR__ . '/ElasticApmTests/ComponentTests/appCodeForTestCaughtExceptionResponded500.php';
require __DIR__ . '/ElasticApmTests/ComponentTests/appCodeForTestPhpErrorUncaughtException.php';
require __DIR__ . '/ElasticApmTests/ComponentTests/appCodeForTestPhpErrorUndefinedVariable.php';

TestsRootDir::$fullPath = __DIR__;
