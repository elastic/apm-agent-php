| :warning: :construction: **WARNING: This project is still in development. Please do not use in a production environment!** |
| --- |

# Elastic APM Agent for PHP

This is the official PHP agent for [Elastic APM](https://www.elastic.co/products/apm).

The PHP agent enables you to trace the execution of operations in your application, sending performance metrics and errors to the Elastic APM server.

:ballot_box_with_check: We'd love to get information about your setup to help us with prioritizing framework support – please take a minute to fill out [our survey](https://docs.google.com/forms/d/e/1FAIpQLSf8c3BJVMqaeuqpq-t3_Q4NilNcdsrzK1qJ4Qo9JpJslrmYzA/viewform).

If you have any feedback or questions,
please post them at the [Discuss forum](https://discuss.elastic.co/c/apm).

## Usage

This agent is a PHP extension that must be installed in your PHP environment.

See the [documentation](docs) for setup and configuration details.

## Contributing

See [contributing documentation](CONTRIBUTING.md).

### Local development

If you don't want to install any of the dependencies you might need to compile and install the library then you can use the Dockerfile.

```bash
docker build --tag test-php .

## To compile the library
docker run --rm -ti -v $(pwd):/app test-php

## To test the Library
docker run --rm -ti -v $(pwd):/app test-php make test

## To install the library
docker run --rm -ti -v $(pwd):/app test-php make install
```

To generate the packages then you can use the `packaging/Dockerfile`.

```bash
## To build the docker image that will be used later on for packaging the project
make -C packaging build

## To create the rpm package
make -C packaging rpm

## To create the deb package
make -C packaging deb

## To create all the packages that are supported
make -C packaging package
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

## License

Elastic APM PHP Agent is licensed under [Apache License, Version 2.0](https://www.apache.org/licenses/LICENSE-2.0.html)
