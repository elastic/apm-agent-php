/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#pragma once

#include "basic_types.h"

struct CpuMetricsReading
{
    unsigned long long user;
    unsigned long long nice;
    unsigned long long system;
    unsigned long long idle;
};
typedef struct CpuMetricsReading CpuMetricsReading;

struct SystemMetricsReading
{
    CpuMetricsReading processCpuReading;
    CpuMetricsReading machineCpuReading;
};
typedef struct SystemMetricsReading SystemMetricsReading;

struct SystemMetrics
{
    double machineCpu;
    double processCpu;

    UInt64 machineMemoryFree;
    UInt64 machineMemoryTotal;
    UInt64 processMemorySize;
    UInt64 processMemoryRss;
};
typedef struct SystemMetrics SystemMetrics;

void readSystemMetrics( SystemMetricsReading* systemMetricsReading );

void getSystemMetrics( const SystemMetricsReading* startReading, const SystemMetricsReading* endReading, SystemMetrics* result );
