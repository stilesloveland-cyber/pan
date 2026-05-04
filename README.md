# WPAN 个人网盘系统

一个基于 PHP + Nginx 的个人网盘系统，支持多用户、文件管理、在线预览、分享等功能。

## 目录结构

```
/var/www/html/pan/
├── index.html          # 前端界面（登录/上传/管理/预览）
├── functions.php       # 公共函数库
├── upload.php          # 上传、注册、登录、改密、删除
├── files.php           # 获取文件列表和空间信息
├── download.php        # 文件下载
├── preview.php         # 文件在线预览
├── share.php           # 生成和访问分享链接
└── .htaccess           # Apache 访问限制

/var/www/uploads/       # 存储目录（Web根目录外）
├── users.json          # 用户信息
├── public/             # 公共空间文件
├── shares/             # 分享记录
└── md5(密码)/          # 各用户的个人目录
```

## 功能列表

- **用户系统**：注册、登录、修改密码
- **文件上传**：拖拽/点击上传，多文件并发，实时进度条和速度显示
- **文件下载**：保留原始文件名
- **文件删除**：单选/批量删除
- **在线预览**：图片、视频、音频、PDF、文本/代码
- **文件分享**：生成带有效期的分享链接
- **公共空间**：上传到公共区域，所有用户可见
- **管理员**：可查看和管理所有用户文件
- **双视图**：列表视图 / 图标视图，支持排序和搜索
- **空间统计**：个人/公共/全局三级空间使用可视化
- **网络检测**：实时 Ping 显示网络延迟

## 部署步骤

### 1. 安装依赖

```bash
sudo apt install nginx php-fpm php-cli php-mbstring php-json
```

### 2. 创建目录

```bash
sudo mkdir -p /var/www/html/pan
sudo mkdir -p /var/www/uploads

sudo chown -R www-data:www-data /var/www/html/pan
sudo chown -R www-data:www-data /var/www/uploads
sudo chmod -R 755 /var/www/html/pan
sudo chmod -R 775 /var/www/uploads
```

### 3. 上传文件

将所有文件上传到 `/var/www/html/pan/`

### 4. 配置 Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;

    root /var/www/html/pan;
    index index.html;

    client_max_body_size 10G;
    client_body_timeout 300s;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize=10G\npost_max_size=10G\nmax_execution_time=300\nmax_input_time=300";
    }
}
```

### 5. 启用站点

```bash
sudo ln -s /etc/nginx/sites-available/your-domain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 6. 域名解析

将域名 A 记录指向服务器 IP。

## 首次使用

- 访问 `http://你的域名/`，默认为登录页面
- 管理员密码：`admin`
- 点击"注册"创建新账号

## 注意事项

- 存储目录 `/var/www/uploads/` 位于 Web 根目录外，不可直接通过 URL 访问
- 首次部署建议删除旧的 `users.json`，让系统自动重建（用户需重新注册）
- 文件上传大小限制由 Nginx 和 PHP 配置共同决定
- 建议定期清理过期分享记录（系统会自动清理）