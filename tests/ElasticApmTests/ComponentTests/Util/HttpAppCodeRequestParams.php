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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\UrlParts;

final class HttpAppCodeRequestParams extends AppCodeRequestParams
{
    /** @var string */
    public $httpRequestMethod = 'GET';

    /** @var UrlParts */
    public $urlParts;

    /** @var ?int */
    public $expectedHttpResponseStatusCode = HttpConstantsForTests::STATUS_OK;

    public function __construct(HttpServerHandle $httpServerHandle, AppCodeTarget $appCodeTarget)
    {
        parent::__construct($appCodeTarget);

        $this->urlParts = new UrlParts();
        $this->urlParts->scheme('http')
                       ->host(HttpServerHandle::DEFAULT_HOST)
                       ->port($httpServerHandle->getMainPort())
                       ->path('/');
    }
}
