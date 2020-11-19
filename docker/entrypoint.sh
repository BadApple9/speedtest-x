#!/bin/bash

set -e
set -x

# Cleanup
#rm -rf /var/www/html/*

# Copy frontend files
cp /speedtest/*.js /var/www/html/
cp /speedtest/*.html /var/www/html/

cp -r /speedtest/backend/ /var/www/html/backend

ln -snf /var/www/html/backend/speedlogs /speedlogs

chown -R www-data /var/www/html/*

# Allow selection of Apache port for network_mode: host
if [ "$WEBPORT" != "80" ]; then
  sed -i "s/^Listen 80\$/Listen $WEBPORT/g" /etc/apache2/ports.conf
  sed -i "s/*:80>/*:$WEBPORT>/g" /etc/apache2/sites-available/000-default.conf
fi

echo "Done, Starting APACHE"

# This runs apache
apache2-foreground