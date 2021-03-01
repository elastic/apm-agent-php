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

namespace Elastic\Apm;

/**
 * This interface has functionality shared between Transaction and Span contexts'.
 */
interface ExecutionSegmentContextInterface
{
    /**
     * @param string                     $key
     * @param string|bool|int|float|null $value
     *
     * Labels is a flat mapping of user-defined labels with string keys and null, string, boolean or number values.
     *
     * The length of a key and a string value is limited to 1024.
     *
     * @return void
     *
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L40
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json#L46
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L88
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/tags.json
     */
    public function setLabel(string $key, $value): void;
}
