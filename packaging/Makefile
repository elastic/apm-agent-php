IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages
PHP_AGENT_DIR:=/opt/elastic/apm-agent-php
PHP_VERSION?=7.2
GIT_SHA?=$(shell git rev-parse HEAD || echo "unknown")
RELEASE_VERSION?=

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
	@docker build -t $(IMAGE) .

create-%: prepare  ## Create the specific package
	@echo "Creating package $* ..."
	@mkdir -p $(PWD)/$(OUTPUT)
	@docker run --rm \
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
	@docker run --rm $(IMAGE) --version

.PHONY: package
package: apk deb rpm tar  ## Create all the installers

.PHONY: info
info: apk-info deb-info rpm-info tar-info  ## Show the package metadata for all the installers

.PHONY: apk-info
apk-info:  ## Show the apk package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.apk) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /sbin/apk $(IMAGE) manifest $$BINARY

.PHONY: deb-info
deb-info:  ## Show the deb package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.deb) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: rpm-info
rpm-info:  ## Show the rpm package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.rpm) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: tar-info
tar-info:  ## Show the tar package metadata
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.tar) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/tar $(IMAGE) -tvf $$BINARY

.PHONY: prepare-apk
prepare-apk:  ## Build the docker image for the apk smoke tests
	@cd $(PWD)/packaging/test/alpine ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . ;\
	cd -

.PHONY: prepare-deeb
prepare-deb:  ## Build the docker image for the deb smoke tests
	@cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . ;\
	cd -

.PHONY: prepare-tar
prepare-tar:  ## Build the docker image for the tar smoke tests
	@cd $(PWD)/packaging/test/ubuntu ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . ;\
	cd -

.PHONY: prepare-rpm
prepare-rpm:  ## Build the docker image for the rpm smoke tests
	@cd $(PWD)/packaging/test/centos ;\
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $@ . ;\
	cd -

.PHONY: apk-install
apk-install: prepare-apk  ## Install the apk installer to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src prepare-apk

.PHONY: deb-install
deb-install: prepare-deb  ## Install the deb installer to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e TYPE=deb prepare-deb

.PHONY: tar-install
tar-install: prepare-tar  ## Install the tar installer to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e TYPE=tar prepare-tar

.PHONY: rpm-install
rpm-install: prepare-rpm  ## Install the rpm installer to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e TYPE=rpm prepare-rpm

.PHONY: install
install: apk-install deb-install rpm-install tar-install  ## Install all the distributions

.PHONY: apk-install-release
apk-install-release: prepare-apk  ## Install the apk installer from a given release to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e VERSION=$(RELEASE_VERSION) -e TYPE=release prepare-apk

.PHONY: deb-install-release
deb-install-release: prepare-deb  ## Install the deb installer from a given release to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e VERSION=$(RELEASE_VERSION) -e TYPE=release prepare-deb

.PHONY: rpm-install-release
rpm-install-release: prepare-rpm  ## Install the rpm installer from a given release to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e VERSION=$(RELEASE_VERSION) -e TYPE=release prepare-rpm

.PHONY: tar-install-release
tar-install-release: prepare-tar  ## Install the tar installer from a given release to run some smoke tests
	@docker run --rm -v $(PWD):/src -w /src -e VERSION=$(RELEASE_VERSION) -e TYPE=release-tar prepare-tar

.PHONY: install-release
install-release: apk-install-release deb-install-release rpm-install-release tar-install-release  ## Install all the distributions for a given release
