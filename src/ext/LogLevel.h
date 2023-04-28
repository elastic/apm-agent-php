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

#pragma once

/**
 * The order is important because lower numeric values are considered contained in higher ones
 * for example logLevel_error means that both logLevel_error and logLevel_critical is enabled.
 */
enum LogLevel
{
    /**
     * logLevel_not_set should not be used by logging statements - it is used only in configuration.
     */
    logLevel_not_set = -1,

    /**
     * logLevel_off should not be used by logging statements - it is used only in configuration.
     */
    logLevel_off = 0,

    logLevel_critical,
    logLevel_error,
    logLevel_warning,
    logLevel_info,
    logLevel_debug,
    logLevel_trace,

    numberOfLogLevels
};
typedef enum LogLevel LogLevel;
