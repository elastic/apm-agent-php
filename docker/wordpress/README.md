# Load Testing with Wordpress

Simulate real load on a wordpress site, while randomly throwing errors in the wp app.

See: http://127.0.0.1:8000/info.php

## Configuration
Please go to `./data/elastic_apm.ini` and add the correct values to point to the APM server (e.g.).

## Run the Simulator
```
docker-compose up
```
