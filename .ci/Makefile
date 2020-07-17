IMAGE := test-php
PHP_VERSION ?= 7.2

CURRENT_UID := $(shell id -u)
CURRENT_GID := $(shell id -g)

.PHONY: prepare
prepare:
	docker build \
		--build-arg PHP_VERSION=${PHP_VERSION} \
		--tag $(IMAGE):${PHP_VERSION} .

.PHONY: build
build: prepare
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}

.PHONY: test
test: prepare
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION} \
		make test

.PHONY: install
install: prepare
	# docker as root to install the extension
	docker run --rm -t \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION} \
		make install

.PHONY: composer
composer: prepare
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
