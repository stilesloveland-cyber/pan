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

# 由 PHP 的 initSystem() 自动创建 users.json（含默认 admin 用户）
# 和 settings.json（含默认配置），此处无需预创建

mkdir -p /run/nginx

exec /usr/bin/supervisord -c /etc/supervisord.conf
