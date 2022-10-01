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

namespace ElasticApmTests\Util;

use Closure;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class FileUtilForTests
{
    use StaticClassTrait;

    public static function normalizePath(string $inAbsolutePath): string
    {
        $result = realpath($inAbsolutePath);
        if ($result === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage("realpath failed", ['inAbsolutePath' => $inAbsolutePath])
            );
        }
        return $result;
    }

    /**
     * @param string                        $filePath
     * @param Closure                       $consumeLine
     *
     * @phpstan-param Closure(string): void $consumeLine
     */
    public static function readLines(string $filePath, Closure $consumeLine): void
    {
        $fileHandle = fopen($filePath, 'r');
        if ($fileHandle === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage('Failed to open file', ['filePath' => $filePath])
            );
        }

        while (($line = fgets($fileHandle)) !== false) {
            $consumeLine($line);
        }

        if (!feof($fileHandle)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage('Failed to read from file', ['filePath' => $filePath])
            );
        }

        fclose($fileHandle);
    }

    /**
     * @param string[] $list
     *
     * @return string
     */
    public static function listToPath(array $list): string
    {
        $result = '';
        foreach ($list as $pathElement) {
            if (!TextUtil::isEmptyString($result)) {
                $result .= DIRECTORY_SEPARATOR;
            }
            $result .= $pathElement;
        }
        return $result;
    }
}
