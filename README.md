# XX网盘系统 v2.0

一个基于 PHP + Nginx 的现代化个人网盘系统，支持多用户、文件管理、在线预览、分享等功能。

## ✨ 功能特性

### 核心功能
- **用户系统**：注册、登录、修改密码、退出登录
- **文件上传**：拖拽/点击上传，多文件并发，实时进度条和速度显示
- **文件下载**：保留原始文件名
- **文件删除**：单选/批量删除（支持个人空间和公共空间）
- **在线预览**：图片 / 视频 / 音频 / PDF / 文本 / 代码文件
- **文件分享**：生成带有效期（1/7/30天）的分享链接

### 新增特性（v2.0）
- **🔐 安全会话系统**：基于 PHP Session 的登录态管理，密码不再明文出现在 URL 中
- **🌙 暗色模式**：支持手动切换，跟随系统偏好
- **📱 响应式设计**：完美适配手机、平板、桌面端
- **🎨 现代化 UI**：渐变配色、毛玻璃效果、平滑动画
- **🔄 空间统计可视化**：个人/公共/全局三级空间使用量，根据视图自动切换
- **📡 网络延迟检测**：实时 Ping 显示网络状态
- **📂 文件夹管理**：新建文件夹、重命名、目录树导航、面包屑路径
- **📄 Office 预览**：支持 Word / Excel / PowerPoint 在线预览

### 管理功能
- **管理员**：可查看和管理所有用户文件
- **公共空间**：所有用户可见的上传区域
- **双视图**：列表视图 / 图标视图，支持排序和搜索

## 📁 目录结构

```
/var/www/html/pan/
├── index.html          # 前端界面（登录/上传/管理/预览）
├── functions.php       # 公共函数库（会话管理、用户认证、缓存）
├── upload.php          # 上传、注册、登录、改密、删除
├── files.php           # 获取文件列表和空间信息
├── download.php        # 文件下载
├── preview.php         # 文件在线预览
├── share.php           # 生成和访问分享链接
├── README.md           # 项目说明
└── DEPLOYMENT.md       # 部署指南

/var/www/uploads/       # 存储目录（Web根目录外）
├── users.json          # 用户信息
├── public/             # 公共空间文件
├── shares/             # 分享记录
├── cache/              # 空间大小缓存
└── md5(密码)/          # 各用户的个人目录
```

## 🚀 部署步骤

### 1. 安装依赖

```bash
sudo apt update
sudo apt install nginx php-fpm php-cli php-mbstring php-json php-common
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

    # 静态资源缓存
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
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

## 🎯 首次使用

- 访问 `http://你的域名/`
- **管理员默认密码**：`admin`
- 点击「注册账号」创建新用户
- 登录后即可上传和管理文件

## 📝 使用技巧

- **暗色模式**：点击侧边栏底部的 🌙 按钮切换
- **批量操作**：勾选多个文件后点「删除」批量删除
- **公共空间**：侧边栏切换「公共空间」视图
- **文件预览**：点击文件旁的 👁️ 图标在线预览
- **分享文件**：点击文件旁的 🔗 图标生成分享链接

## ⚙️ 注意事项

- 存储目录 `/var/www/uploads/` 位于 Web 根目录外，不可直接通过 URL 访问
- 首次部署建议删除旧的 `users.json`，让系统自动重建
- 文件上传大小限制由 Nginx `client_max_body_size` 和 PHP `upload_max_filesize` 共同决定
- 系统会自动清理过期分享记录
- 空间大小缓存 30 秒自动刷新，上传/删除后立即清除

## 🔒 安全说明（v2.0）

- 使用 PHP Session 管理登录态，密码不会出现在 URL 和日志中
- Session Cookie 启用 `HttpOnly` 和 `SameSite=Lax` 保护
- 所有文件操作均进行路径穿越检测
- 会话过期时间：7 天

## 📋 更新日志

### v2.0
- 引入 PHP Session 安全认证
- 新增暗色模式支持
- 全面 UI 重设计（渐变、毛玻璃、动画）
- 响应式布局优化（移动端适配）
- 空间大小缓存机制
- 代码重构：常量定义、路径安全、错误处理
- 修复：管理员批量删除公共文件、注册不返回角色信息