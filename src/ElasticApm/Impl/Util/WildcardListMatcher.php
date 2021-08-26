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

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WildcardListMatcher
{
    /** @var WildcardMatcher[] */
    private $matchers;

    /**
     * @param iterable<string> $wildcardExprs
     */
    public function __construct(iterable $wildcardExprs)
    {
        $this->matchers = [];
        foreach ($wildcardExprs as $wildcardExpr) {
            $this->matchers[] = new WildcardMatcher($wildcardExpr);
        }
    }

    public function match(string $text): ?string
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->match($text)) {
                return $matcher->groupName();
            }
        }
        return null;
    }

    public function __toString(): string
    {
        return implode(', ', $this->matchers);
    }
}
