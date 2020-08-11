IMAGE := test-php
PHP_VERSION ?= 7.2

CURRENT_UID := $(shell id -u)
CURRENT_GID := $(shell id -g)

.PHONY: help
.DEFAULT_GOAL := help
help: ## Display this help text
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: prepare
prepare:  ## Build docker image for building and testing the project
	docker build \
		--build-arg PHP_VERSION=${PHP_VERSION} \
		--tag $(IMAGE):${PHP_VERSION} .

.PHONY: build
build: prepare  ## Build the project
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}

.PHONY: test
test: prepare  ## Test the project
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION} \
		make test

.PHONY: generate
generate: prepare  ## Generate the agent extension
	# docker as root to install the extension
	docker run --rm -it \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION} \
		/app/.ci/compile.sh

.PHONY: composer
composer: prepare  ## Run composer
	# docker as root to install the extension
	docker run -t --rm \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION} \
		sh -c 'make install \
			&& echo 'extension=elastic_apm.so' > /usr/local/etc/php/php.ini \
			&& echo 'elastic_apm.bootstrap_php_part_file=/app/src/bootstrap_php_part.php' >> /usr/local/etc/php/php.ini \
			&& php -m \
			&& cd /app \
			&& composer install \
			&& composer static_check_and_run_component_tests_standalone_envs'
