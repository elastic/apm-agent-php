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

use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanExpectationsBuilder;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class WordPressSpanExpectationsBuilder extends SpanExpectationsBuilder
{
    private const EXPECTED_SPAN_TYPE_FOR_CORE = 'wordpress_core';
    private const EXPECTED_SPAN_TYPE_FOR_PLUGIN = 'wordpress_plugin';
    private const EXPECTED_SPAN_TYPE_FOR_THEME = 'wordpress_theme';

    private function forAddonFilterCallback(string $hookName, string $addonGroup, string $addonName): SpanExpectations
    {
        /**
         * @see WordPressFilterCallbackWrapper::__invoke
         */
        $result = $this->startNew();
        $result->name->setValue($hookName . ' - ' . $addonName);
        $result->type->setValue($addonGroup);
        $result->subtype->setValue($addonName);
        $result->action->setValue($hookName);
        return $result;
    }

    public function forPluginFilterCallback(string $hookName, string $pluginName): SpanExpectations
    {
        return $this->forAddonFilterCallback($hookName, /* addonGroup */ self::EXPECTED_SPAN_TYPE_FOR_PLUGIN, $pluginName);
    }

    public function forThemeFilterCallback(string $hookName, string $themeName): SpanExpectations
    {
        return $this->forAddonFilterCallback($hookName, /* addonGroup */ self::EXPECTED_SPAN_TYPE_FOR_THEME, $themeName);
    }

    public function forCoreFilterCallback(string $hookName): SpanExpectations
    {
        /**
         * @see WordPressFilterCallbackWrapper::__invoke
         */
        $result = $this->startNew();
        $result->name->setValue($hookName . ' - WordPress core');
        $result->type->setValue(self::EXPECTED_SPAN_TYPE_FOR_CORE);
        $result->subtype->setValue(null);
        $result->action->setValue($hookName);
        return $result;
    }
}
