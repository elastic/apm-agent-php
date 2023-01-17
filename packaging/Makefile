SHELL = /bin/bash
IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages
PHP_AGENT_DIR:=/opt/elastic/apm-agent-php
PHP_VERSION?=7.2
GIT_SHA?=$(shell git rev-parse HEAD || echo "unknown")
RELEASE_VERSION?=
GITHUB_RELEASES_URL=https://github.com/elastic/apm-agent-php/releases/download
export FPM_FLAGS=

.PHONY: help
.DEFAULT_GOAL := help
help: ## Display this help text
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: clean
clean: ## Clean the generated packages
	rm -f $(PWD)/$(OUTPUT)/*.*

.PHONY: prepare
prepare: ## Build docker image for the packaging
	docker build -t $(IMAGE) . || exit 1

.PHONY: build-docker-images
build-docker-images: prepare prepare-apk prepare-deb prepare-rpm prepare-tar prepare-deb-apache prepare-deb-fpm ## Build all the docker images
	@echo 'Build docker images'

create-%: prepare  ## Create the specific package
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
		-w /app $(IMAGE)

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
	BINARY=$$(ls -1 $(OUTPUT)/*.apk) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /sbin/apk $(IMAGE) manifest $$BINARY

.PHONY: deb-info
deb-info:  ## Show the deb package metadata
	cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.deb) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: rpm-info
rpm-info:  ## Show the rpm package metadata
	cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.rpm) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: tar-info
tar-info:  ## Show the tar package metadata
	cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY

.PHONY: prepare-apk
prepare-apk:  ## Build the docker image for the apk smoke tests
	cd $(PWD)/packaging/test/alpine ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) --build-arg ADD_LIBUNWIND_DEPENDENCY=${ADD_LIBUNWIND_DEPENDENCY} -t $@ . || exit 1 ;\
	cd -

.PHONY: prepare-deb-apache
prepare-deb-apache:  ## Build the docker image for the deb smoke tests for apache
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --file apache/Dockerfile --build-arg PHP_VERSION=$(PHP_VERSION) --tag $@ . || exit 1;\
	cd -

.PHONY: prepare-deb-fpm
prepare-deb-fpm:  ## Build the docker image for the deb smoke tests for fpm
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --file fpm/Dockerfile --build-arg PHP_VERSION=$(PHP_VERSION) --tag $@ . || exit 1 ;\
	cd -

.PHONY: prepare-deb
prepare-deb:  ## Build the docker image for the deb smoke tests
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . || exit 1 ;\
	cd -

.PHONY: prepare-tar
prepare-tar:  ## Build the docker image for the tar smoke tests
	cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . || exit 1 ;\
	cd -

.PHONY: prepare-rpm
prepare-rpm:  ## Build the docker image for the rpm smoke tests
	cd $(PWD)/packaging/test/centos ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . || exit 1 ;\
	cd -

.PHONY: apk-install
apk-install: prepare-apk  ## Install the apk installer to run some smoke tests
	$(PWD)/.ci/run_docker_with_component_tests.sh prepare-apk

.PHONY: deb-install
deb-install: prepare-deb  ## Install the deb installer to run some smoke tests
	TYPE=deb $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb

.PHONY: tar-install
tar-install: prepare-tar  ## Install the tar installer to run some smoke tests
	TYPE=tar $(PWD)/.ci/run_docker_with_component_tests.sh prepare-tar

.PHONY: rpm-install
rpm-install: prepare-rpm  ## Install the rpm installer to run some smoke tests
	TYPE=rpm $(PWD)/.ci/run_docker_with_component_tests.sh prepare-rpm

.PHONY: deb-install-in-apache
deb-install-in-apache: prepare-deb-apache  ## Install the deb installer to run some smoke tests in apache
	TYPE=deb $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb-apache

.PHONY: deb-install-in-fpm
deb-install-in-fpm: prepare-deb-fpm  ## Install the deb installer to run some smoke tests in fpm
	TYPE=deb $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb-fpm

.PHONY: install
install: apk-install deb-install rpm-install tar-install  ## Install all the distributions

.PHONY: apk-install-release-github
apk-install-release-github: prepare-apk  ## Install the apk installer from a given release to run some smoke tests
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=release-github $(PWD)/.ci/run_docker_with_component_tests.sh prepare-apk

.PHONY: deb-install-release-github
deb-install-release-github: prepare-deb  ## Install the deb installer from a given release to run some smoke tests
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=release-github $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb

.PHONY: rpm-install-release-github
rpm-install-release-github: prepare-rpm  ## Install the rpm installer from a given release to run some smoke tests
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=release-github $(PWD)/.ci/run_docker_with_component_tests.sh prepare-rpm

.PHONY: tar-install-release-github
tar-install-release-github: prepare-tar  ## Install the tar installer from a given release to run some smoke tests
	GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) VERSION=$(RELEASE_VERSION) TYPE=release-tar-github $(PWD)/.ci/run_docker_with_component_tests.sh prepare-tar

.PHONY: install-release-github
install-release-github: apk-install-release-github deb-install-release-github rpm-install-release-github tar-install-release-github  ## Install all the distributions for a given release using the downloaded binaries

.PHONY: lifecycle-testing
lifecycle-testing: apk-lifecycle-testing deb-lifecycle-testing rpm-lifecycle-testing tar-lifecycle-testing ## Lifecycle testing all the distributions

.PHONY: apk-lifecycle-testing
apk-lifecycle-testing: prepare-apk  ## Lifecycle testing for the apk installer
	TYPE=apk-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-apk

.PHONY: deb-lifecycle-testing
deb-lifecycle-testing: prepare-deb  ## Lifecycle testing for the deb installer
	TYPE=deb-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb

.PHONY: deb-lifecycle-testing-in-apache
deb-lifecycle-testing-in-apache: prepare-deb-apache  ## Lifecycle testing for the deb installer with apache
	TYPE=deb-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb-apache

.PHONY: deb-lifecycle-testing-in-fpm
deb-lifecycle-testing-in-fpm: prepare-deb-fpm  ## Lifecycle testing for the deb installer with fpm
	TYPE=deb-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb-fpm

.PHONY: rpm-lifecycle-testing
rpm-lifecycle-testing: prepare-rpm  ## Lifecycle testing for the rpm installer
	TYPE=rpm-uninstall PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-rpm

.PHONY: tar-lifecycle-testing
tar-lifecycle-testing: prepare-tar  ## Lifecycle testing for the tar installer
	TYPE=tar-uninstall $(PWD)/.ci/run_docker_with_component_tests.sh prepare-tar

.PHONY: rpm-php-upgrade-testing
rpm-php-upgrade-testing: PHP_VERSION="7.2"  ### Force the PHP version to start with.
rpm-php-upgrade-testing: prepare-rpm  ## PHP upgrade, from 7.2 to 7.4, testing for the rpm installer
	TYPE=php-upgrade PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-rpm

.PHONY: rpm-agent-upgrade-testing
rpm-agent-upgrade-testing: prepare-rpm  ## Agent upgrade, from 1.0.0 to the current generated one, testing for the rpm installer
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-rpm

.PHONY: deb-agent-upgrade-testing
deb-agent-upgrade-testing: prepare-deb  ## Agent upgrade, from 1.0.0 to the current generated one, testing for the deb installer
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb

.PHONY: rpm-agent-upgrade-testing-local
rpm-agent-upgrade-testing-local: prepare-rpm  ## Agent upgrade, from 1.0.0 to the current generated one, testing for the rpm installer
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade-local PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-rpm

.PHONY: deb-agent-upgrade-testing-local
deb-agent-upgrade-testing-local: prepare-deb  ## Agent upgrade, from 1.0.0 to the current generated one, testing for the deb installer
	VERSION=$(RELEASE_VERSION) GITHUB_RELEASES_URL=$(GITHUB_RELEASES_URL) TYPE=agent-upgrade-local PACKAGE=$(NAME) $(PWD)/.ci/run_docker_with_component_tests.sh prepare-deb
