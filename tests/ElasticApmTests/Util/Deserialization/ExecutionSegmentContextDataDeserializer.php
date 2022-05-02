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

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\ExecutionSegmentContextData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataValidator;

final class ExecutionSegmentContextDataDeserializer
{
    use StaticClassTrait;

    /**
     * @param mixed                       $key
     * @param mixed                       $value
     * @param ExecutionSegmentContextData $result
     *
     * @return bool
     */
    public static function deserializeKeyValue($key, $value, ExecutionSegmentContextData $result): bool
    {
        switch ($key) {
            case 'tags':
                $result->labels = DataValidator::validateLabels($value);
                return true;
            default:
                return false;
        }
    }
}
