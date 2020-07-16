IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages

GIT_SHA ?= $(shell git rev-parse HEAD || echo "unknown")
BUILD_NUMBER ?= 0
RELEASE_TAG ?= SNAPSHOT.$(shell date '+%Y%m%d').$(BUILD_NUMBER)
RELEASE_EPOCH ?= $(shell date '+%s')

.PHONY: build
build:
	@docker build -t $(IMAGE) .

create-%: build
	@echo 'Creating package ...'
	@mkdir -p $(PWD)/$(OUTPUT)
	@docker run --rm \
		-v $(PWD):/app \
		-w /app $(IMAGE) \
		--input-type dir \
		--output-type $* \
		--name $(NAME) \
		--iteration "$(RELEASE_TAG)" \
		--epoch "$(RELEASE_EPOCH)" \
		--version $(VERSION) \
		--architecture all \
		--url 'TBD' \
		--maintainer 'elastic' \
		--description 'TBD' \
		--license 'TBD' \
		--vendor 'elastic' \
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
rpm: create-rpm
	## TODO: fpm replaces - with _ in the version

.PHONY: deb
deb: create-deb

.PHONY: tar
tar: create-tar

.PHONY: version
version:
	@docker run --rm $(IMAGE) --version

.PHONY: package
package: rpm deb tar

.PHONY: info
info: rpm-info deb-info

.PHONY: rpm-info
rpm-info:
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.rpm) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: deb-info
deb-info:
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.deb) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: rpm-install
rpm-install:
	echo 'TBD'

.PHONY: deb-install
deb-install:
	@docker build -t deb-install -f $(PWD)/packaging/test/ubuntu/Dockerfile . > /dev/null
	@echo 'Connecting to the docker ubuntu container... type the below commands'
	@echo 'dpkg -i build/packages/*.deb'
	@echo 'cp /usr/share/php/extensions/elastic_apm.so   /usr/local/lib/php/extensions/no-debug-non-zts-20190902/'
	@echo 'php-fpm'
	@echo ''
	@docker run --rm -ti -v $(PWD):/src -w /src deb-install /bin/bash
