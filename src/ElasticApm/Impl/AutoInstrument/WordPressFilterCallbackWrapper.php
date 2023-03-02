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

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WordPressFilterCallbackWrapper
{
    /** @var string */
    private $hookName;

    /** @var mixed */
    private $callback;

    /** @var string */
    private $pluginName;

    /**
     * @param string $hookName
     * @param mixed  $callback
     * @param string $pluginName
     */
    public function __construct(string $hookName, $callback, string $pluginName)
    {
        $this->hookName = $hookName;
        $this->callback = $callback;
        $this->pluginName = $pluginName;
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        // $shouldCreateSpan = false;
        $shouldCreateSpan = true;

        /** @var ?SpanInterface $span */
        $span = null;
        /** @noinspection PhpUnnecessaryLocalVariableInspection, PhpConditionAlreadyCheckedInspection */
        if ($shouldCreateSpan) { // @phpstan-ignore-line
            $name = $this->hookName . ' (' . $this->pluginName . ')';
            $type = 'wordpress_plugin';
            $subtype = $this->pluginName;
            $action = $this->hookName;
            $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);
        }

        try {
            return call_user_func_array($this->callback, func_get_args()); // @phpstan-ignore-line
        } catch (Throwable $throwable) {
            if ($span !== null) {
                $span->createErrorFromThrowable($throwable);
            }
            throw $throwable;
        } finally {
            if ($span !== null) {
                $span->end();
            }
        }
    }
}