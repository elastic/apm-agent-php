<?php

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

    public const TRANSACTION_TYPE_REQUEST = 'request';

    public const SPAN_TYPE_DB = 'db';
    // public const SPAN_TYPE_EXTERNAL = 'external';

    public const SPAN_TYPE_DB_SUBTYPE_SQLITE = 'sqlite';
    // public const SPAN_TYPE_DB_SUBTYPE_MYSQL = 'mysql';
    // public const SPAN_TYPE_DB_SUBTYPE_ORACLE = 'oracle';
    // public const SPAN_TYPE_DB_SUBTYPE_POSTGRESQL = 'postgresql';
    // public const SPAN_TYPE_DB_SUBTYPE_MSSQL = 'mssql';

    // public const SPAN_TYPE_EXTERNAL_SUBTYPE_HTTP = 'http';

    public const SPAN_TYPE_DB_ACTION_EXEC = 'exec';
    public const SPAN_TYPE_DB_ACTION_QUERY = 'query';
}
