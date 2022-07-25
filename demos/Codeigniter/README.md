# Dockerize Codeigniter
Docker compose to setup nginx, php and mysql for codeigniter 4.0.3.  

## Directory Structure
```sh
codeigniter-docker
├── docker
│   ├── mysql
│   │   └── based.sql
│   ├── nginx
│   │   └── nginx.conf
│   └── php
│       └── Dockerfile
├── docker-compose.yml
└── reset-db.sh
```
## Images
1. nginx → nginx:latest
2. php → php:7.4-fpm
3. mysql → mysql:5.7

## Setup Initial DB  
Copy your sql file to *docker/mysql* with *based.sql* as a file name.  

## Deployment using Docker
1. Deploy nginx, php-fpm, and mysql using docker-compose
    ```bash
    docker-compose up -d
    ```
2. Stop all container
    ```bash
    docker stop leru_nginx leru_mysql leru_php_fpm
    ```
3. Remove all container
    ```bash
    docker rm leru_nginx leru_mysql leru_php_fpm
    ```
4. Remove php-fpm image
    ```bash
    docker rmi codeigniter-docker_php_fpm
    ```
5. Reset mysql data
    ```bash
    bash reset-db.sh
    ```
