#!/bin/sh
set -e

mkdir -p /var/www/uploads/public
mkdir -p /var/www/uploads/shares
mkdir -p /var/www/uploads/cache
mkdir -p /var/www/uploads/cache/chunks
mkdir -p /var/www/uploads/thumbs

chown -R www-data:www-data /var/www/uploads
chmod -R 775 /var/www/uploads

chown -R www-data:www-data /var/www/html/pan
chmod -R 755 /var/www/html/pan

if [ ! -f /var/www/uploads/users.json ]; then
    echo '{}' > /var/www/uploads/users.json
    chown www-data:www-data /var/www/uploads/users.json
fi

mkdir -p /run/nginx

exec /usr/bin/supervisord -c /etc/supervisord.conf
