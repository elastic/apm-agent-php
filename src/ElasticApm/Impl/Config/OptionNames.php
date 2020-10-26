<?php

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
    public const ENABLED = 'enabled';
    public const ENVIRONMENT = 'environment';
    public const SECRET_TOKEN = 'secret_token';
    public const SERVER_TIMEOUT = 'server_timeout';
    public const SERVER_URL = 'server_url';
    public const SERVICE_NAME = 'service_name';
    public const SERVICE_VERSION = 'service_version';
    public const TRANSACTION_SAMPLE_RATE = 'transaction_sample_rate';
    public const VERIFY_SERVER_CERT = 'verify_server_cert';
}
