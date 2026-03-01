# Contributing to the Elastic APM PHP agent

The PHP APM Agent is open source and we love to receive contributions from our community — you!

There are many ways to contribute,
from writing tutorials or blog posts,
improving the documentation,
submitting bug reports and feature requests or writing code.

You can get in touch with us through [Discuss](https://discuss.elastic.co/c/apm),
feedback and ideas are always welcome.

## Code contributions (please read this before your first PR)

If you have a bugfix or new feature that you would like to contribute,
please find or open an issue about it first.
Talk about what you would like to do.
It may be that somebody is already working on it,
or that there are particular issues that you should know about before implementing the change.

We aim to maintain high code quality, therefore every PR goes through the same review process, no matter who opened the PR.

### General advice

Please make sure that your PR addresses a single issue and its size is reasonable to review. There are no hard rules, but please keep in mind that every line of your PR will be reviewed and it's not uncommon that a longer discussion evolves in case of a sensitive change.

Therefore it's preferred to have multiple smaller PRs over a single big PR.

This makes sure that changes that have consensus get merged quickly and don't get blocked by unrelated changes. Additionally, the repository will also have a useful and searchable history by doing so.

Please do:
- Try to get feedback as early as possible and prefer to ask questions in issues before you start submitting code.
- Add a description to your PR which describes your intention and gives a high level summary about your changes.
- Run all the tests from the `test` folder and make sure that all of them are green. See [Testing](###Testing).
- In case of new code, make sure it's covered by at least 1 test.
- make sure your IDE uses the `.editorconfig` from the repo and you follow our coding guidelines. See [Coding-guidelines](###Coding-guidelines).
- Feel free to close a PR and create a new one in case you changed your mind, or found a better solution.
- Feel free to fix typos.
- Feel free to use the draft PR feature of GitHub, in case you would like to show some work in progress code and get feedback on it.

Please don't:
- Create a PR where you address multiple issues at once.
- Create a giant PR with a huge change set. There is no hard rule, but if your change grows over 1000 lines, it's maybe worth thinking about making it self contained and submit it as a PR and address follow-up issues in a subsequent PR. (of course there can be exceptions)
- Actively push code to a PR until you have received feedback. Of course if you spot some minor things after you opened a PR, it's perfectly fine to push a small fix. But please don't do active work on a PR that haven't received any feedback. It's very possible that someone already looked at it and is about to write a detailed review. If you actively add new changes to a PR then the reviewer will have a hard time to provide up to date feedback. If you just want to show work-in-progress code, feel free to use the draft feature of github, or indicate in the title, that the work is not 100% done yet. Of course, once you have feedback on a PR, it's perfectly fine, or rather encouraged to start working collaboratively on the PR and push new changes and address issues and suggestions from the reviewer.
- Change or add dependencies, unless you are really sure about it (it's best to ask about this in an issue first) - see [compatibility](####compatibility).

### Submitting your changes

Generally, we require that you test any code you are adding or modifying.
Once your changes are ready to submit for review:

1. Sign the Contributor License Agreement

    Please make sure you have signed our [Contributor License Agreement](https://www.elastic.co/contributor-agreement/).
    We are not asking you to assign copyright to us,
    but to give us the right to distribute your code without restriction.
    We ask this of all contributors in order to assure our users of the origin and continuing existence of the code.
    You only need to sign the CLA once.

2. Build and package your changes

    Run the build and package goals to make sure that nothing is broken.
    See [development](#development) for details.

3. Test your changes

    Run the test suite to make sure that nothing is broken.
    See [testing](#testing) for details.

4. Rebase your changes

    Update your local repository with the most recent code from the main repo,
    and rebase your branch on top of the latest main branch.
    We prefer your initial changes to be squashed into a single commit.
    Later,
    if we ask you to make changes,
    add them as separate commits.
    This makes them easier to review.
    As a final step before merging, we will either ask you to squash all commits yourself or we'll do it for you.

5. Submit a pull request

    Push your local changes to your forked copy of the repository and [submit a pull request](https://help.github.com/articles/using-pull-requests).
    In the pull request,
    choose a title which sums up the changes that you have made,
    and in the body provide more details about what your changes do.
    Also mention the number of the issue where the discussion has taken place,
    eg "Closes #123".

6. Be patient

    We might not be able to review your code as fast as we would like to,
    but we'll do our best to dedicate it the attention it deserves.
    Your effort is much appreciated!

### Development

See [development documentation](DEVELOPMENT.md).

### Testing

This project includes comprehensive test suites to ensure code quality and functionality.

#### Before You Start Testing

**Quick Decision Guide**:

- 🚀 **Just want to validate code quality?** → Run static checks: `composer run-script static_check`
- ✅ **Testing business logic changes?** → Run unit tests: `composer run-script run_unit_tests`
- 🔧 **Changed the native extension (C code)?** → Use Docker: `make -f .ci/Makefile run-phpt-tests`
- 🌐 **Testing with real HTTP requests?** → Use Docker: `make -f .ci/Makefile component-test`
- 🎯 **Testing with a specific framework?** → Use the framework script: `./scripts/test-framework.sh`
- 📦 **Preparing a PR?** → Run the full suite with Docker

#### Prerequisites

- Docker (for containerized testing)
- PHP 7.2+ with required extensions
- Composer

#### Test Requirements Overview

| Test Type       | Requires Extension | Requires External Services | Can Run Locally  | Recommended Approach                          |
|-----------------|--------------------|---------------------------|-------------------|-----------------------------------------------|
| Static Checks   | ❌ No              | ❌ No                     | ✅ Yes            | `composer run-script static_check`            |
| Unit Tests      | ❌ No              | ❌ No                     | ✅ Yes            | `composer run-script run_unit_tests`          |
| Component Tests | ✅ **Yes**         | ⚠️ MySQL (for some tests) | ⚠️ Advanced setup | Docker: `make -f .ci/Makefile component-test` |
| PHPT Tests      | ✅ **Yes**         | ❌ No                     | ⚠️ Advanced setup | Docker: `make -f .ci/Makefile run-phpt-tests` |

**Key Points**:
- **Static checks and unit tests** work immediately after `composer install` - no extension build required
- **Component and PHPT tests** require the native extension to be built and loaded - use Docker for easiest setup
- **Component tests** include 1 MySQL-dependent test that will fail without MySQL running (248 total tests, ~247 pass without MySQL)

#### Quick Start

##### Unit Tests and Static Checks (No Extension Required)

Unit tests and static checks can run directly without building the native extension:

```bash
# Install dependencies
composer install

# Run all static checks (linting, code style, PHPStan)
composer run-script static_check

# Run unit tests only
composer run-script run_unit_tests

# Run static checks and unit tests together
composer run-script static_check_and_run_unit_tests
```

##### Component Tests (Require Built Extension)

**Important**: Component tests require the Elastic APM PHP extension to be built and loaded. You have two options:

**Option 1: Using Docker (Recommended)**

The Docker approach handles building the extension automatically:

```bash
# Build the extension for your architecture (required first time)
BUILD_ARCHITECTURE=linux-x86-64 make -f .ci/Makefile build

# Option A: Run component tests WITHOUT MySQL-dependent tests (fastest)
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile component-test
# This will fail 1 test (MySQLi prerequisites) - see "Handling MySQL Tests" below

# Option B: Run ALL component tests including MySQL tests (complete)
# First, start external services (MySQL) in the background
source .ci/env_vars_for_external_services_for_component_tests.sh
.ci/start_external_services_for_component_tests.sh

# Then run component tests - all tests will pass
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile component-test

# Stop external services when done
.ci/stop_external_services_for_component_tests.sh
```

**Handling MySQL Tests**:

Some component tests (specifically `MySQLiAutoInstrumentationTest`) require a MySQL server to be running. If you run component tests without starting external services, you'll see 1 test failure:

```
My SQLi Auto Instrumentation (ElasticApmTests\ComponentTests\MySQLiAutoInstrumentation)
 ✘ Prerequisites satisfied
   Failed asserting that null is not null.
```

This is **expected and safe to ignore** if you're not working on MySQL instrumentation. The other tests will pass successfully.

If you need to test MySQL instrumentation:
1. Start the MySQL service using the commands in Option B below
2. The test expects these environment variables (set automatically by the script):
   - `ELASTIC_APM_PHP_TESTS_MYSQL_HOST`
   - `ELASTIC_APM_PHP_TESTS_MYSQL_PORT`
   - `ELASTIC_APM_PHP_TESTS_MYSQL_USER`
   - `ELASTIC_APM_PHP_TESTS_MYSQL_PASSWORD`
   - `ELASTIC_APM_PHP_TESTS_MYSQL_DB`

**Option 2: Local Setup (Advanced)**

To run component tests locally without Docker:

1. Build and install the extension (see [DEVELOPMENT.md](DEVELOPMENT.md))
2. Verify the extension is loaded:
   ```bash
   php -m | grep elastic_apm
   ```
3. Run the tests:
   ```bash
   composer run-script run_component_tests
   ```

##### Complete Test Suite Using Docker

For testing across different PHP versions and architectures:

```bash
# Run static checks and unit tests for PHP 8.3
PHP_VERSION=8.3 make -f .ci/Makefile static-check-unit-test

# Run static checks and unit tests for PHP 7.2 with Alpine
PHP_VERSION=7.2 DOCKERFILE=Dockerfile.alpine make -f .ci/Makefile static-check-unit-test

# Run PHPT tests for PHP 8.3 (requires built extension)
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile run-phpt-tests

# Open interactive shell in test container
PHP_VERSION=8.3 make -f .ci/Makefile interactive
```

#### Best Practices for Testing Before Submitting a PR

1. **Start with fast tests** - Run static checks and unit tests first for quick feedback
2. **Test the affected areas** - If you changed PHP code, run unit tests; if you changed C code, run PHPT tests
3. **Use Docker for comprehensive testing** - Before submitting, run the full Docker-based test suite
4. **Test on multiple PHP versions** - If your change affects compatibility, test on both older (7.2) and newer (8.4) PHP versions
5. **Add new tests** - If you're adding a feature or fixing a bug, include tests that validate the fix

**Recommended PR preparation checklist**:
```bash
# 1. Fast local validation
composer run-script static_check
composer run-script run_unit_tests

# 2. If you changed native extension code
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile run-phpt-tests

# 3. Full integration testing (1 MySQL test failure is expected, see notes below)
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile component-test
# Expected result: Tests: 248, Assertions: ~640,000, Failures: 1 (MySQLi prerequisites)

# 4. (Optional) If working on MySQL instrumentation, start external services first
source .ci/env_vars_for_external_services_for_component_tests.sh
.ci/start_external_services_for_component_tests.sh
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile component-test
# Expected result: All 248 tests pass

# 5. (Optional) Test with a real framework
./scripts/test-framework.sh 8.3 laravel
```

**Note**: It's normal and acceptable to have 1 test failure (MySQLi prerequisites check) when running component tests without starting external services. This will not prevent your PR from being merged.

#### Test Types

##### Static Analysis

**What it validates**: Code quality, style compliance, type safety, and potential bugs without executing code.

**When to run**: Before every commit, as part of your development workflow.

- **Parallel Lint**: Checks PHP syntax errors across all PHP files
  ```bash
  composer run-script parallel-lint
  ```

- **PHP_CodeSniffer**: Validates code style compliance with project standards
  ```bash
  composer run-script php_codesniffer_check
  # Auto-fix issues
  composer run-script php_codesniffer_fix
  ```

- **PHPStan**: Static analysis for type safety and potential bugs (max level)
  ```bash
  composer run-script phpstan
  # Generate JUnit reports for CI
  composer run-script phpstan-junit-report-for-ci
  ```

**Why it matters**: Catches common mistakes early and ensures consistent code style across the project.

##### Unit Tests

**What it validates**: Business logic, data transformations, utility functions, and internal APIs in isolation.

**When to run**: After making changes to PHP code in `agent/php/ElasticApm/` or test code.

**Test scope**: Tests in `tests/ElasticApmTests/UnitTests/` validate individual classes and functions without requiring the native extension or external services.

```bash
# Run all unit tests
composer run-script run_unit_tests

# Run specific test by filter
composer run-script run_unit_tests_filter -- <TestClassName>

# Example: Run only serialization tests
composer run-script run_unit_tests_filter -- Serializer
```

Configuration: `phpunit_v8_format.xml`

**Why it matters**: Fast feedback on business logic changes without needing to build the native extension. These tests form the first line of defense against regressions.

##### Component Tests

**What it validates**: End-to-end behavior of the agent in realistic scenarios including:
- HTTP request/response instrumentation
- Database query tracing
- External service call tracking
- Error capturing and reporting
- Span creation and propagation
- Communication with APM server

**When to run**: After making changes to instrumentation logic, configuration handling, or integration points.

**Test scope**: Tests in `tests/ElasticApmTests/ComponentTests/` run the agent in both HTTP server and CLI contexts, simulating real application behavior.

**Prerequisites**:
- The Elastic APM PHP extension must be built and loaded
- For local testing, see "Component Tests (Require Built Extension)" section above
- For Docker testing, the extension is built automatically

```bash
# Using Docker (Recommended) - extension is built and loaded automatically
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile component-test

# Test on multiple PHP versions
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=7.2 make -f .ci/Makefile component-test
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.4 make -f .ci/Makefile component-test

# Using local setup (requires extension to be built and installed first)
# Verify extension is loaded:
php -m | grep elastic_apm

# Run HTTP server component tests
composer run-script run_component_tests_http

# Run CLI component tests
composer run-script run_component_tests_cli

# Run both
composer run-script run_component_tests

# Filter specific component test
composer run-script run_component_tests_http_filter -- ApiKeySecretToken
```

Configuration: `phpunit_component_tests.xml`

**Why it matters**: Validates that the agent correctly captures and reports APM data in realistic application scenarios. These tests catch integration issues that unit tests might miss.

**Common Issues**:

1. **Extension not loaded**:
   ```
   RuntimeException: Environment hosting component tests application code should have elastic_apm extension loaded
   ```
   - The extension is not built or not loaded in PHP
   - **Solution**: Use Docker approach or build/install the extension locally

2. **MySQL test failure** (expected without MySQL running):
   ```
   MySQLiAutoInstrumentation ✘ Prerequisites satisfied
   Failed asserting that null is not null
   ```
   - MySQL server is not running or environment variables are not set
   - **Impact**: 1 test fails, ~247 tests pass
   - **Solution**: Either ignore this failure (if not working on MySQL instrumentation) OR start external services:
     ```bash
     source .ci/env_vars_for_external_services_for_component_tests.sh
     .ci/start_external_services_for_component_tests.sh
     ```

##### PHPT Tests

**What it validates**: Native C extension functionality at a low level including:
- Extension loading and initialization
- PHP API interactions
- Memory management
- Internal function hooking
- Extension configuration (INI settings)
- Compatibility across PHP versions

**When to run**: After making changes to the native extension code in `agent/native/`.

**Test scope**: PHPT (PHP Test) files in `agent/native/ext/tests/` use PHP's built-in testing framework to validate extension behavior.

**Prerequisites**:
- The Elastic APM PHP extension must be built
- Docker is the recommended approach for these tests

```bash
# Build the extension first (if not already built)
BUILD_ARCHITECTURE=linux-x86-64 make -f .ci/Makefile build

# Run PHPT tests for PHP 8.3
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile run-phpt-tests

# Run PHPT tests for PHP 7.2
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=7.2 make -f .ci/Makefile run-phpt-tests

# Test on Alpine (musl libc)
BUILD_ARCHITECTURE=linuxmusl-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile run-phpt-tests
```

**Why it matters**: These are the only tests that validate the C extension code directly. Essential for ensuring the extension doesn't crash, leak memory, or behave incorrectly at the PHP interpreter level.

##### Framework Testing

The `scripts/test-framework.sh` script helps you test the Elastic APM PHP Agent with real-world PHP frameworks. This is invaluable for:
- **Integration testing**: Verify the agent works correctly with popular frameworks
- **Compatibility validation**: Test across different PHP versions and framework versions
- **Issue reproduction**: Set up a minimal environment to reproduce framework-specific bugs
- **Development workflow**: Quickly spin up a framework instance instrumented with your local agent changes

**How it works**:
1. Downloads and installs the specified framework (Laravel, Symfony, WordPress, Drupal, or Magento)
2. Configures the framework to use your local development version of the agent
3. Creates a PHP configuration file with Elastic APM settings
4. Provides instructions for running the framework with APM instrumentation

**Usage**:
```bash
./scripts/test-framework.sh <PHP_VERSION> <FRAMEWORK> [FRAMEWORK_VERSION]
```

**Examples**:
```bash
# Test with Laravel 11.x on PHP 8.3
./scripts/test-framework.sh 8.3 laravel 11.x

# Test with Symfony 7.0 on PHP 8.2
./scripts/test-framework.sh 8.2 symfony 8.0.x

# Test with WordPress latest on PHP 8.1
./scripts/test-framework.sh 8.1 wordpress latest

# Test with Drupal on PHP 8.3
./scripts/test-framework.sh 8.3 drupal 7.x-dev
```

**Supported frameworks**: Laravel, Symfony, WordPress, Drupal, Magento

**Step-by-step workflow**:

1. **Build the agent extension** (if not already built):
   ```bash
   BUILD_ARCHITECTURE=linux-x86-64 make -f .ci/Makefile build
   ```

2. **Run the framework setup script**:
   ```bash
   ./scripts/test-framework.sh 8.3 laravel 11.x
   ```

   The script will:
   - Check PHP availability
   - Download and install Laravel 11.x in `build/framework-tests/laravel-8.3-11.x/`
   - Add the agent as a Composer dependency
   - Generate `php-apm.ini` with APM configuration

3. **Configure the extension path** in the generated `php-apm.ini`:
   ```ini
   ; Update this line with the actual path to your built extension
   extension=/path/to/apm-agent-php/agent/native/_build/linux-x86-64-release/ext/elastic_apm-PHPVERSION.so
   ```

4. **Start the framework application**:
   ```bash
   cd build/framework-tests/laravel-8.3-11.x/
   php -c php-apm.ini artisan serve
   ```

5. **Generate traffic** and verify traces appear in your APM server

**What gets configured**:

The script creates a `php-apm.ini` file with sensible defaults:
```ini
extension=elastic_apm.so
elastic_apm.enabled=1
elastic_apm.service_name=laravel_test  # Named after framework
elastic_apm.environment=development
elastic_apm.log_level=DEBUG             # Verbose logging for debugging
elastic_apm.server_url=http://localhost:8200
```

**Tips**:
- Each framework/PHP/version combination is installed in a separate directory
- You can reuse the same installation for multiple test runs
- Set `ELASTIC_APM_LOG_LEVEL_STDERR=TRACE` for even more detailed logs
- Use Docker for testing if your system PHP version doesn't match the desired version
- The script is extensible - you can add support for additional frameworks by editing it

#### Supported PHP Versions

Tests run against multiple PHP versions:
- PHP 7.2, 7.3, 7.4
- PHP 8.0, 8.1, 8.2, 8.3, 8.4

#### Supported Architectures

- `linux-x86-64` (default)
- `linux-arm64`
- `linuxmusl-x86-64` (Alpine)
- `linuxmusl-arm64` (Alpine ARM)

#### CI/CD Testing

GitHub Actions workflows automatically run tests on:
- Pull requests
- Pushes to main branch
- Multiple PHP versions and architectures in parallel

See `.github/workflows/test.yml` for the complete CI configuration.

#### Testing Workflow Summary

Here's a complete workflow from code change to ready-for-PR:

```bash
# 1. Make your code changes
# ... edit files ...

# 2. Quick validation (< 1 minute)
composer run-script static_check

# 3. Unit test validation (< 5 minutes)
composer run-script run_unit_tests

# 4. If changes affect PHP code - you're done! Commit and push
git add .
git commit -m "Your changes"

# 5. If changes affect native extension (C code) - run PHPT tests
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile run-phpt-tests

# 6. Before submitting PR - run full component test suite
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=8.3 make -f .ci/Makefile component-test
# Note: 1 MySQL test failure is expected (see documentation)

# 7. (Optional) Validate with a real framework
./scripts/test-framework.sh 8.3 symfony
cd build/framework-tests/symfony-8.3-latest/
# Configure php-apm.ini with extension path
php -c php-apm.ini bin/console about
```

#### Troubleshooting

##### Test Failures

1. **Ensure all dependencies are installed**:
   ```bash
   composer install
   ```

2. **Check PHP version compatibility**:
   ```bash
   php -v
   ```
   Make sure you're using PHP 7.2 or higher.

3. **For Docker tests, ensure Docker is running**:
   ```bash
   docker ps
   ```
   If Docker isn't running, start the Docker daemon.

4. **Extension not loaded** (for component/PHPT tests):
   ```bash
   php -m | grep elastic_apm
   ```
   If nothing is returned, the extension isn't loaded. Use Docker testing instead.

5. **Component test shows 1 MySQL failure**:
   ```
   FAILURES!
   Tests: 248, Assertions: 640117, Failures: 1.
   MySQLiAutoInstrumentation ✘ Prerequisites satisfied
   ```
   This is **expected and normal** when MySQL is not running. The test suite includes MySQL-specific tests that require an external MySQL server. You have two options:

   **Option A** (Recommended for most contributors): Ignore this failure. It won't affect your PR. The CI system will run tests with MySQL available.

   **Option B** (If working on MySQL instrumentation): Start the external services:
   ```bash
   source .ci/env_vars_for_external_services_for_component_tests.sh
   .ci/start_external_services_for_component_tests.sh
   # Re-run your component tests
   # Stop services when done:
   .ci/stop_external_services_for_component_tests.sh
   ```

##### Build Directory Permissions

If you encounter permission issues with the `build/` directory when using Docker:

```bash
sudo chown -R $(id -u):$(id -g) build/
```

This can happen because Docker containers run as root and create files owned by root.

##### Memory Limits

PHPUnit tests are configured with 2GB memory limit. If you encounter memory issues:

```bash
php -d memory_limit=4G vendor/bin/phpunit
```

##### Docker Build Issues

If Docker builds fail or hang:

```bash
# Clean up old containers and images
docker system prune -a

# Rebuild from scratch
BUILD_ARCHITECTURE=linux-x86-64 make -f .ci/Makefile build
```

##### Framework Testing Issues

If `./scripts/test-framework.sh` fails:

1. **Check PHP version availability**: The script checks if the requested PHP version is installed
2. **Check Composer availability**: Framework installation requires Composer
3. **Check network access**: Frameworks are downloaded from the internet
4. **Consult script output**: The script provides colored output with helpful error messages

**Extension path configuration**: After running the script, you must edit `php-apm.ini` to point to your built extension:

```bash
# Find your built extension
ls -la agent/native/_build/linux-x86-64-release/ext/

# Update php-apm.ini with the correct path
extension=/home/you/apm-agent-php/agent/native/_build/linux-x86-64-release/ext/elastic_apm-83.so
```

### Workflow

All feature development and most bug fixes hit the main branch first.
Pull requests should be reviewed by someone with commit access.
Once approved, the author of the pull request,
or reviewer if the author does not have commit access,
should "Squash and merge".

### Design considerations

#### Performance

The agent is designed to monitor production applications. Therefore it's very important to keep the performance overhead of the agent as low as possible.

It's not uncommon that you write or change code that can potentially change the performance characteristics of the agent and therefore also of the application's of our users.

##### Performance testing
    
Coming soon [TBD]

#### Compatibility
    
Coming soon [TBD]

### Coding guidelines

Coming soon [TBD]

### Adding support for instrumenting new libraries/frameworks/APIs

Coming soon

### Releasing

Coming soon.
