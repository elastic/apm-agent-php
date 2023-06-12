# Local development
## Build and package

### Using the docker approach

If you don't want to install any of the dependencies you might need to compile and install the library then you can use the Dockerfile.

```bash
## To compile the library for all supported PHP releases for glibc Linux distributions
BUILD_ARCHITECTURE=linux-x86-64 make -f .ci/Makefile build

## To compile the library for all supported PHP releases for musl libc Linux distributions
BUILD_ARCHITECTURE=linuxmusl-x86-64 make -f .ci/Makefile build

## To prepare the docker container for testing
PHP_VERSION=7.2 make -f .ci/Makefile prepare

## To run PHP tests of native extension (phpt)
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=7.2  make -f .ci/Makefile run-phpt-tests

## To run the unit test and static check
PHP_VERSION=7.2 make -f .ci/Makefile static-check-unit-test

## To run the component tests
BUILD_ARCHITECTURE=linux-x86-64 PHP_VERSION=7.2 make -f .ci/Makefile component-test

## To release given the GITHUB_TOKEN and TAG_NAME, it creates a draft release
GITHUB_TOKEN=**** TAG_NAME=v1.0.0 make -f .ci/Makefile draft-release

## Help goal will provide further details
make -f .ci/Makefile help
```

_NOTE_: 

* `PHP_VERSION` can be set to a different PHP version.
* For testing of Alpine specific binaries you must run "prepare" task with `DOCKERFILE=Dockerfile.alpine` environment variable set to build proper docker image. 

To generate the packages then you can use the `packaging/Dockerfile`, see the below commands:

| :warning: :construction: **WARNING: The packaging stage is still in development!** |
| --- |

```bash
## To prepare the docker image that will be used later on for packaging the project
make -C packaging prepare

## To create the rpm package
make -C packaging rpm

## To create the deb package
make -C packaging deb

## To create all the packages that are supported
make -C packaging package

## To list the metadata info of the above generated packages
make -C packaging info

## To test the installation in debian
make -C packaging deb-install

## To test the installation for a given release in debian using the downloaded binary
RELEASE_VERSION=0.1 make -C packaging deb-install-release-github

## To test the installation and uninstallation for all the packages
make -C packaging lifecycle-testing

## To test the agent upgrade from a given release version with the existing generated package
RELEASE_VERSION=1.0.0 make -C packaging rpm-agent-upgrade-testing

## Help goal will provide further details
make -C packaging help
```

_NOTE_: current implementation requires to use `make -C packaging <target>` since the workspace
        is mounted as a volume.

## Documentation

To build the documentation for this project you must first clone the [`elastic/docs` repository](https://github.com/elastic/docs/). Then run the following commands:

```bash
# Set the location of your repositories
export GIT_HOME="/<fullPathTYourRepos>"

# Build the PHP documentation
$GIT_HOME/docs/build_docs --doc $GIT_HOME/apm-agent-php/docs/index.asciidoc --chunk 1 --open
```

# CI
## How to run Jenkins build+test CI pipeline with custom log level
By default build+test CI pipeline runs with the default log level (for both agent and tests infrastructure).
Jenkins build parameters can be used to run build+test CI pipeline with a custom log level.
- Go to classic (i.e., not Blue Ocean) Jenkins' UI
- Make sure it's a UI page for PR/branch and not for a particular build (i.e., `https://apm-ci.elastic.co/job/apm-agent-php/job/apm-agent-php-mbp/job/PR-###/` and not `https://apm-ci.elastic.co/job/apm-agent-php/job/apm-agent-php-mbp/job/PR-###/#/`)
- Go to `Build with Parameters`
- Select log level for agent and/or tests' infrastructure
- Click `Build`


# Updating docker images used for building and testing
## Building and updating docker images used to build the agent extension

If you want to update images used to build native extension, you need to go into `agent/native/building/dockerized` folder and modify Dockerfile stored in images folder. In this moment, there are two Dockerfiles:
`Dockerfile_musl` for Linux x86_64 with musl libc implementation and `Dockerfile_glibc` for all other x86_64 distros with glibc implementation. 
Then you need to increment image version in `docker-compose.yml`. Remember to update Dockerfiles for all architectures, if needed. To build new images, you just need to call:
```bash
docker-compose build
```
It will build images for all supported architectures. As a result you should get summary like this:
```bash
Successfully tagged elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-linux-x86-64-0.0.1
Successfully tagged elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-linuxmusl-x86-64-0.0.1
```

To test freshly built images, you need to udate image version in `build:` task in ```.ci/Makefile``` and run build task described in [Build/package](#build-and-package)
)

\
If everything works as you expected, you just need to push new image to dockerhub by calling:
```bash
docker push elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-linux-x86-64-0.0.1
```

## Building and updating docker images used to execute tests
If you want to update images used for testing, you need to go into `packaging/test` folder and modify Dockerfiles stored in folders:
|Folder name|Usage|
|-|-|
|alpine|Testing of apk packages|
|centos|Testing of rpm packages|
|ubuntu|Testing of deb packages|
|ubuntu/apache|Tesing of deb packages with Apache/mod_php| 
|ubuntu/fpm|Tesing of deb packages with Apache/php-fpm| 

Then you need to increment image version in `docker-compose.yml`.\
To build new images, you just need to call:
```bash
docker-compose build
```
It will build and tag images for all test scenarios. As a result you should get summary like this:
```bash
Successfully tagged elasticobservability/apm-agent-php-dev:packages-test-apk-php-7.2-0.0.1
...
```

\
To test freshly built images, you need to udate images version in ```packaging/Makefile```. Note that one particular image can be specified multiple times inside this file. Please check carefully that you have updated all the places where the image has been used

\
If everything works as you expected, you just need to push new image to dockerhub by calling:
```bash
docker push elasticobservability/apm-agent-php-dev:packages-test-apk-php-7.2-0.0.1
```
It should be done for all images you modified.
