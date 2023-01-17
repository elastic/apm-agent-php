
SHELL=/bin/bash -o pipefail
IMAGE := test-php
PHP_VERSION ?= 7.2
DOCKERFILE ?= Dockerfile
LOOPS ?= 50
SUFFIX :=
ADD_LIBUNWIND_DEPENDENCY_BUILD_ARG_OPT:=
ifeq ($(DOCKERFILE), Dockerfile.alpine)
    ## This is only required to tag the docker images used for building/testing this project
    SUFFIX := -alpine
    ADD_LIBUNWIND_DEPENDENCY_BUILD_ARG_OPT:=--build-arg ADD_LIBUNWIND_DEPENDENCY=${ADD_LIBUNWIND_DEPENDENCY}
endif

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
		${ADD_LIBUNWIND_DEPENDENCY_BUILD_ARG_OPT} \
		--tag $(IMAGE):${PHP_VERSION}$(SUFFIX) \
		-f ${DOCKERFILE} .

.PHONY: build
build: prepare  ## Build the project
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}$(SUFFIX)

.PHONY: static-check-unit-test
static-check-unit-test: prepare  ## Test the static check and unit tests
	# docker as the current user
	docker run --rm -t \
		-e CHOWN_RESULTS_UID=$(CURRENT_UID) -e CHOWN_RESULTS_GID=$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}$(SUFFIX) \
		/app/.ci/static-check-unit-test.sh

.PHONY: generate-for-package
generate-for-package: prepare  ## Generate the agent extension for the package
	# docker as the current user
	docker run --rm -t \
		-v $(PWD):/app \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		$(IMAGE):${PHP_VERSION}$(SUFFIX) \
		/app/.ci/generate-for-package.sh

.PHONY: interactive
interactive: prepare  ## Run an interactive docker shell
	docker run -it --rm \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION} \
		/bin/bash

.PHONY: component-test
component-test: prepare  ## Run component-test
	# docker as root to install the extension
	docker run -t --rm \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}$(SUFFIX) \
		sh -c '/app/.ci/component-test.sh'

.PHONY: loop
loop:  ## Bump the version given VERSION
	.ci/loop.sh "$(LOOPS)" "${DOCKERFILE}" "${PHP_VERSION}"

.PHONY: previous-version
previous-version:  ## Fetch the current version
	@grep '\(VERSION = \)' src/ElasticApm/ElasticApm.php | \
	sed "s#.*= '\(.*\)';#\1#g"

.PHONY: bump-version
bump-version:  ## Bump the version given VERSION
ifndef VERSION
	@echo "Please set VERSION in the environment to bump the version"
	exit 1
endif
	.ci/bump_version.sh

.PHONY: prepare-changelog
prepare-changelog:  ## Prepare the dependencies to run the changelog
	@docker build --tag gren -f .ci/docker/gren/Dockerfile .

.PHONY: changelog
changelog:  ## Create the changelog for the given version
ifndef GITHUB_TOKEN
	@echo "Please set GITHUB_TOKEN in the environment to generate the changelog"
	exit 1
endif
ifndef TAG_NAME
	@echo "Please set TAG_NAME in the environment to create the changelog"
	exit 1
endif
ifndef PREVIOUS_TAG
	@echo "Please set PREVIOUS_TAG in the environment to create the changelog"
	exit 1
endif
	docker run --rm -t \
		--volume "$(PWD)":/app \
		--workdir /app \
		--env GITHUB_TOKEN=$(GITHUB_TOKEN) \
		--env TAG_NAME=$(TAG_NAME) \
		--env PREVIOUS_TAG=$(PREVIOUS_TAG) \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		gren

.PHONY: draft-release
draft-release: validate-tag-name validate-github-token  ## Run a draft release given the GITHUB_TOKEN and TAG_NAME
	gh \
		release \
		create \
		"$(TAG_NAME)" \
		--draft \
		--title "$(TAG_NAME)" \
		--notes "For more information, please see the [changelog](https://www.elastic.co/guide/en/apm/agent/php/current/release-notes.html)." \
		--repo elastic/apm-agent-php \
		build/packages/*.*

.PHONY: get-draft-release
get-draft-release: validate-tag-name  ## Get the draft release id for the given TAG_NAME
	@gh api repos/{owner}/{repo}/releases | jq --arg v "$(TAG_NAME)" '.[]|select(.draft)|select(.tag_name == $$v)|.id'

.PHONY: download-verify
download-verify:  ## Download the assets for the given draft release and verify their signature
	.ci/download_release_and_verify_checksum.sh "${TAG_NAME}" "${ORIGINAL_PACKAGES_LOCATION}"

validate-github-token:
ifndef GITHUB_TOKEN
	@echo "Please set GITHUB_TOKEN in the environment to perform a release"
	exit 1
endif

validate-tag-name:
ifndef TAG_NAME
	@echo "Please set TAG_NAME in the environment to perform a release"
	exit 1
endif
