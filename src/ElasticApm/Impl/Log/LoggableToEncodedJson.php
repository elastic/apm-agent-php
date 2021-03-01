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

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Exception;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToEncodedJson
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     * @param bool  $prettyPrint
     * @param int   $lengthLimit
     *
     * @return string
     */
    public static function convert(
        $value,
        bool $prettyPrint = false,
        /** @noinspection PhpUnusedParameterInspection */ int $lengthLimit = LoggableToString::DEFAULT_LENGTH_LIMIT
    ): string {
        try {
            $jsonEncodable = LoggableToJsonEncodable::convert($value);
        } catch (Exception $ex) {
            return LoggingSubsystem::onInternalFailure(
                'LoggableToJsonEncodable::convert() failed',
                ['value type' => DbgUtil::getType($value)],
                $ex
            );
        }

        try {
            return JsonUtil::encode($jsonEncodable, $prettyPrint);
        } catch (Exception $ex) {
            return LoggingSubsystem::onInternalFailure(
                'JsonUtil::encode() failed',
                ['$jsonEncodable type' => DbgUtil::getType($jsonEncodable)],
                $ex
            );
        }
    }
}
