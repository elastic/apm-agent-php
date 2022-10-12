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

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\SrcRootDir;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Autoloader
{
    private const AUTOLOAD_FQ_CLASS_NAME_PREFIX = 'Elastic\\Apm\\';

    /** @var int */
    private static $autoloadFqClassNamePrefixLength;

    /** @var string */
    private static $elasticApmSrcDir;

    public static function register(): void
    {
        self::$elasticApmSrcDir = SrcRootDir::$fullPath . DIRECTORY_SEPARATOR . 'ElasticApm';
        self::$autoloadFqClassNamePrefixLength = strlen(self::AUTOLOAD_FQ_CLASS_NAME_PREFIX);

        spl_autoload_register([__CLASS__, 'autoloadCodeForClass']);
    }

    private static function shouldAutoloadCodeForClass(string $fqClassName): bool
    {
        // does the class use the namespace prefix?
        return strncmp(self::AUTOLOAD_FQ_CLASS_NAME_PREFIX, $fqClassName, self::$autoloadFqClassNamePrefixLength) == 0;
    }

    public static function autoloadCodeForClass(string $fqClassName): void
    {
        // Example of $fqClassName: Elastic\Apm\Impl\Util\Assert

        BootstrapStageLogger::logTrace("Entered with fqClassName: `$fqClassName'", __LINE__, __FUNCTION__);

        if (!self::shouldAutoloadCodeForClass($fqClassName)) {
            BootstrapStageLogger::logTrace(
                "shouldAutoloadCodeForClass returned false."
                . " fqClassName: `$fqClassName'",
                __LINE__,
                __FUNCTION__
            );
            return;
        }

        // get the relative class name
        $relativeClass = substr($fqClassName, self::$autoloadFqClassNamePrefixLength);
        $classSrcFileRelative = ((DIRECTORY_SEPARATOR === '\\')
                ? $relativeClass
                : str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)) . '.php';
        $classSrcFileAbsolute = self::$elasticApmSrcDir . DIRECTORY_SEPARATOR . $classSrcFileRelative;

        if (file_exists($classSrcFileAbsolute)) {
            BootstrapStageLogger::logTrace(
                "Before require `$classSrcFileAbsolute' ...",
                __LINE__,
                __FUNCTION__
            );
            require $classSrcFileAbsolute;
            BootstrapStageLogger::logTrace(
                "After require `$classSrcFileAbsolute' ...",
                __LINE__,
                __FUNCTION__
            );
        } else {
            BootstrapStageLogger::logTrace(
                "File with the code for class doesn't exist."
                . " classSrcFile: `$classSrcFileAbsolute'. fqClassName: `$fqClassName'",
                __LINE__,
                __FUNCTION__
            );
        }
    }
}
