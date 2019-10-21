#!/bin/sh

echo "starting load generating in 30 seconds .."
sleep 10
echo "> starting load generating in 20 seconds .."
sleep 10
echo "> starting load generating in 10 seconds .."
sleep 7
echo "> starting load generating in 3 seconds .."
sleep 1
echo "> in 2 seconds .."
sleep 1
echo "> in 1 second .."
sleep 1
/usr/local/bin/python /app/runner.py
