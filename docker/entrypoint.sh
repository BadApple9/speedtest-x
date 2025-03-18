#!/bin/bash

set -e
set -x

# Cleanup
#rm -rf /var/www/html/*

# Copy frontend files
cp /speedtest/*.js /var/www/html/
cp /speedtest/*.html /var/www/html/

cp -r /speedtest/backend/ /var/www/html/backend
cp -r /speedtest/chartjs/ /var/www/html/chartjs

ln -snf /var/www/html/backend/speedlogs /speedlogs

chown -R www-data /var/www/html/*

# Allow selection of Apache port for network_mode: host
if [ "$WEBPORT" != "80" ]; then
  sed -i "s/^Listen 80\$/Listen $WEBPORT/g" /etc/apache2/ports.conf
  sed -i "s/*:80>/*:$WEBPORT>/g" /etc/apache2/sites-available/000-default.conf
fi

if [ "$MAX_LOG_COUNT" != "100" ]; then
  sed -i "s/^const MAX_LOG_COUNT = 100/const MAX_LOG_COUNT = $MAX_LOG_COUNT/g" /var/www/html/backend/config.php
fi

if [ "$IP_SERVICE" != "ip.sb" ]; then
  sed -i "s/^const IP_SERVICE = 'ip.sb'/const IP_SERVICE = '$IP_SERVICE'/g" /var/www/html/backend/config.php
fi

if [ "$SAME_IP_MULTI_LOGS" != "false" ]; then
  sed -i "s/^const SAME_IP_MULTI_LOGS = false/const SAME_IP_MULTI_LOGS = $SAME_IP_MULTI_LOGS/g" /var/www/html/backend/config.php
fi

echo "Done, Starting APACHE"

# This runs apache
apache2-foreground