IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")
OUTPUT:=build/packages

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
		--url 'TBD' \
		--maintainer 'elastic' \
		--description 'TBD' \
		--license 'TBD' \
		--vendor 'elastic' \
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

.PHONY: deb
deb: create-deb

.PHONY: tar
tar: create-tar

.PHONY: version
version:
	@docker run --rm -ti $(IMAGE) --version

.PHONY: package
package: rpm deb tar

.PHONY: info
info: rpm-info deb-info

.PHONY: rpm-info
rpm-info:
	@docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $(OUTPUT)/$(NAME)-$(VERSION)-1.noarch.rpm
	@docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $(OUTPUT)/$(NAME)-$(VERSION)-1.noarch.rpm

.PHONY: deb-info
deb-info:
	@docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $(OUTPUT)/$(NAME)_$(VERSION)_all.deb
	@docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $(OUTPUT)/$(NAME)_$(VERSION)_all.deb

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
