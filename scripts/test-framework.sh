#!/usr/bin/env bash
set -e

# Script to test the Elastic APM PHP Agent with various PHP frameworks
# Usage: ./scripts/test-framework.sh <PHP_VERSION> <FRAMEWORK> [FRAMEWORK_VERSION]
#
# Examples:
#   ./scripts/test-framework.sh 8.3 laravel 11.x
#   ./scripts/test-framework.sh 8.2 symfony 7.0
#   ./scripts/test-framework.sh 8.1 wordpress 6.4

VERSION=${1:?Please specify the PHP version to be tested with (e.g., 8.3). Usage: ./scripts/test-framework.sh <PHP_VERSION> <FRAMEWORK> [FRAMEWORK_VERSION]}
FRAMEWORK=${2:?Please specify the FRAMEWORK to be tested with (e.g., laravel, symfony, wordpress) Usage: ./scripts/test-framework.sh <PHP_VERSION> <FRAMEWORK> [FRAMEWORK_VERSION]}
FRAMEWORK_VERSION=${3:-latest}

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "${SCRIPT_DIR}/.." && pwd )"
TEST_DIR="${PROJECT_ROOT}/build/framework-tests"
FRAMEWORK_DIR="${TEST_DIR}/${FRAMEWORK}-${VERSION}-${FRAMEWORK_VERSION}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if PHP version is installed
check_php_version() {
    echo_info "Checking PHP ${VERSION} availability..."
    if ! command -v php${VERSION} &> /dev/null && ! php -v | grep -q "PHP ${VERSION}"; then
        echo_error "PHP ${VERSION} is not installed or not in PATH"
        echo_info "You can use Docker to test with different PHP versions:"
        echo_info "  PHP_VERSION=${VERSION} make -f .ci/Makefile interactive"
        exit 1
    fi
    echo_info "PHP ${VERSION} is available"
}

# Setup framework test environment
setup_framework() {
    echo_info "Setting up ${FRAMEWORK} ${FRAMEWORK_VERSION} with PHP ${VERSION}..."
    mkdir -p "${FRAMEWORK_DIR}"
    cd "${FRAMEWORK_DIR}"

    case "${FRAMEWORK}" in
        laravel)
            setup_laravel
            ;;
        symfony)
            setup_symfony
            ;;
        wordpress)
            setup_wordpress
            ;;
        drupal)
            setup_drupal
            ;;
        magento)
            setup_magento
            ;;
        *)
            echo_error "Framework '${FRAMEWORK}' is not supported yet"
            echo_info "Supported frameworks: laravel, symfony, wordpress, drupal, magento"
            echo_info "You can add support by extending this script"
            exit 1
            ;;
    esac
}

setup_laravel() {
    echo_info "Installing Laravel ${FRAMEWORK_VERSION}..."
    if [ ! -f "composer.json" ]; then
        composer create-project --prefer-dist laravel/laravel . "${FRAMEWORK_VERSION}"
    fi
}

setup_symfony() {
    echo_info "Installing Symfony ${FRAMEWORK_VERSION}..."
    if [ ! -f "composer.json" ]; then
        composer create-project symfony/skeleton . "${FRAMEWORK_VERSION}"
    fi
}

setup_wordpress() {
    echo_info "Downloading WordPress ${FRAMEWORK_VERSION}..."
    if [ ! -f "wp-config-sample.php" ]; then
        if [ "${FRAMEWORK_VERSION}" = "latest" ]; then
            curl -O https://wordpress.org/latest.tar.gz
        else
            curl -O "https://wordpress.org/wordpress-${FRAMEWORK_VERSION}.tar.gz"
        fi
        tar -xzf *.tar.gz --strip-components=1
        rm *.tar.gz
    fi
}

setup_drupal() {
    echo_info "Installing Drupal ${FRAMEWORK_VERSION}..."
    if [ ! -f "composer.json" ]; then
        composer create-project drupal/recommended-project . "${FRAMEWORK_VERSION}"
    fi
}

setup_magento() {
    echo_info "Installing Magento ${FRAMEWORK_VERSION}..."
    echo_warn "Magento requires additional setup (database, elasticsearch, etc.)"
    echo_warn "This is a basic installation only"
    if [ ! -f "composer.json" ]; then
        composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition . "${FRAMEWORK_VERSION}"
    fi
}

# Configure Elastic APM for the framework
configure_apm() {
    echo_info "Configuring Elastic APM PHP Agent..."

    # Add elastic-apm-php to composer dependencies if not already present
    if [ -f "composer.json" ]; then
        if ! grep -q "elastic/apm-agent" composer.json; then
            echo_info "Adding Elastic APM PHP Agent to composer.json..."
            composer config repositories.local path "${PROJECT_ROOT}"
            composer require elastic/apm-agent:@dev
        fi
    fi

    # Create a basic php.ini configuration for the agent
    cat > php-apm.ini <<EOF
; Elastic APM PHP Agent Configuration
extension=elastic_apm.so
elastic_apm.enabled=1
elastic_apm.service_name=${FRAMEWORK}_test
elastic_apm.environment=development
elastic_apm.log_level=DEBUG
elastic_apm.server_url=http://localhost:8200
EOF

    echo_info "Created php-apm.ini configuration"
    echo_warn "Remember to load this configuration with: php -c php-apm.ini"
}

# Run basic tests
run_tests() {
    echo_info "Running basic framework tests with Elastic APM..."

    case "${FRAMEWORK}" in
        laravel)
            if [ -f "artisan" ]; then
                echo_info "Running Laravel artisan commands..."
                php artisan --version
                echo_info "You can now test your Laravel application with:"
                echo_info "  cd ${FRAMEWORK_DIR}"
                echo_info "  php -c php-apm.ini artisan serve"
            fi
            ;;
        symfony)
            if [ -f "bin/console" ]; then
                echo_info "Running Symfony console commands..."
                php bin/console --version
                echo_info "You can now test your Symfony application with:"
                echo_info "  cd ${FRAMEWORK_DIR}"
                echo_info "  php -c php-apm.ini -S localhost:8000 -t public/"
            fi
            ;;
        wordpress)
            echo_info "WordPress is installed. You can test it with:"
            echo_info "  cd ${FRAMEWORK_DIR}"
            echo_info "  php -c php-apm.ini -S localhost:8000"
            echo_info "Then visit http://localhost:8000 to complete setup"
            ;;
        drupal|magento)
            echo_info "${FRAMEWORK} is installed in ${FRAMEWORK_DIR}"
            echo_info "Additional configuration is required. See ${FRAMEWORK} documentation."
            ;;
    esac
}

# Print summary
print_summary() {
    echo ""
    echo_info "========================================="
    echo_info "Framework Test Setup Complete"
    echo_info "========================================="
    echo_info "Framework: ${FRAMEWORK} ${FRAMEWORK_VERSION}"
    echo_info "PHP Version: ${VERSION}"
    echo_info "Location: ${FRAMEWORK_DIR}"
    echo ""
    echo_info "Next steps:"
    echo_info "1. Ensure Elastic APM Server is running (or set elastic_apm.server_url)"
    echo_info "2. Build the Elastic APM PHP extension if not already built"
    echo_info "3. Start the framework application with the APM configuration"
    echo_info "4. Generate traffic to see traces in Kibana"
    echo ""
    echo_info "To rebuild the agent:"
    echo_info "  cd ${PROJECT_ROOT}"
    echo_info "  PHP_VERSION=${VERSION} make -f .ci/Makefile build"
    echo ""
}

# Main execution
main() {
    echo_info "Starting framework test setup for ${FRAMEWORK} ${FRAMEWORK_VERSION} with PHP ${VERSION}"

    check_php_version
    setup_framework
    configure_apm
    run_tests
    print_summary

    echo_info "Framework test environment ready!"
}

main
