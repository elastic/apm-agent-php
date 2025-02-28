---
navigation_title: "Elastic APM PHP Agent"
mapped_pages:
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.13.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.12.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.11.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.10.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.9.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.9.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.8.4.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.8.3.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.8.2.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.8.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.8.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.7.2.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.7.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.7.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.6.2.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.6.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.6.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.5.2.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.5.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.5.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.4.2.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.4.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.4.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.3.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.3.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.2.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.0.1.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.0.html
  - https://www.elastic.co/guide/en/apm/agent/php/current/release-notes-v1.0.0-beta1.html
---

# Elastic APM PHP Agent release notes [elastic-apm-php-agent-release-notes]

Review the changes, fixes, and more in each version of Elastic APM PHP Agent.

To check for security updates, go to [Security announcements for the Elastic stack](https://discuss.elastic.co/c/announcements/security-announcements/31).

% Release notes includes only features, enhancements, and fixes. For breaking changes, deprecations, and known issues, create a separate page and at it to the applicable sections.

% ## version.next [elastic-apm-php-agent-versionext-release-notes]
% **Release date:** Month day, year

% ### Features and enhancements [elastic-apm-php-agent-versionext-features-enhancements]

% ### Fixes [elastic-apm-php-agent-versionext-fixes]

## 1.15.0 [elastic-apm-php-agent-1150-release-notes]
**Release date:** January 17, 2025

### Features and enhancements [elastic-apm-php-agent-1150-features-enhancements]
* Add php 8.4 compatibility #1255
* Added warning log for Xdebug incompatibility [#1256](https://github.com/elastic/apm-agent-php/pull/1256) and [#1257](https://github.com/elastic/apm-agent-php/pull/1257)

## 1.14.1 [elastic-apm-php-agent-1141-release-notes]
**Release date:** January 5, 2025

### Fixes [elastic-apm-php-agent-1141-fixes]
* Fixed calling post hook if instrumented functions throws [#1223](https://github.com/elastic/apm-agent-php/pull/1223)

## 1.14.0 [elastic-apm-php-agent-1140-release-notes]
**Release date:** August 28, 2024

### Features and enhancements [elastic-apm-php-agent-1140-features-enhancements]
* Experimental support for ARM64 architecture

### Fixes [elastic-apm-php-agent-1140-fixes]
* Fixed issue causing forked process to hang or crash [#1213](https://github.com/elastic/apm-agent-php/pull/1213)

## 1.13.2 [elastic-apm-php-agent-1132-release-notes]
**Release date:** August 20, 2024

### Fixes [elastic-apm-php-agent-1132-fixes]
* FSet various PHP engine hooks only when the relevant feature is enabled [#1211](https://github.com/elastic/apm-agent-php/pull/1211)

## 1.13.1 [elastic-apm-php-agent-1131-release-notes]
**Release date:** July 23, 2024

### Features and enhancements [elastic-apm-php-agent-1131-features-enhancements]
* Fixed memory leak in exception handling [#1174](https://github.com/elastic/apm-agent-php/pull/1174)
* Changed exprerimental part of WordPress instrumentation that is measuring latency impact by plugin to be disabled by default [#1181](https://github.com/elastic/apm-agent-php/pull/1181)

## 1.13.0 [elastic-apm-php-agent-1130-release-notes]
**Release date:** January 31, 2024

### Features and enhancements [elastic-apm-php-agent-1130-features-enhancements]
* Added support for PHP 8.3 [#1127](https://github.com/elastic/apm-agent-php/pull/1127)

### Fixes [elastic-apm-php-agent-1130-fixes]
* Fixed resetting state for forks [#1125](https://github.com/elastic/apm-agent-php/pull/1125)

## 1.12.0 [elastic-apm-php-agent-1120-release-notes]
**Release date:** January 15, 2024

### Features and enhancements [elastic-apm-php-agent-1120-features-enhancements]
* Removed limitation that required to reinstall the agent after PHP upgrade [#1115](https://github.com/elastic/apm-agent-php/pull/#1115)
* Fixed "malformed UTF-8 characters" issue [#1120](https://github.com/elastic/apm-agent-php/pull/#1120)

## 1.11.0 [elastic-apm-php-agent-1110-release-notes]
**Release date:** January 4, 2024

### Features and enhancements [elastic-apm-php-agent-1110-features-enhancements]
* Debug option to collect diagnostic information from PHP worker process [#1065](https://github.com/elastic/apm-agent-php/pull/#1065)
* Enable background (non-blocking) communication with APM Server for any SAPI by default [#1079](https://github.com/elastic/apm-agent-php/pull/#1079)
* Sending metadata.system.architecture and platform [#1083](https://github.com/elastic/apm-agent-php/pull/#1083)

### Fixes [elastic-apm-php-agent-1110-fixes]
* Improved packaging script to support other architectures and fixed package naming for x86-64 architecture  [#1067](https://github.com/elastic/apm-agent-php/pull/#1067)
* Fixed exception handling by improving memory allocation and proper exception object copy [#1076](https://github.com/elastic/apm-agent-php/pull/#1076)
* Fixed building of APM server connection string [#1080](https://github.com/elastic/apm-agent-php/pull/#1080)
* Allow using environment variables passed via FastCGI for agent configuration [#1113](https://github.com/elastic/apm-agent-php/pull/#1113)

## 1.10.0 [elastic-apm-php-agent-1110-release-notes]
**Release date:** September 12, 2023

### Features and enhancements [elastic-apm-php-agent-1100-features-enhancements]
* Preview of inferred spans feature. It needs to be enabled manually, please refer to documentation [`profiling_inferred_spans_enabled`](/reference/configuration-reference.md#config-profiling-inferred-spans-enabled) [#1038](https://github.com/elastic/apm-agent-php/pull/#1038)

### Fixes [elastic-apm-php-agent-1100-fixes]
* Detection and logging if agent source code doesn’t comply open_basedir limitation [#1044](https://github.com/elastic/apm-agent-php/pull/#1044)

## 1.9.1 [elastic-apm-php-agent-191-release-notes]
**Release date:** July 6, 2023

### Features and enhancements [elastic-apm-php-agent-191-features-enhancements]
* Added configuration option: GLOBAL_LABELS [#1007](https://github.com/elastic/apm-agent-php/pull/#1007)
* Introduced new C++ build environment [#985](https://github.com/elastic/apm-agent-php/pull/#985)

### Fixes [elastic-apm-php-agent-191-fixes]
* Suppress errors and warnings when internally calling opcache_get_status [#1013](https://github.com/elastic/apm-agent-php/pull/#1013)

## 1.9.0 [elastic-apm-php-agent-190-release-notes]
**Release date:** June 22, 2023

### Features and enhancements [elastic-apm-php-agent-190-features-enhancements]
* Added container ID detection [#966](https://github.com/elastic/apm-agent-php/pull/966)
* Extended span compression support to spans without service target [#944](https://github.com/elastic/apm-agent-php/pull/944)
* Added auto-instrumentation for capturing WordPress filters/actions callbacks and the active theme [#948](https://github.com/elastic/apm-agent-php/pull/948)
* Added configuration option STACK_TRACE_LIMIT [#995](https://github.com/elastic/apm-agent-php/pull/995)
* Added SPAN_STACK_TRACE_MIN_DURATION configuration option [#996](https://github.com/elastic/apm-agent-php/pull/996)
* Implemented backoff on failure in communication to APM Server [#999](https://github.com/elastic/apm-agent-php/pull/999)

### Fixes [elastic-apm-php-agent-190-fixes]
* Fixed not cleaning up connection data in sync backend comm. mode [#957](https://github.com/elastic/apm-agent-php/pull/957)
* Fixed crash when [`opcache_reset()`](https://www.php.net/manual/en/function.opcache-reset.php) is used [#1000](https://github.com/elastic/apm-agent-php/pull/1000)

## 1.8.4 [elastic-apm-php-agent-184-release-notes]
**Release date:** May 17, 2023

### Fixes [elastic-apm-php-agent-184-fixes]
* Fixed deadlock caused by use of pthread_atfork [#964](https://github.com/elastic/apm-agent-php/pull/964)
* Fixed verify_server_cert=false not disabling all the checks related to HTTPS certificate [#965](https://github.com/elastic/apm-agent-php/pull/965)
* Fixed not joining background sender thread if there was fork after module init [#959](https://github.com/elastic/apm-agent-php/pull/959)

## 1.8.3 [elastic-apm-php-agent-183-release-notes]
**Release date:** April 21, 2023

### Fixes [elastic-apm-php-agent-183-fixes]
* Fixed issue with missing transaction details on some setups [#916](https://github.com/elastic/apm-agent-php/pull/916)

## 1.8.2 [elastic-apm-php-agent-182-release-notes]
**Release date:** April 12, 2023

### Fixes [elastic-apm-php-agent-182-fixes]
* Fixed agent issue causing PHP to crash when opcache preload feature was enabled [#913](https://github.com/elastic/apm-agent-php/pull/913)

## 1.8.1 [elastic-apm-php-agent-181-release-notes]
**Release date:** March 9, 2023

### Fixes [elastic-apm-php-agent-181-fixes]
* Fix for the agent causing very high CPU usage because it’s creating frequent connections to Elastic APM Server [#877](https://github.com/elastic/apm-agent-php/pull/877)

## 1.8.0 [elastic-apm-php-agent-180-release-notes]
**Release date:** February 27, 2023

### Features and enhancements [elastic-apm-php-agent-180-features-enhancements]
* Added support for PHP 8.2 [#868](https://github.com/elastic/apm-agent-php/pull/868)

## 1.7.2 [elastic-apm-php-agent-172-release-notes]
**Release date:** February 24, 2023

### Fixes [elastic-apm-php-agent-172-fixes]
* Fixed: case when process fork happens during request processing [#857](https://github.com/elastic/apm-agent-php/pull/857)

## 1.7.1 [elastic-apm-php-agent-171-release-notes]
**Release date:** January 16, 2023

### Fixes [elastic-apm-php-agent-171-fixes]
* Fixed: php apm segfaults on a zend error/php warning [#834](https://github.com/elastic/apm-agent-php/pull/834)

## 1.7.0 [elastic-apm-php-agent-170-release-notes]
**Release date:** October 13, 2022

### Features and enhancements [elastic-apm-php-agent-170-features-enhancements]
* Added support for automatically capturing MySQLi: [#688](https://github.com/elastic/apm-agent-php/pull/688)

### Fixes [elastic-apm-php-agent-170-fixes]
* Fixed: Inferred spans when used with Laravel: [#796](https://github.com/elastic/apm-agent-php/pull/796)
* Fixed: CustomErrorData not found issue: [#797](https://github.com/elastic/apm-agent-php/pull/797)

## 1.6.2 [elastic-apm-php-agent-162-release-notes]
**Release date:** November 17, 2022

### Features and enhancements [elastic-apm-php-agent-162-features-enhancements]
* Backported support for automatically capturing MySQLi: [#688](https://github.com/elastic/apm-agent-php/pull/688)

## 1.6.1 [elastic-apm-php-agent-161-release-notes]
**Release date:** September 12, 2022

### Fixes [elastic-apm-php-agent-161-fixes]
* Fixed: Current implementation for Improved Granularity for SQL Databases doesn’t account for SQL USE statement: [#759](https://github.com/elastic/apm-agent-php/pull/759)

## 1.6.0 [elastic-apm-php-agent-160-release-notes]
**Release date:** August 22, 2022

### Features and enhancements [elastic-apm-php-agent-160-features-enhancements]
* Added inferred spans to automatically detect slow functions (as an experimental feature disabled by default): [#731](https://github.com/elastic/apm-agent-php/pull/731)
* Improved granularity for SQL databases: [#732](https://github.com/elastic/apm-agent-php/pull/732)
* Implemented default type for transactions and spans: [#733](https://github.com/elastic/apm-agent-php/pull/733)
* Implemented support for Dependencies table: [#748](https://github.com/elastic/apm-agent-php/pull/748)
* Improved transaction name for Laravel’s `artisan` command - now includes the first argument: [#714](https://github.com/elastic/apm-agent-php/pull/714)

## 1.5.2 [elastic-apm-php-agent-152-release-notes]
**Release date:** June 20, 2022

### Fixes [elastic-apm-php-agent-152-fixes]
* Fixed bug: Agent destroys error code for curl calls: [#707](https://github.com/elastic/apm-agent-php/pull/707)

## 1.5.1 [elastic-apm-php-agent-151-release-notes]
**Release date:** May 30, 2022

### Fixes [elastic-apm-php-agent-151-fixes]
* Fixed bug: Forked process runs indefinitely: [#691](https://github.com/elastic/apm-agent-php/pull/691)

## 1.5.0 [elastic-apm-php-agent-150-release-notes]
**Release date:** March 29, 2022

### Features and enhancements [elastic-apm-php-agent-150-features-enhancements]
* Added support for PHP 8.1: [#604](https://github.com/elastic/apm-agent-php/pull/604)

## 1.4.2 [elastic-apm-php-agent-142-release-notes]
**Release date:** February 17, 2022

### Features and enhancements [elastic-apm-php-agent-142-features-enhancements]
* Create error events only for PHP error types included in [`error_reporting()`](https://www.php.net/manual/en/function.error-reporting.php): [#625](https://github.com/elastic/apm-agent-php/pull/625)

## 1.4.1 [elastic-apm-php-agent-141-release-notes]
**Release date:** February 14, 2022

### Fixes [elastic-apm-php-agent-141-fixes]
* Fixed error events not being created for PHP errors: [#619](https://github.com/elastic/apm-agent-php/pull/619)

## 1.4.0 [elastic-apm-php-agent-140-release-notes]
**Release date:** January 10, 2022

### Features and enhancements [elastic-apm-php-agent-140-features-enhancements]
* Background (non-blocking) communication with APM Server: [#584](https://github.com/elastic/apm-agent-php/pull/584)

## 1.3.1 [elastic-apm-php-agent-131-release-notes]
**Release date:** October 18, 2021

### Features and enhancements [elastic-apm-php-agent-131-features-enhancements]
* DISABLE_SEND configuration option: [#559](https://github.com/elastic/apm-agent-php/pull/559)
* DISABLE_INSTRUMENTATIONS configuration option: [#565](https://github.com/elastic/apm-agent-php/pull/565)
* DEV_INTERNAL configuration option: [#566](https://github.com/elastic/apm-agent-php/pull/566)

## 1.3.0 [elastic-apm-php-agent-130-release-notes]
**Release date:** September 1, 2021

### Features and enhancements [elastic-apm-php-agent-130-features-enhancements]
* SERVICE_NODE_NAME configuration option: [#458](https://github.com/elastic/apm-agent-php/pull/458)
* URL_GROUPS configuration option: [#537](https://github.com/elastic/apm-agent-php/pull/537)

## 1.2.0 [elastic-apm-php-agent-120-release-notes]
**Release date:** June 29, 2021

### Features and enhancements [elastic-apm-php-agent-120-features-enhancements]
* Collecting data for `Error rate` chart: [#441](https://github.com/elastic/apm-agent-php/pull/441)
* HOSTNAME configuration option: [#440](https://github.com/elastic/apm-agent-php/pull/440)
* Collecting data for `Time spent by span type` chart: [#436](https://github.com/elastic/apm-agent-php/pull/436)
* `ensureParentId()` API: [#431](https://github.com/elastic/apm-agent-php/pull/431)

### Fixes [elastic-apm-php-agent-120-fixes]
* Fixed missing subtype and action for DB spans and DB not showing on `Service Map`: [#443](https://github.com/elastic/apm-agent-php/pull/443)

## 1.1.0 [elastic-apm-php-agent-110-release-notes]
**Release date:** June 1, 2021

### Features and enhancements [elastic-apm-php-agent-110-features-enhancements]
* Support for PHP 8.0: [#365](https://github.com/elastic/apm-agent-php/pull/365)
* Support for Central (AKA Remote) Agents Configuration [#134](https://github.com/elastic/apm-agent-php/pull/134)

## 1.0.1 [elastic-apm-php-agent-101-release-notes]
**Release date:** April 1, 2021

### Fixes [elastic-apm-php-agent-101-fixes]
* Fixed missing query string: [#390](https://github.com/elastic/apm-agent-php/pull/390)
* Fixed $_SERVER not set when auto_globals_jit = On: [#392](https://github.com/elastic/apm-agent-php/pull/392)

## 1.0.0 [elastic-apm-php-agent-100-release-notes]
**Release date:** March 23, 2021

### Features and enhancements [elastic-apm-php-agent-100-features-enhancements]
* Added support for distributed tracing: [#283](https://github.com/elastic/apm-agent-php/pull/283)
* Added Error events: [#282](https://github.com/elastic/apm-agent-php/pull/282)
* Add support for TRANSACTION_MAX_SPANS configuration option : [#260](https://github.com/elastic/apm-agent-php/pull/260)
* Added SERVER_TIMEOUT configuration option: [#245](https://github.com/elastic/apm-agent-php/pull/245)
* Automatically capture stack trace for spans: [#232](https://github.com/elastic/apm-agent-php/pull/232)
* Added VERIFY_SERVER_CERT configuration option: [#225](https://github.com/elastic/apm-agent-php/pull/225)
* Implemented sampling (TRANSACTION_SAMPLE_RATE): [#216](https://github.com/elastic/apm-agent-php/pull/216)

### Fixes [elastic-apm-php-agent-100-fixes]
* Small fixes to examples in docs: [#355](https://github.com/elastic/apm-agent-php/pull/355)
* Exclude query string from a transaction name: [#285](https://github.com/elastic/apm-agent-php/pull/285)
* Added check that the corresponding extension is loaded before instrumenting it: [#228](https://github.com/elastic/apm-agent-php/pull/228)








