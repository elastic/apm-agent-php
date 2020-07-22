IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages

GIT_SHA ?= $(shell git rev-parse HEAD || echo "unknown")

.PHONY: help
.DEFAULT_GOAL := help
help: ## Display this help text
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: build
build: ## Build docker image for the packaging
	@docker build -t $(IMAGE) .

create-%: build  ## Create the specific package
	@echo 'Creating package ...'
	@mkdir -p $(PWD)/$(OUTPUT)
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
		--package $(OUTPUT) \
		--chdir /app \
		src/ext/modules/=/usr/share/php/extensions \
		README.md=/usr/share/doc/apm-agent-php/docs/README.md \
		src/ElasticApm=/usr/share/php/apm-agent-php \
		src/bootstrap_php_part.php=/usr/share/php/apm-agent-php/bootstrap_php_part.php
	@echo 'Creating sha512sum ...'
	@BINARY=$$(ls -1 $(PWD)/$(OUTPUT)/*.$*) ;\
	sha512sum $$BINARY > $$BINARY.sha512 ;\
	sed -i.bck "s#$(PWD)/$(OUTPUT)/##g" $$BINARY.sha512 ;\
	rm $(PWD)/$(OUTPUT)/*.bck

.PHONY: rpm
rpm: create-rpm  ## Create the rpm installer
	## TODO: fpm replaces - with _ in the version

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
info: rpm-info deb-info  ## Show the package metadata for all the installers

.PHONY: rpm-info
rpm-info:  ## Show the rpm package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.rpm) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: deb-info
deb-info:  ## Show the deb package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.deb) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: rpm-install
rpm-install:  ## Install the rpm installer to run some smoke tests
	@cd $(PWD)/packaging/test/centos/7.2 ;\
	docker build -t rpm-install . ;\
	cd -
	docker run --rm -v $(PWD):/src -w /src rpm-install

.PHONY: deb-install
deb-install:  ## Install the deb installer to run some smoke tests
	@cd $(PWD)/packaging/test/ubuntu ;\
	docker build -t deb-install . ;\
	cd -
	@docker run --rm -v $(PWD):/src -w /src deb-install
