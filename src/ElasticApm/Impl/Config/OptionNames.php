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
    public const BREAKDOWN_METRICS = 'breakdown_metrics';
    public const DISABLE_SEND = 'disable_send';
    public const ENABLED = 'enabled';
    public const ENVIRONMENT = 'environment';
    public const HOSTNAME = 'hostname';
    public const LOG_LEVEL = 'log_level';
    public const LOG_LEVEL_SYSLOG = 'log_level_syslog';
    public const LOG_LEVEL_STDERR = 'log_level_stderr';
    public const SECRET_TOKEN = 'secret_token';
    public const SERVER_TIMEOUT = 'server_timeout';
    public const SERVER_URL = 'server_url';
    public const SERVICE_NAME = 'service_name';
    public const SERVICE_NODE_NAME = 'service_node_name';
    public const SERVICE_VERSION = 'service_version';
    public const TRANSACTION_IGNORE_URLS = 'transaction_ignore_urls';
    public const TRANSACTION_MAX_SPANS = 'transaction_max_spans';
    public const TRANSACTION_SAMPLE_RATE = 'transaction_sample_rate';
    public const URL_GROUPS = 'url_groups';
    public const VERIFY_SERVER_CERT = 'verify_server_cert';
}
