IMAGE := test-php
PHP_VERSION ?= 7.2
DOCKERFILE ?= Dockerfile
SUFFIX := 
ifeq ($(DOCKERFILE), Dockerfile.alpine)
    ## This is only required to tag the docker images used for building/testing this project
    SUFFIX := -alpine
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
		--tag $(IMAGE):${PHP_VERSION}$(SUFFIX) \
		-f ${DOCKERFILE} .

.PHONY: build
build: prepare  ## Build the project
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}$(SUFFIX)

.PHONY: test
test: prepare  ## Test the project
	# docker as the current user
	docker run --rm -t \
		-u $(CURRENT_UID):$(CURRENT_GID) \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}$(SUFFIX) \
		make test

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

.PHONY: composer
composer: prepare  ## Run composer
	# docker as root to install the extension
	docker run -t --rm \
		-v $(PWD):/app \
		$(IMAGE):${PHP_VERSION}$(SUFFIX) \
		sh -c '/app/.ci/composer.sh'

.PHONY: bump-version
bump-version:  ## Bump the version given VERSION
ifndef VERSION
	@echo "Please set VERSION in the environment to bump the version"
	exit 1
endif
	.ci/bump_version.sh

.PHONY: changelog
changelog:  ## Generate the changelog
ifndef GITHUB_TOKEN
	@echo "Please set GITHUB_TOKEN in the environment to perform a release"
	exit 1
endif
ifndef PREVIOUS_TAG_NAME
	@echo "Please set PREVIOUS_TAG_NAME in the environment to perform a release"
	exit 1
endif
	@docker run -t --rm \
		-v "$(PWD)":/usr/local/src \
		-w /usr/local/src \
		ferrarimarco/github-changelog-generator --token ${GITHUB_TOKEN} --since-tag ${PREVIOUS_TAG_NAME}

.PHONY: release
release:  ## Run a release given the GITHUB_TOKEN and TAG_NAME
ifndef GITHUB_TOKEN
	@echo "Please set GITHUB_TOKEN in the environment to perform a release"
	exit 1
endif
ifndef TAG_NAME
	@echo "Please set TAG_NAME in the environment to perform a release"
	exit 1
endif
	@docker run --rm -t \
		--env GITHUB_TOKEN=$(GITHUB_TOKEN) \
		--env TAG_NAME=$(TAG_NAME) \
		--volume "$(PWD)":/app \
		--workdir /app \
		golang:1.14.7 \
		/app/.ci/release.sh
