# Elastic APM Agent for PHP

The official PHP agent for [Elastic APM](https://www.elastic.co/products/apm).
This agent is a PHP extension that must be installed in your PHP environment.

## Note

**This project is still in development. Please do not use in a production environment!**

## Usage

See the [documentation](docs) for setup and configuration details.

## Contributing

See the [contributing documentation](CONTRIBUTING.md)

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
