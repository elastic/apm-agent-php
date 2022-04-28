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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TempFileUtilForTests
{
    use StaticClassTrait;

    public static function createTempIniFile(string $type): string
    {
        $fileNamePrefix = 'Elastic_APM_PHP_Agent_component_tests_-_' . $type . '_-_';
        $tempFileFullPath = tempnam(sys_get_temp_dir(), $fileNamePrefix);
        if ($tempFileFullPath === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to create a temporary INI file',
                    ['sys_get_temp_dir' => sys_get_temp_dir(), '$fileNamePrefix' => $fileNamePrefix]
                )
            );
        }

        return $tempFileFullPath;
    }

    public static function deleteTempIniFile(string $tempFileFullPath): void
    {
        if (!file_exists($tempFileFullPath)) {
            return;
        }

        if (!unlink($tempFileFullPath)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to delete temporary file',
                    ['tempIniFileFullPath' => $tempFileFullPath]
                )
            );
        }
    }
}
