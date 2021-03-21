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

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Constants
{
    public const KEYWORD_STRING_MAX_LENGTH = 1024;

    public const NON_KEYWORD_STRING_MAX_LENGTH = 10 * 1024;

    public const EXECUTION_SEGMENT_ID_SIZE_IN_BYTES = 8;
    public const TRACE_ID_SIZE_IN_BYTES = 16;
    public const ERROR_ID_SIZE_IN_BYTES = 16;

    public const TRANSACTION_TYPE_REQUEST = 'request';
    public const TRANSACTION_TYPE_CLI = 'cli';

    public const SPAN_TYPE_DB = 'db';
    public const SPAN_TYPE_EXTERNAL = 'external';

    public const SPAN_TYPE_DB_SUBTYPE_SQLITE = 'sqlite';
    public const SPAN_TYPE_DB_SUBTYPE_MYSQL = 'mysql';
    // public const SPAN_TYPE_DB_SUBTYPE_ORACLE = 'oracle';
    // public const SPAN_TYPE_DB_SUBTYPE_POSTGRESQL = 'postgresql';
    // public const SPAN_TYPE_DB_SUBTYPE_MSSQL = 'mssql';

    public const SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP = 'http';

    public const SPAN_TYPE_DB_ACTION_EXEC = 'exec';
    public const SPAN_TYPE_DB_ACTION_QUERY = 'query';
}
