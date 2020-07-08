IMAGE:=php-packaging
NAME:=apm-agent-php
VERSION?=$(shell grep 'VERSION' ../src/ElasticApm/ElasticApm.php | cut -d= -f2 | tr -d " " | sed "s/'\(.*\)'.*/\1/g")

.PHONY: build
build:
	docker build -t $(IMAGE) .

create-%:
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
		-C /app/src/ext/modules

.PHONY: rpm
rpm: create-rpm

.PHONY: deb
deb: create-deb

.PHONY: version
version:
	docker run --rm -ti $(IMAGE) --version

.PHONY: package
package: rpm deb

.PHONY: rpm-info
rpm-info:
	docker run --rm -ti -v $(PWD)/..:/app -w /app --entrypoint /usr/bin/rpm $(IMAGE) -qip $(NAME)-$(VERSION)-1.x86_64.rpm

.PHONY: deb-info
deb-info:
	docker run --rm -ti -v $(PWD)/..:/app -w /app --entrypoint /usr/bin/dpkg $(IMAGE) --info $(NAME)_$(VERSION)_amd64.deb

.PHONY: rpm-install
rpm-install:
	echo 'TBD'

.PHONY: deb-install
deb-install:
	echo 'TBD'
