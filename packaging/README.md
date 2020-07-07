This folder contains all the recipes to generate a package for different systems, such as debian, centos and so on.

For such it uses [FPM](https://github.com/jordansissel/fpm/wiki)

## How to use it 


```bash
## To build the docker image that will be used later on for packaging the project
make build

## To create the rpm package
make rpm

## To create the deb package
make deb

## To create all the packages that are supported
make package

```