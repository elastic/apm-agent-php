IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")

.PHONY: build
build:
	docker build -t $(IMAGE) .

create-%:
	mkdir -p $(PWD)/build/packages || true
	docker run --rm \
		-v $(PWD):/app \
		-w /app $(IMAGE) \
		-s dir \
		-t $* \
		-n $(NAME) \
		-v $(VERSION) \
		--url 'TBD' \
		--maintainer 'elastic' \
		--description 'TBD' \
		--license 'TBD' \
		--vendor 'elastic' \
		-p build/packages \
		-C /app \
		src/ext/modules/=/usr/share/php/extensions \
		README.md=/usr/share/doc/apm-agent-php/docs/README.md \
		src/ElasticApm=/usr/share/php/apm-agent-php \
		src/bootstrap_php_part.php=/usr/share/php/apm-agent-php/bootstrap_php_part.php

.PHONY: rpm
rpm: create-rpm

.PHONY: deb
deb: create-deb

.PHONY: tar
tar: create-tar

.PHONY: version
version:
	docker run --rm -ti $(IMAGE) --version

.PHONY: package
package: rpm deb tar

.PHONY: rpm-info
rpm-info:
	docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $(NAME)-$(VERSION)-1.x86_64.rpm
	docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qlp $(NAME)-$(VERSION)-1.x86_64.rpm

.PHONY: deb-info
deb-info:
	docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $(NAME)_$(VERSION)_amd64.deb
	docker run --rm -ti -v $(PWD):/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) -c $(NAME)_$(VERSION)_amd64.deb

.PHONY: rpm-install
rpm-install:
	echo 'TBD'

.PHONY: deb-install
deb-install:
	echo 'TBD'
