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

#include "SystemMetrics.h"
#ifndef PHP_WIN32
#   include <stdlib.h>
#   include <stdio.h>
#   include <sys/sysinfo.h>
#endif
#include <php.h>

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_SYS_METRICS

#ifdef PHP_WIN32

static void fillDummyCpuReadingOnWindows( CpuMetricsReading* result )
{
    static UInt64 counter = 0;
    result->user = ++counter;
    result->nice = ++counter;
    result->system = ++counter;
    result->idle = ++counter;
}

#else

static void readCpuFromProcStat( const char* procStatFilePath, CpuMetricsReading* result )
{
    FILE* proc_stat_file = fopen(procStatFilePath, "r");
    /*
     * http://www.linuxhowtos.org/System/procstat.htm
     *
     * > cat /proc/stat
     *  cpu  2255 34 2290 22625563 6290 127 456
     *
     * These numbers identify the amount of time the CPU has spent performing different kinds of work.
     * Time units are in USER_HZ or Jiffies (typically hundredths of a second).
     * The meanings of the columns are as follows, from left to right:
     *      user: normal processes executing in user mode
     *      nice: niced processes executing in user mode
     *      system: processes executing in kernel mode
     *      idle: twiddling thumbs
     */
    int n = fscanf(proc_stat_file, "cpu %llu %llu %llu %llu", &result->user, &result->nice, &result->system, &result->idle);
    fclose(proc_stat_file);
}

#endif // #ifdef PHP_WIN32

void readSystemMetrics( SystemMetricsReading* systemMetricsReading )
{
#ifdef PHP_WIN32
    fillDummyCpuReadingOnWindows( &systemMetricsReading->machineCpuReading );
    fillDummyCpuReadingOnWindows( &systemMetricsReading->processCpuReading );
#else
    readCpuFromProcStat( "/proc/stat", &systemMetricsReading->machineCpuReading );
    readCpuFromProcStat( "/proc/self/stat", &systemMetricsReading->processCpuReading );
#endif
}

double calcCpuPercent( const CpuMetricsReading* startReading, const CpuMetricsReading* endReading )
{
    if ( endReading->user < startReading->user
         || endReading->nice < startReading->nice
         || endReading->system < startReading->system
         || endReading->idle < startReading->idle )
    {
        //Overflow detection. Just skip this value.
        return -1.0;
    }
    uint64_t busy = ( endReading->user - startReading->user ) + ( endReading->nice - startReading->nice ) + ( endReading->system - startReading->system );
    uint64_t total = busy + ( endReading->idle - startReading->idle );
    return total == 0 ? 0 : ( (double) busy ) / total;
}

void getSystemMetrics( const SystemMetricsReading* startReading, const SystemMetricsReading* endReading, SystemMetrics* result )
{
    result->machineCpu = calcCpuPercent( &startReading->machineCpuReading, &endReading->machineCpuReading );
    result->processCpu = calcCpuPercent( &startReading->processCpuReading, &endReading->processCpuReading );

#ifdef PHP_WIN32
    result->machineMemoryTotal = 4ULL * 1024 * 1024 * 1024;
    result->machineMemoryFree = 1ULL * 1024 * 1024 * 1024;
#else
    struct sysinfo sysInfo;
    sysinfo( &sysInfo );
    result->machineMemoryTotal = sysInfo.totalram;
    result->machineMemoryFree = sysInfo.freeram;
#endif

    result->processMemorySize = zend_memory_peak_usage( /* real_usage (bool): */ 0 );
    result->processMemoryRss = zend_memory_peak_usage( /* real_usage (bool): */ 1 );
}
