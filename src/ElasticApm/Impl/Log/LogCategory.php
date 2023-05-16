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
final class LogCategory
{
    use StaticClassTrait;

    public const AUTO_INSTRUMENTATION = 'Auto-Instrumentation';
    public const BACKEND_COMM = 'Backend-Comm';
    public const CONFIGURATION = 'Configuration';
    public const DISCOVERY = 'Discovery';
    public const DISTRIBUTED_TRACING = 'Distributed-Tracing';
    public const INFERRED_SPANS = 'Inferred-Spans';
    public const INFRASTRUCTURE = 'Infrastructure';
    public const INTERCEPTION = 'Interception';
    public const PUBLIC_API = 'Public-API';
}
