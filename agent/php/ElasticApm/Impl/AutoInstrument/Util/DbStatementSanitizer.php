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

namespace Elastic\Apm\Impl\AutoInstrument\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DbStatementSanitizer
{
    use StaticClassTrait;

    /**
     * One alternative per line, in this order: single-quoted string, double-quoted string,
     * line comment, hash comment, block comment, numeric literal.
     * Quoted strings may be unterminated. Possessive quantifiers keep the match linear on long literals.
     * The x modifier ignores the line breaks, so `\#` escapes the hash comment marker.
     */
    private const LITERALS_REGEX = <<<'REGEX'
~
'(?:[^'\\]++|\\[\s\S]|'')*+(?:'|\\?$)
|"(?:[^"\\]++|\\[\s\S]|"")*+(?:"|\\?$)
|--[^\n]*
|\#[^\n]*
|/\*[\s\S]*?\*/
|(?<![\w.`:@$])(?:0[xX][0-9a-fA-F]+|0[bB][01]+|\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|\.\d+(?:[eE][+-]?\d+)?)(?![\w`])
~x
REGEX;

    /**
     * Replaces every literal in the statement with a question mark.
     *
     * @return ?string The sanitized statement or null if the regular expression engine failed.
     */
    public static function sanitize(string $statement): ?string
    {
        return preg_replace(self::LITERALS_REGEX, '?', $statement);
    }
}
