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

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Tracer;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionParser<array<string|bool|int|float|null>>
 */
final class LabelsOptionParser extends OptionParser
{
    /**
     * @param string $valueAsString
     *
     * @return string|bool|int|float|null
     */
    private static function parseValue(string $valueAsString)
    {
        if ($valueAsString === 'true') {
            return true;
        }
        if ($valueAsString === 'false') {
            return false;
        }

        if (filter_var($valueAsString, FILTER_VALIDATE_INT) !== false) {
            return intval($valueAsString);
        }

        if (filter_var($valueAsString, FILTER_VALIDATE_FLOAT) !== false) {
            return floatval($valueAsString);
        }

        if ($valueAsString === 'null') {
            return null;
        }

        return Tracer::limitKeywordString($valueAsString);
    }

    /**
     * @param string $rawValue
     *
     * @return array<string|bool|int|float|null>
     */
    public function parse(string $rawValue): array
    {
        // Value format:
        //                  key=value[,key=value[,...]]

        $result = [];
        foreach ((new KeyValuePairsOptionParser())->parse($rawValue) as $key => $valueAsString) {
            $result[$key] = self::parseValue($valueAsString);
        }
        return $result;
    }
}
