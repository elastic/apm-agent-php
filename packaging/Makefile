SHELL = /bin/bash
MAKEFLAGS += --no-print-directory
IMAGE:=php-packaging
IMAGE_TAG:=latest
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../agent/php/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages
PHP_AGENT_DIR:=/opt/elastic/apm-agent-php
PHP_VERSION?=7.2
SEL_DISTRO?=buster
GIT_SHA?=$(shell git rev-parse HEAD || echo "unknown")
RELEASE_VERSION?=
GITHUB_RELEASES_URL=https://github.com/elastic/apm-agent-php/releases/download
BUILD_ARCH := $(if $(BUILD_ARCH),$(BUILD_ARCH),"x86-64")

export FPM_FLAGS=

ifneq ($(PHP_VERSION), 7.2)
	SEL_DISTRO := bullseye
endif

.PHONY: help
.DEFAULT_GOAL := help
help: ## Display this help text
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: clean
clean: ## Clean the generated packages
	rm -f $(PWD)/$(OUTPUT)/*.*

.PHONY: prepare
prepare: ## Build docker image for the packaging
	docker build --build-arg IMAGE_TAG=$(IMAGE_TAG) -t $(IMAGE) . || exit 1

.PHONY: build-docker-images
build-docker-images: prepare prepare-apk prepare-deb prepare-rpm prepare-tar prepare-deb-apache prepare-deb-fpm ## Build all the docker images
	@echo 'Build docker images'

create-%: prepare  ## Create the specific package
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	@echo "Creating package $* ..."
	mkdir -p $(PWD)/$(OUTPUT)
	docker run --rm \
		-v $(PWD):/app \
		-e TYPE=$* \
		-e NAME=$(NAME) \
		-e VERSION=$(VERSION) \
		-e OUTPUT=$(OUTPUT) \
		-e FPM_FLAGS=${FPM_FLAGS} \
		-e PHP_AGENT_DIR=${PHP_AGENT_DIR} \
		-e BUILD_ARCH=$(BUILD_ARCH) \
		-w /app $(IMAGE)
	@echo "::endgroup::"

.PHONY: apk
apk: FPM_FLAGS="--depends=bash"
apk: create-apk  ## Create the apk installer

.PHONY: deb
deb: create-deb  ## Create the deb installer

.PHONY: rpm
rpm: create-rpm  ## Create the rpm installer

.PHONY: tar
tar: create-tar  ## Create the tar.gz

.PHONY: version
version:  ## Show the fpm version
	docker run --rm $(IMAGE) --version || exit 1

.PHONY: package
package: apk deb rpm tar  ## Create all the installers

.PHONY: info
info: apk-info deb-info rpm-info tar-info  ## Show the package metadata for all the installers

.PHONY: apk-info
apk-info:  ## Show the apk package metadata
	cd $(PWD) ;\
	if [ "$(BUILD_ARCH)" = "arm64" ]; then \
		BINARY=$$(find $(OUTPUT)/ -type f -name "*aarch64*.apk" -o -name "*arm64*.apk" | head -n 1); \
	elif [ "$(BUILD_ARCH)" = "x86-64" ]; then \
		BINARY=$$(find $(OUTPUT)/ -type f -name "*x86_64*.apk" -o -name "*amd64*.apk" | head -n 1); \
	else \
		echo "Unsupported BUILD_ARCH: $(BUILD_ARCH)"; \
		exit 1; \
	fi; \
	echo $$BINARY; \
	docker run --rm -v $(PWD):/app -w /app --entrypoint /sbin/apk $(IMAGE) manifest $$BINARY

.PHONY: deb-info
deb-info:  ## Show the deb package metadata
	cd $(PWD) ;\
	if [ "$(BUILD_ARCH)" = "arm64" ]; then \
		BINARY=$$(find $(OUTPUT)/ -type f -name "*aarch64*.deb" -o -name "*arm64*.deb" | head -n 1); \
	elif [ "$(BUILD_ARCH)" = "x86-64" ]; then \
		BINARY=$$(find $(OUTPUT)/ -type f -name "*x86_64*.deb" -o -name "*amd64*.deb" | head -n 1); \
	else \
		echo "Unsupported BUILD_ARCH: $(BUILD_ARCH)"; \
		exit 1; \
	fi; \
	echo $$BINARY; \
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: rpm-info
rpm-info:  ## Show the rpm package metadata
	cd $(PWD) ;\
	if [ "$(BUILD_ARCH)" = "arm64" ]; then \
		BINARY=$$(find $(OUTPUT)/ -type f -name "*aarch64*.rpm" -o -name "*arm64*.rpm" | head -n 1); \
	elif [ "$(BUILD_ARCH)" = "x86-64" ]; then \
		BINARY=$$(find $(OUTPUT)/ -type f -name "*x86_64*.rpm" -o -name "*amd64*.rpm" | head -n 1); \
	else \
		echo "Unsupported BUILD_ARCH: $(BUILD_ARCH)"; \
		exit 1; \
	fi; \
	echo $$BINARY; \
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: tar-info
tar-info:  ## Show the tar package metadata
	cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/$(NAME)-linux-$(BUILD_ARCH)*.tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY ;\
	BINARY=$$(ls -1 $(OUTPUT)/$(NAME)-debugsymbols-linux-$(BUILD_ARCH).tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY ;\
	BINARY=$$(ls -1 $(OUTPUT)/$(NAME)-linuxmusl-$(BUILD_ARCH)*.tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY ;\
	BINARY=$$(ls -1 $(OUTPUT)/$(NAME)-debugsymbols-linuxmusl-$(BUILD_ARCH).tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY

.PHONY: prepare-apk
prepare-apk:  ## Build the docker image for the apk smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	cd $(PWD)/packaging/test/alpine ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . || exit 1 ;\
	cd -
	@echo "::endgroup::"

.PHONY: prepare-deb-apache
prepare-deb-apache:  ## Build the docker image for the deb smoke tests for apache
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --file apache/Dockerfile --build-arg PHP_VERSION=$(PHP_VERSION) --tag $@ . || exit 1;\
	cd -
	@echo "::endgroup::"

.PHONY: prepare-deb-fpm
prepare-deb-fpm:  ## Build the docker image for the deb smoke tests for fpm
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --file fpm/Dockerfile --build-arg PHP_VERSION=$(PHP_VERSION) --tag $@ . || exit 1 ;\
	cd -
	@echo "::endgroup::"

.PHONY: prepare-deb
prepare-deb:  ## Build the docker image for the deb smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) --build-arg SEL_DISTRO=$(SEL_DISTRO) -t $@ . || exit 1 ;\
	cd -
	@echo "::endgroup::"

.PHONY: prepare-tar
prepare-tar:  ## Build the docker image for the tar smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) --build-arg SEL_DISTRO=$(SEL_DISTRO) -t $@ . || exit 1 ;\
	cd -
	@echo "::endgroup::"

.PHONY: prepare-rpm
prepare-rpm:  ## Build the docker image for the rpm smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	cd $(PWD)/packaging/test/centos ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . || exit 1 ;\
	cd -
	@echo "::endgroup::"

.PHONY: apk-install
apk-install: ## Install the apk installer to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	$(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-apk-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: deb-install
deb-install: ## Install the deb installer to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=deb $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1

.PHONY: tar-install
tar-install: ## Install the tar installer to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=tar $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: rpm-install
rpm-install: ## Install the rpm installer to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=rpm $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-rpm-php-$(PHP_VERSION)-0.0.3
	@echo "::endgroup::"

.PHONY: deb-install-in-apache
deb-install-in-apache: ## Install the deb installer to run some smoke tests in apache
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=deb $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-apache-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: deb-install-in-fpm
deb-install-in-fpm: ## Install the deb installer to run some smoke tests in fpm
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=deb $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-fpm-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: install
install: apk-install deb-install rpm-install tar-install  ## Install all the distributions

.PHONY: apk-install-release-github
apk-install-release-github: ## Install the apk installer from a given release to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=release-github $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-apk-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: deb-install-release-github
deb-install-release-github: ## Install the deb installer from a given release to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=release-github $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: rpm-install-release-github
rpm-install-release-github: ## Install the rpm installer from a given release to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=release-github $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-rpm-php-$(PHP_VERSION)-0.0.3
	@echo "::endgroup::"

.PHONY: tar-install-release-github
tar-install-release-github: ## Install the tar installer from a given release to run some smoke tests
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) VERSION=$(RELEASE_VERSION) TYPE=release-tar-github $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: install-release-github
install-release-github: apk-install-release-github deb-install-release-github rpm-install-release-github tar-install-release-github  ## Install all the distributions for a given release using the downloaded binaries

.PHONY: lifecycle-testing
lifecycle-testing: apk-lifecycle-testing deb-lifecycle-testing rpm-lifecycle-testing tar-lifecycle-testing ## Lifecycle testing all the distributions

.PHONY: apk-lifecycle-testing
apk-lifecycle-testing: ## Lifecycle testing for the apk installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=apk-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-apk-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: deb-lifecycle-testing
deb-lifecycle-testing: ## Lifecycle testing for the deb installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=deb-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: deb-lifecycle-testing-in-apache
deb-lifecycle-testing-in-apache: ## Lifecycle testing for the deb installer with apache
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=deb-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-apache-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: deb-lifecycle-testing-in-fpm
deb-lifecycle-testing-in-fpm: ## Lifecycle testing for the deb installer with fpm
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=deb-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-fpm-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: rpm-lifecycle-testing
rpm-lifecycle-testing:  ## Lifecycle testing for the rpm installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=rpm-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-rpm-php-$(PHP_VERSION)-0.0.3
	@echo "::endgroup::"

.PHONY: tar-lifecycle-testing
tar-lifecycle-testing: ## Lifecycle testing for the tar installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	TYPE=tar-uninstall $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: rpm-php-upgrade-testing
### Force the PHP version to start with.
rpm-php-upgrade-testing: PHP_VERSION=7.2
rpm-php-upgrade-testing: ## PHP upgrade, from 7.2 to 7.4, testing for the rpm installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	echo "'$(PHP_VERSION)'"
	TYPE=php-upgrade PHP_VERSION=$(PHP_VERSION) PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-rpm-php-$(PHP_VERSION)-0.0.3
	@echo "::endgroup::"

.PHONY: rpm-agent-upgrade-testing
rpm-agent-upgrade-testing:  ## Agent upgrade, from 1.0.0 to the current generated one, testing for the rpm installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-rpm-php-$(PHP_VERSION)-0.0.3
	@echo "::endgroup::"

.PHONY: deb-agent-upgrade-testing
deb-agent-upgrade-testing: ## Agent upgrade, from 1.0.0 to the current generated one, testing for the deb installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"

.PHONY: rpm-agent-upgrade-testing-local
rpm-agent-upgrade-testing-local:  ## Agent upgrade, from 1.0.0 to the current generated one, testing for the rpm installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade-local PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-rpm-php-$(PHP_VERSION)-0.0.3
	@echo "::endgroup::"

.PHONY: deb-agent-upgrade-testing-local
deb-agent-upgrade-testing-local: ## Agent upgrade, from 1.0.0 to the current generated one, testing for the deb installer
	@echo "::group::$@"  # Helping to group logs in GitHub actions
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade-local PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh elasticobservability/apm-agent-php-dev:packages-test-deb-php-$(PHP_VERSION)-0.0.1
	@echo "::endgroup::"
