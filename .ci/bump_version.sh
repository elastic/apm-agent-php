#!/usr/bin/env bash
set -xe

sed -i.bck "s#\(VERSION = \).*;#\1'${VERSION}';#g" agent/php/ElasticApm/ElasticApm.php
sed -i.bck "s#\(PHP_ELASTIC_APM_VERSION\).*#\1 \"${VERSION}\"#g" agent/native/ext/elastic_apm_version.h

git add agent/php/ElasticApm/ElasticApm.php agent/native/ext/elastic_apm_version.h
