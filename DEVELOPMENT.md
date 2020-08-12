# Local development

## Build/package

### Using the docker approach

If you don't want to install any of the dependencies you might need to compile and install the library then you can use the Dockerfile.

```bash
## To prepare the build docker container
PHP_VERSION=7.2 make -f .ci/Makefile prepare

## To compile the library
PHP_VERSION=7.2 make -f .ci/Makefile build

## To test the library
PHP_VERSION=7.2 make -f .ci/Makefile test

## To generate the agent extension with the existing PHP API
PHP_VERSION=7.2 make -f .ci/Makefile generate-for-package

## To install with composer
PHP_VERSION=7.2 make -f .ci/Makefile composer

## To release given the GITHUB_TOKEN and TAG_NAME
GITHUB_TOKEN=**** TAG_NAME=v1.0.0 make -f .ci/Makefile release

## Help goal will provide further details
make -f .ci/Makefile help
```

_NOTE_: `PHP_VERSION` can be set to a different PHP version.

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
