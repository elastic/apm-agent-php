#!/bin/sh

cd /var/elasticapm

phpize
./configure --enable-elasticapm
make clean
make
make install
