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

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\InterceptedCallTrackerInterface;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Registration implements LoggableInterface
{
    /** @var callable */
    public $factory;

    /** @var int */
    private $dbgPluginIndex;

    /** @var string */
    private $dbgPluginDesc;

    /** @var string */
    private $dbgInterceptedCallDesc;

    /**
     * @param int      $dbgPluginIndex
     * @param string   $dbgPluginDesc
     * @param string   $dbgInterceptedCallDesc
     * @param callable $interceptedCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedCallTrackerInterface $interceptedCallTrackerFactory
     *
     * @see           InterceptedCallTrackerInterface
     */
    public function __construct(
        int $dbgPluginIndex,
        string $dbgPluginDesc,
        string $dbgInterceptedCallDesc,
        callable $interceptedCallTrackerFactory
    ) {
        $this->dbgPluginIndex = $dbgPluginIndex;
        $this->dbgPluginDesc = $dbgPluginDesc;
        $this->dbgInterceptedCallDesc = $dbgInterceptedCallDesc;
        $this->factory = $interceptedCallTrackerFactory;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(
            [
                'interceptedCallDescription' => $this->dbgInterceptedCallDesc,
                'plugin'                     => [
                    'index'       => $this->dbgPluginIndex,
                    'description' => $this->dbgPluginDesc,
                ],
            ]
        );
    }
}
