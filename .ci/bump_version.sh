#!/usr/bin/env bash
set -xe

sed -i.bck "s#\(VERSION = \).*;#\1'${VERSION}';#g" src/ElasticApm/ElasticApm.php
sed -i.bck "s#\(PHP_ELASTIC_APM_VERSION\).*#\1 \"${VERSION}\"#g" src/ext/elastic_apm_version.h

git add src/ElasticApm/ElasticApm.php src/ext/elastic_apm_version.h
