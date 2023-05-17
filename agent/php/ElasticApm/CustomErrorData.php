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
 * Data to create custom error event
 *
 * @see  ElasticApm::createCustomError
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L53
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 */
class CustomErrorData
{
    /**
     * @var int|string|null
     *
     * The error code set when the error happened, e.g. database error code
     *
     * The length of a string value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L56
     */
    public $code = null;

    /**
     * @var string|null
     *
     * The original error message
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L61
     */
    public $message = null;

    /**
     * @var string|null
     *
     * Describes the exception type's module namespace
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L65
     */
    public $module = null;

    /**
     * @var string|null
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L80
     */
    public $type = null;
}
