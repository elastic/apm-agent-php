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

namespace ElasticApmTests\ComponentTests\WordPress;

use Elastic\Apm\Impl\Util\ArrayUtil;
use PHPUnit\Framework\Assert;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class WordPressMockWpHook
{
    /** @var array<string, callable|callable-string> */
    private $idToCallback = [];

    public function __construct()
    {
    }

    public function mockImplAddFilter(string $hookName, callable $callback, int $priority): void
    {
        $id = WordPressMockBridge::callWpFilterBuildUniqueId($hookName, $callback, $priority);
        Assert::assertIsString($id);
        $this->idToCallback[$id] = $callback;
    }

    public function mockImplRemoveFilter(string $hookName, callable $callback, int $priority): void
    {
        $id = WordPressMockBridge::callWpFilterBuildUniqueId($hookName, $callback, $priority);
        Assert::assertIsString($id);
        unset($this->idToCallback[$id]);
    }

    public function mockImplIsEmpty(): bool
    {
        return ArrayUtil::isEmpty($this->idToCallback);
    }

    /**
     * @param array<mixed> $args
     *
     * @return mixed
     */
    public function mockApplyFilters(array $args)
    {
        $retVal = null;
        foreach ($this->idToCallback as $callback) {
            $retVal = call_user_func_array($callback, $args);
        }
        return $retVal;
    }
}
