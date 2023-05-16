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

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class SinkBase implements SinkInterface
{
    /** @inheritDoc */
    public function consume(
        int $statementLevel,
        string $message,
        array $contextsStack,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        ?bool $includeStacktrace,
        int $numberOfStackFramesToSkip
    ): void {
        $combinedContext = [];

        // Traverse $contextsStack in reverse order since the data most specific to the log statement is on top
        for (end($contextsStack); key($contextsStack) !== null; prev($contextsStack)) {
            /** @var array<string, mixed> $currentContext */
            $currentContext = current($contextsStack);
            foreach ($currentContext as $key => $value) {
                $combinedContext[$key] = $value;
            }
        }

        if ($includeStacktrace === null ? ($statementLevel <= Level::ERROR) : $includeStacktrace) {
            $combinedContext[LoggableStackTrace::STACK_TRACE_KEY]
                = LoggableStackTrace::buildForCurrent($numberOfStackFramesToSkip + 1);
        }

        $ctxAsStr = LoggableToString::convert($combinedContext);
        $msgCtxSeparator = (TextUtil::isEmptyString($message) || TextUtil::isEmptyString($ctxAsStr)) ? '' : ' ';
        $messageWithContext = $message . $msgCtxSeparator . $ctxAsStr;

        $this->consumePreformatted(
            $statementLevel,
            $category,
            $srcCodeFile,
            $srcCodeLine,
            $srcCodeFunc,
            $messageWithContext
        );
    }

    /**
     * @param int    $statementLevel
     * @param string $category
     * @param string $srcCodeFile
     * @param int    $srcCodeLine
     * @param string $srcCodeFunc
     * @param string $messageWithContext
     */
    abstract protected function consumePreformatted(
        int $statementLevel,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        string $messageWithContext
    ): void;
}
