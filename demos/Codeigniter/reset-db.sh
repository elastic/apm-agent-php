#!/bin/bash

cp docker/mysql/based.sql .
rm -rf docker/mysql
mkdir docker/mysql
mv based.sql docker/mysql/