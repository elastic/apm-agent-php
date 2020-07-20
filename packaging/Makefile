IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages

GIT_SHA ?= $(shell git rev-parse HEAD || echo "unknown")

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

.PHONY: apk
apk: create-apk

.PHONY: deb
deb: create-deb

.PHONY: rpm
rpm: create-rpm
	## TODO: fpm replaces - with _ in the version

.PHONY: tar
tar: create-tar

.PHONY: version
version:
	@docker run --rm $(IMAGE) --version

.PHONY: package
package: apk deb rpm tar

.PHONY: info
info: rpm-info deb-info apk-info

.PHONY: apk-info
apk-info:
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.apk) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /sbin/apk $(IMAGE) manifest $$BINARY

.PHONY: deb-info
deb-info:
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.deb) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $$BINARY

.PHONY: rpm-info
rpm-info:
	@cd $(PWD) ;\
	BINARY=$$(ls -1 $(OUTPUT)/*.rpm) ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $$BINARY ;\
	docker run --rm -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $$BINARY

.PHONY: deb-install
deb-install:
	@cd $(PWD)/packaging/test/ubuntu ;\
	docker build -t deb-install . ;\
	cd -
	@docker run --rm -v $(PWD):/src -w /src deb-install

.PHONY: rpm-install
rpm-install:
	echo 'TBD'

