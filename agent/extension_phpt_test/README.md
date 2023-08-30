
# extension_phpt_tests

The idea behind these tests is to run the "phpt" tests provided with the PHP source code together with the Elastic APM agent. This allows us to investigate the impact of the agent on the performance of the PHP language and also to easily diagnose corner cases and detect potential segmentation faults.

To help diagnose segmentation faults, gdb is installed in each Docker image, so that core dumps can be analysed directly.

Each image contains automatically run tests in two variants:
1. without the agent loaded
2. with a loaded agent

At the end of the tests, a summary will be printed containing how many tests failed for each of the variants

We are mindful that tests delivered with PHP can also fail without the agent loaded. These tests have been selected and placed in the folder `results`. For example for PHP 7.2 there is a file `results/testsAllowedToFail7.2.txt`.

After the tests have been performed, a folder will be created in the `results` folder, e.g. `7.2`` (depending on the PHP version being tested), and in this folder files will be created with the list of tests that failed - separately for the variant with and without an agent. In addition, a tar.gz archive will be created containing the results from the tests to help resolve the issue.

## Running the tests manually

### Prerequisities

To run the tests, you must first build the agent binaries for the `linux-x86-64-release` platform. You can read how to do this in the [DEVELOPMENT.md](../../DEVELOPMENT.md)

### Run all tests for all PHP supported versions in parallel

```
cd apm-agent-php/agent/extension_phpt_test
docker-compose up --build
```

It will start up elastic search and apm-server and execute tests for all supported PHP releases.

Please note that there are a lot of tests and it can take quite some time to complete all the tests. In addition, running the tests for all releases of the PHP in parallel can put a huge strain on the CPU and memory.

### Run tests for one PHP release

In this example we will run all tests with PHP 8.2

```
cd apm-agent-php/agent/extension_phpt_test
docker-compose up elasticsearch apm-server phpt_82
```
It will start up elastic search and apm-server and execute tests for PHP 8.2.

### Run one specified test

In this example we will run test with PHP 8.2

```
cd apm-agent-php/agent/extension_phpt_test
docker-compose build phpt_82 elasticsearch apm-server
docker-compose run --rm  phpt_82 /scripts/run.sh tests/output/ob_start_error_005.phpt
```

### Run bunch of tests from a folder

In this example we will run test with PHP 8.2

```
cd apm-agent-php/agent/extension_phpt_test
docker-compose build phpt_82 elasticsearch apm-server
docker-compose run --rm  phpt_82 /scripts/run.sh tests/output
```

### Core dump examination with gdb

In order to diagnose a core dump, you need to run the image together with the mounted path in which you collect core dumps. Then just use preinstalled gdb.

```
cd apm-agent-php/agent/extension_phpt_test
docker-compose run -v /path/to/coredumps:/path/to/coredumps phpt_82 /bin/bash
```
then inside runnning container:
```
gdb php /path/to/coredumps/coredump
```
