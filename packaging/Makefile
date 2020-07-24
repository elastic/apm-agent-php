IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages
PHP_AGENT_DIR:=/opt/elastic/apm-agent-php
PHP_VERSION?=7.2
GIT_SHA ?= $(shell git rev-parse HEAD || echo "unknown")

.PHONY: help
.DEFAULT_GOAL := help
help: ## Display this help text
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: prepare
prepare: ## Build docker image for the packaging
	@docker build -t $(IMAGE) .

create-%: prepare  ## Create the specific package
	@echo 'Creating package ...'
	@mkdir -p $(PWD)/$(OUTPUT)/$(PHP_VERSION)
	@docker run --rm \
		-v $(PWD):/app \
		-w /app $(IMAGE) \
		--input-type dir \
		--output-type $* \
		--name $(NAME) \
		--version $(VERSION) \
		--architecture all \
		--url 'https://github.com/elastic/apm-agent-php' \
		--maintainer 'APM Team <info@elastic.co>' \
		--license 'ASL 2.0' \
		--vendor 'Elasticsearch, Inc.' \
		--description "PHP agent for Elastic APM\nGit Commit: ${GIT_SHA}" \
		--package $(OUTPUT)/$(PHP_VERSION) \
		--chdir /app \
		--after-install=packaging/post-install.sh \
		packaging/post-install.sh=$(PHP_AGENT_DIR)/bin/post-install.sh \
		src/ext/modules/=$(PHP_AGENT_DIR)/extensions \
		README.md=$(PHP_AGENT_DIR)/docs/README.md \
		src/ElasticApm=$(PHP_AGENT_DIR)/src \
		src/bootstrap_php_part.php=$(PHP_AGENT_DIR)/src/bootstrap_php_part.php
	@echo 'Creating sha512sum ...'
	@BINARY=$$(ls -1 $(PWD)/$(OUTPUT)/$(PHP_VERSION)/*.$*) ;\
	sha512sum $$BINARY > $$BINARY.sha512 ;\
	sed -i.bck "s#$(PWD)/$(OUTPUT)/$(PHP_VERSION)/##g" $$BINARY.sha512 ;\
	rm $(PWD)/$(OUTPUT)/$(PHP_VERSION)/*.bck

.PHONY: rpm
rpm: create-rpm  ## Create the rpm installer

.PHONY: deb
deb: create-deb  ## Create the deb installer

.PHONY: tar
tar: create-tar  ## Create the tar.gz

.PHONY: version
version:  ## Show the fpm version
	@docker run --rm $(IMAGE) --version

.PHONY: package
package: rpm deb tar  ## Create all the installers

.PHONY: info
info: rpm-info deb-info tar-info  ## Show the package metadata for all the installers

.PHONY: rpm-info
rpm-info:  ## Show the rpm package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/$(PHP_VERSION)/*.rpm) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: deb-info
deb-info:  ## Show the deb package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/$(PHP_VERSION)/*.deb) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: tar-info
tar-info:  ## Show the tar package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY

.PHONY: rpm-install
rpm-install:  ## Install the rpm installer to run some smoke tests
	@cd $(PWD)/packaging/test/centos ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t rpm-install . ;\
	cd -
	docker run --rm -v $(PWD):/src -w /src rpm-install

.PHONY: deb-install
deb-install:  ## Install the deb installer to run some smoke tests
	@cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t deb-install . ;\
	cd -
	@docker run --rm -v $(PWD):/src -w /src deb-install

.PHONY: tar-install
tar-install:  ## Install the tar installer to run some smoke tests
	@cd $(PWD)/packaging/test/tar ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t tar-install . ;\
	cd -
	docker run --rm -v $(PWD):/src -w /src tar-install

.PHONY: install
install: deb-install rpm-install tar-install ## Install all the distributions
