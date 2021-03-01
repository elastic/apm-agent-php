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

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AppCodeHostKind
{
    use StaticClassTrait;

    public const NOT_SET = 0;
    public const CLI_SCRIPT = self::NOT_SET + 1;
    public const CLI_BUILTIN_HTTP_SERVER = self::CLI_SCRIPT + 1;
    public const EXTERNAL_HTTP_SERVER = self::CLI_BUILTIN_HTTP_SERVER + 1;

    public static function toString(int $intValue): string
    {
        /** @var array<int, string> */
        $intToStringMap = [
            AppCodeHostKind::NOT_SET => 'NOT_SET',
            AppCodeHostKind::CLI_SCRIPT => 'CLI_script',
            AppCodeHostKind::CLI_BUILTIN_HTTP_SERVER => 'CLI_builtin_HTTP_server',
            AppCodeHostKind::EXTERNAL_HTTP_SERVER    => 'external_HTTP_server'
        ];

        return ArrayUtil::getValueIfKeyExistsElse($intValue, $intToStringMap, null) ?? "UNKNOWN ($intValue)";
    }
}
