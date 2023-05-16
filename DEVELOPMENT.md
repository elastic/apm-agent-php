# Local development

## Build/package

### Using the docker approach

If you don't want to install any of the dependencies you might need to compile and install the library then you can use the Dockerfile.

```bash
## To prepare the build docker container
PHP_VERSION=7.2 make -f .ci/Makefile prepare

## To compile the library
PHP_VERSION=7.2 make -f .ci/Makefile build

## To run the unit test and static check
PHP_VERSION=7.2 make -f .ci/Makefile static-check-unit-test

## To generate the agent extension with the existing PHP API
PHP_VERSION=7.2 make -f .ci/Makefile generate-for-package

## To run the component tests
PHP_VERSION=7.2 make -f .ci/Makefile component-test

## To release given the GITHUB_TOKEN and TAG_NAME, it creates a draft release
GITHUB_TOKEN=**** TAG_NAME=v1.0.0 make -f .ci/Makefile draft-release

## To generate the agent extension with the existing PHP API for alpine
PHP_VERSION=7.2 DOCKERFILE=Dockerfile.alpine make -f .ci/Makefile generate-for-package

## Help goal will provide further details
make -f .ci/Makefile help
```

_NOTE_: 

* `PHP_VERSION` can be set to a different PHP version.
* Alpine specific binaries can be generated if using `DOCKERFILE=Dockerfile.alpine`

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
