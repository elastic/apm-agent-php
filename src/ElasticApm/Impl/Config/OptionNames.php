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

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OptionNames
{
    use StaticClassTrait;

    public const API_KEY = 'api_key';
    public const AST_PROCESS_ENABLED = 'ast_process_enabled';
    public const AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE = 'ast_process_debug_dump_converted_back_to_source';
    public const AST_PROCESS_DEBUG_DUMP_FOR_PATH_PREFIX = 'ast_process_debug_dump_for_path_prefix';
    public const AST_PROCESS_DEBUG_DUMP_OUT_DIR = 'ast_process_debug_dump_out_dir';
    public const ASYNC_BACKEND_COMM = 'async_backend_comm';
    public const BREAKDOWN_METRICS = 'breakdown_metrics';
    public const CAPTURE_ERRORS = 'capture_errors';
    public const DEV_INTERNAL = 'dev_internal';
    public const DISABLE_INSTRUMENTATIONS = 'disable_instrumentations';
    public const DISABLE_SEND = 'disable_send';
    public const ENABLED = 'enabled';
    public const ENVIRONMENT = 'environment';
    public const HOSTNAME = 'hostname';
    public const INTERNAL_CHECKS_LEVEL = 'internal_checks_level';
    public const LOG_LEVEL = 'log_level';
    public const LOG_LEVEL_SYSLOG = 'log_level_syslog';
    public const LOG_LEVEL_STDERR = 'log_level_stderr';
    public const NON_KEYWORD_STRING_MAX_LENGTH = 'non_keyword_string_max_length';
    public const PROFILING_INFERRED_SPANS_ENABLED = 'profiling_inferred_spans_enabled';
    public const PROFILING_INFERRED_SPANS_MIN_DURATION = 'profiling_inferred_spans_min_duration';
    public const PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL = 'profiling_inferred_spans_sampling_interval';
    public const SANITIZE_FIELD_NAMES = 'sanitize_field_names';
    public const SECRET_TOKEN = 'secret_token';
    public const SERVER_TIMEOUT = 'server_timeout';
    public const SERVER_URL = 'server_url';
    public const SERVICE_NAME = 'service_name';
    public const SERVICE_NODE_NAME = 'service_node_name';
    public const SERVICE_VERSION = 'service_version';
    public const SPAN_COMPRESSION_ENABLED = 'span_compression_enabled';
    public const SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION = 'span_compression_exact_match_max_duration';
    public const SPAN_COMPRESSION_SAME_KIND_MAX_DURATION = 'span_compression_same_kind_max_duration';
    public const STACK_TRACE_LIMIT = 'stack_trace_limit';
    public const TRANSACTION_IGNORE_URLS = 'transaction_ignore_urls';
    public const TRANSACTION_MAX_SPANS = 'transaction_max_spans';
    public const TRANSACTION_SAMPLE_RATE = 'transaction_sample_rate';
    public const URL_GROUPS = 'url_groups';
    public const VERIFY_SERVER_CERT = 'verify_server_cert';
}
