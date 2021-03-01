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

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LogConsts
{
    use StaticClassTrait;

    public const TYPE_KEY = 'type';

    public const RESOURCE_TYPE_KEY = 'resource_type';
    public const RESOURCE_ID_KEY = 'resource_ID';
    public const RESOURCE_TYPE_VALUE = 'resource';

    public const VALUE_AS_STRING_KEY = 'value_as_string';
    public const VALUE_AS_DEBUG_INFO_KEY = 'value_as_string';

    public const SMALL_LIST_ARRAY_MAX_COUNT = 100;
    public const SMALL_MAP_ARRAY_MAX_COUNT = 100;

    public const LIST_ARRAY_TYPE_VALUE = 'list-array';
    public const MAP_ARRAY_TYPE_VALUE = 'map-array';
    public const ARRAY_COUNT_KEY = 'count';

    public const OBJECT_ID_KEY = 'object_ID';
    public const OBJECT_HASH_KEY = 'object_hash';
}
