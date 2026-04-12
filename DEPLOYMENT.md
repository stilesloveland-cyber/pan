# 网盘系统部署说明

## 项目概述

本项目是一个简单的网盘系统，允许用户上传文件到服务器并随时下载。使用PHP和HTML/CSS/JavaScript实现，支持文件上传、下载和删除功能。

## 系统要求

- Linux服务器（Ubuntu 20.04+）
- Nginx
- PHP 7.4+
- PHP-FPM
- 足够的磁盘空间用于存储文件

## 部署步骤

### 1. 安装必要的软件

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装Nginx和PHP
sudo apt install nginx php-fpm php-cli php-mbstring php-json -y

# 检查PHP版本
sudo php -v

# 检查PHP-FPM状态
sudo systemctl status php8.1-fpm
```

### 2. 创建目录结构

```bash
# 创建网盘目录
sudo mkdir -p /var/www/html/pan/uploads

# 设置目录权限
sudo chown -R www-data:www-data /var/www/html/pan
sudo chmod -R 755 /var/www/html/pan
sudo chmod -R 775 /var/www/html/pan/uploads
```

### 3. 上传文件

将以下文件上传到 `/var/www/html/pan/` 目录：
- `index.html` - 前端界面
- `upload.php` - 文件上传处理
- `download.php` - 文件下载处理
- `files.php` - 获取文件列表
- `.htaccess` - 访问权限配置

### 4. 配置Nginx

```bash
# 创建Nginx配置文件
sudo nano /etc/nginx/sites-available/pan.xxclub.me
```

粘贴以下配置（根据实际情况修改）：

```nginx
server {
    listen 80;
    server_name pan.xxclub.me;

    root /var/www/html/pan;
    index index.html index.php;

    # 配置上传文件大小限制
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ =404;
    }

    # 处理PHP文件
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
    }

    # 禁止直接访问上传目录
    location /uploads/ {
        deny all;
        return 403;
    }

    # 访问日志和错误日志
    access_log /var/log/nginx/pan.access.log;
    error_log /var/log/nginx/pan.error.log;
}
```

### 5. 启用Nginx配置

```bash
# 启用配置
sudo ln -s /etc/nginx/sites-available/pan.xxclub.me /etc/nginx/sites-enabled/

# 检查配置语法
sudo nginx -t

# 重启Nginx
sudo systemctl restart nginx
```

### 6. 配置域名解析

在域名提供商的控制台中，将 `pan.xxclub.me` 域名的A记录指向您的服务器IP地址。

### 7. 测试部署

在浏览器中访问 `http://pan.xxclub.me`，应该能够看到网盘界面。

## 功能测试

1. **文件上传**：
   - 点击上传区域或拖拽文件到上传区域
   - 选择文件后点击上传
   - 查看文件是否出现在文件列表中

2. **文件下载**：
   - 在文件列表中点击文件的下载按钮
   - 检查文件是否成功下载

3. **文件删除**：
   - 在文件列表中点击文件的删除按钮
   - 确认删除后，检查文件是否从列表中消失

## 安全设置

1. **文件大小限制**：
   - 默认限制为100MB，可在 `upload.php` 中修改 `$maxFileSize` 变量

2. **文件类型限制**：
   - 默认允许所有文件类型，可在 `upload.php` 中修改 `$allowedExtensions` 数组

3. **防止直接访问上传目录**：
   - 通过Nginx配置和.htaccess文件禁止直接访问上传目录

4. **文件名处理**：
   - 上传文件时会生成唯一文件名，避免覆盖和恶意文件攻击

## 维护建议

1. **定期清理文件**：
   - 定期检查并清理不需要的文件，避免服务器空间不足

2. **备份重要文件**：
   - 定期备份上传的重要文件

3. **监控服务器空间**：
   - 定期检查服务器磁盘空间使用情况

4. **更新系统和软件**：
   - 定期更新服务器系统和软件，确保安全性

## 故障排查

### 上传失败
- 检查PHP上传限制：修改 `php.ini` 文件中的 `upload_max_filesize` 和 `post_max_size`
- 检查Nginx上传限制：修改Nginx配置文件中的 `client_max_body_size`
- 检查目录权限：确保 `uploads` 目录有写入权限

### 下载失败
- 检查文件是否存在：确认文件在 `uploads` 目录中
- 检查文件权限：确保文件有读取权限
- 检查Nginx配置：确保PHP文件处理正确

### 页面无法访问
- 检查Nginx配置：确保域名配置正确
- 检查域名解析：确认域名已正确解析到服务器IP
- 检查防火墙：确保80端口已开放

## 技术支持

如果遇到部署问题，请检查以下几点：
- 服务器环境是否满足要求
- 配置文件是否正确
- 文件权限是否设置正确
- 网络连接是否正常

如果问题仍然存在，请参考Nginx和PHP的官方文档。