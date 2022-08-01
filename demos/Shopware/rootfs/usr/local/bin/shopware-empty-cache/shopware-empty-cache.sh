#!/usr/bin/env bash

# In Shopware 6 Standard the cache is not automatically emptied.
# In the long run, this can lead to the store installation becoming larger and
# larger and requiring more and more memory on the server.
# See: https://docs.shopware.com/en/shopware-6-en/configuration/caches-indexes
#
# This script simply clears the cache for every given interval.
# Interval is every 24 hours.

SHOPWARE_ROOT_DIRECTORY="${SHOPWARE_ROOT_DIRECTORY:-/var/www/html}"
SHOPWARE_EMPTY_CACHE_INTERVAL="${SHOPWARE_EMPTY_CACHE_INTERVAL:-86400}"

if [ ${SHOPWARE_AUTOMATICALLY_EMPTY_CACHE_ENABLED} == "false" ]; then
  exit 0; # Not enabled, so exit without error
fi

echo "Clearing Shopware cache every ${SHOPWARE_EMPTY_CACHE_INTERVAL} seconds"

while [ ${SHOPWARE_AUTOMATICALLY_EMPTY_CACHE_ENABLED} == "true" ]; do
  echo "Clearing Shopware cache"
  rm -rf "${SHOPWARE_ROOT_DIRECTORY}/var/cache/*"
  sleep "${SHOPWARE_EMPTY_CACHE_INTERVAL}"
done

exit 1; # Should not be reached
