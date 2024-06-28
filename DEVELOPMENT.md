# Local development
## Build and package

### Using the docker approach

If you don't want to install any of the dependencies you might need to compile and install the library then you can use the Dockerfile.

```bash
## To compile the library for all supported PHP releases for glibc Linux distributions for x86_64 architecture
BUILD_ARCHITECTURE=linux-x86-64 make -f .ci/Makefile build

## To compile the library for all supported PHP releases for musl libc Linux distributions for x86_64 architecture
BUILD_ARCHITECTURE=linuxmusl-x86-64 make -f .ci/Makefile build

## To compile the library for all supported PHP releases for glibc Linux distributions for aarch64 (ARMv8) architecture. This build is not officially supported.
BUILD_ARCHITECTURE=linux-arm64 make -f .ci/Makefile build

## To compile the library for all supported PHP releases for musl libc Linux distributions for aarch64 (ARMv8) architecture. This build is not officially supported.
BUILD_ARCHITECTURE=linuxmusl-arm64 make -f .ci/Makefile build

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

### Local development with direct calls to cmake inside docker container
\
If you want to make local development easier, you can call build directly via docker commands.\
All you need to do is mount the path with the sources to the `/source` directory inside the container.\
\
To further speed up build, it is a good idea to mount the local conan cache so that it uses a folder outside the container. The location is arbitrary, but should preferably point outside the source folder. This is optional, if you omit it, conan will place the cache inside the container in the `/home/build/.conan` folder.
\
Make sure to always use the latest version of the image you are using for the build. You can find the current version inside the `.ci/Makefile` in `build:` section

```bash
# this will build agent for linux-x86-64
docker run -v "/path/to/your/apm-agent-php:/source"  -v "/path/to/your/conan:/home/build/.conan:rw"  -w /source/agent/native elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-linux-x86-64-0.0.2  sh -c  "cmake --preset linux-x86-64-release && cmake --build --preset linux-x86-64-release"
# this will build agent for linuxmusl-x86-64
docker run -v "/path/to/your/apm-agent-php:/source"  -v "/path/to/your/conan:/home/build/.conan:rw"  -w /source/agent/native elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-linuxmusl-x86-64-0.0.2  sh -c  "cmake --preset linuxmusl-x86-64-release && cmake --build --preset linuxmusl-x86-64-release"

```

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
docker compose build
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
docker compose build
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

## Building and publishing conan artifacts

First, please remember that you need to perform all steps inside a proper docker container. This will ensure that each package receives the same unique identifier (and package will be used in CI build).

The following are instructions for building and uploading artifacts for the linux-x86-64 architecture

Execution of container. All you need to do here is to use latest container image revision and replace path to your local repository.
```bash
docker run -ti -v /your/forked/repository/path/apm-agent-php:/source -w /source/agent/native elasticobservability/apm-agent-php-dev:native-build-gcc-12.2.0-linux-x86-64-0.0.2 bash
```

In container environment we need to configure project - it will setup build environment, conan environment and build all required conan dependencies
```bash
cmake --preset linux-x86-64-release
```

Now we need to load python virtual environment created in previous step. This will enable path to conan tool.
```bash
source _build/linux-x86-64-release/python_venv/bin/activate
```

You can list all local conan packages simply by calling:
```bash
conan search
```

it should output listing similar to this:
```bash
recipes:

boost/1.82.0
cmocka/1.1.5
gtest/1.13.0
libcurl/8.0.1
libiconv/1.17
libssh2/1.11.0
libunwind/1.6.2
libxml2/2.9.9
openssl/3.1.3
php-headers-72/1.0@elastic/local
php-headers-73/1.0@elastic/local
php-headers-74/1.0@elastic/local
php-headers-80/1.0@elastic/local
php-headers-81/1.0@elastic/local
php-headers-82/1.0@elastic/local
pkgconf/1.9.3
pkgconf/1.9.3@elastic/local
sqlite3/3.29.0
xz_utils/5.4.4
zlib/1.3

```

Now you need to login into conan as elastic user. Package upload is allowed only for mainteiners.
```bash
conan user -r ElasticConan user@elastic.co
```

Now you can upload package to conan artifactory.

`--all` option will upload all revisions of `php-headers-72` you have stored in your .conan/data folder (keep it in mind if you're sharing conan cache folder between containers). You can remove it, then conan will ask before uploading each version.
```bash
conan upload php-headers-72 --all -r=ElasticConan
```

Now you can check conan artifactory for new packages here:
https://artifactory.elastic.dev/ui/repos/tree/General/apm-agent-php-dev

and in "raw" format here:
https://artifactory.elastic.dev/ui/native/apm-agent-php-dev/

