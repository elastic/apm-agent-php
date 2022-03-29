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

/**
 * Get the first key of the given array without affecting the internal array pointer.
 *
 * @link  https://secure.php.net/array_key_first
 *
 * @param array<mixed> $arr
 *
 * @return string|int|null Returns the first key of array if the array is not empty; NULL otherwise.
 */
function array_key_first(array $arr)
{
    foreach ($arr as $key => $unused) {
        return $key;
    }
    return null;
}
