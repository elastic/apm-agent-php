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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionParser<array<string>>
 */
final class KeyValuePairsOptionParser extends OptionParser
{
    /**
     * @param string $rawValue
     *
     * @return array<string>
     */
    public function parse(string $rawValue): array
    {
        // Value format:
        //                  key=value[,key=value[,...]]

        // Treat empty string as zero key-value pairs
        if (TextUtil::isEmptyString($rawValue)) {
            return [];
        }

        $pairs = explode(',', $rawValue);
        $result = [];
        foreach ($pairs as $keyValuePair) {
            $keyValueSeparatorPos = strpos($keyValuePair, '=');
            if ($keyValueSeparatorPos === false) {
                throw new ParseException('One of key-value pairs is missing key-value separator' . ' ;' . LoggableToString::convert(['keyValuePair' => $keyValuePair, 'rawValue' => $rawValue]));
            }
            $key = trim(substr($keyValuePair, /* offset */ 0, /* length */ $keyValueSeparatorPos));
            $value = ($keyValueSeparatorPos === (strlen($keyValuePair) - 1)) ? '' : trim(substr($keyValuePair, /* offset */ $keyValueSeparatorPos + 1));
            if (array_key_exists($key, $result)) {
                throw new ParseException(
                    'Key is present more than once'
                    . ' ;' . LoggableToString::convert(['key' => $key, '1st value' => $result[$key], '2nd value' => $value, '2nd keyValuePair' => $keyValuePair, 'rawValue' => $rawValue])
                );
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
