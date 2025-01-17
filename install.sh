#!/bin/bash
SAIL_NETWORK="octiv-api-network"

docker network create $SAIL_NETWORK

# copy .env if required
if [ ! -f ./.env ]; then
    cp ./.env.example ./.env
fi

# load up .env
source ./.env

# run composer install
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs


./vendor/bin/sail up -d

# ./vendor/bin/sail php artisan key:generate
# ./vendor/bin/sail php artisan migrate:fresh
# ./vendor/bin/sail php artisan passport:install

# Create default bucket in minio
docker run -it --network=$SAIL_NETWORK --entrypoint=/bin/sh minio/mc -c "mc alias set minio http://localhost:9000 sail password && mc mb --ignore-existing minio/octiv"
