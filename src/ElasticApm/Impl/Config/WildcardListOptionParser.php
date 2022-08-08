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

use Elastic\Apm\Impl\Util\WildcardListMatcher;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionParser<WildcardListMatcher>
 */
final class WildcardListOptionParser extends OptionParser
{
    public function parse(string $rawValue): WildcardListMatcher
    {
        return self::parseImpl($rawValue);
    }

    public static function parseImpl(string $rawValue): WildcardListMatcher
    {
        /**
         * @return iterable<string>
         */
        $splitWildcardExpr = function () use ($rawValue): iterable {
            foreach (explode(',', $rawValue) as $listElementRaw) {
                yield trim($listElementRaw);
            }
        };

        return new WildcardListMatcher($splitWildcardExpr());
    }
}
