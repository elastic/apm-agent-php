#!/bin/sh

cd /var/elastic_apm

phpize
./configure --enable-elastic_apm
make clean
make
make install
