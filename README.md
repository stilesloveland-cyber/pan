# XX网盘系统 v3.0

一个基于 PHP + Nginx 的现代化个人网盘系统，支持多用户、文件管理、在线预览、分享、管理后台等功能。

## ✨ 功能特性

### 核心功能
- **用户系统**：注册、登录、修改密码、退出登录
- **文件上传**：拖拽/点击上传，多文件并发，实时进度条和速度显示，分片并行上传（3并发）
- **文件下载**：保留原始文件名，支持断点续传（Range 请求），X-Accel-Redirect 加速
- **文件删除**：单选/批量删除（支持个人空间和公共空间），递归删除文件夹
- **在线预览**：图片 / 视频 / 音频 / PDF / 文本 / 代码 / Office 文档
- **文件分享**：生成带有效期（1/7/30天）的分享链接
- **文件夹管理**：新建文件夹、重命名、移动、目录树导航、面包屑路径
- **ZIP 打包下载**：多文件打包为 ZIP 下载

### v3.0 新特性
- **🛡️ 安全加固**：文件类型黑名单 + MIME type 双重验证，递归删除文件夹，路径穿越防护增强
- **⚡ 速度优化**：分片并行上传（3并发），流式合并（低内存），X-Accel-Redirect 下载加速，Nginx sendfile/gzip
- **🔧 管理后台**：独立的 admin.html 管理界面，可在线调整所有参数
- **📊 动态配置**：配置从 settings.json 读取，管理后台实时修改，无需重启
- **🐳 Docker 一键部署**：docker-compose up -d 即可运行，端口 6666
- **🔒 空间配额限制**：全局空间上限不超过服务器磁盘总容量的 50%

### 管理后台功能
- **系统信息**：PHP 版本、磁盘使用量、用户总数
- **参数设置**：空间配额、上传限制、分片大小、会话配置、缩略图配置
- **文件类型黑名单**：在线增删禁止上传的扩展名
- **用户管理**：查看用户列表、删除用户、修改角色、重置密码

### 其他特性
- **🔐 安全会话系统**：基于 PHP Session 的登录态管理
- **🌙 暗色模式**：支持手动切换，跟随系统偏好
- **📱 响应式设计**：完美适配手机、平板、桌面端
- **🎨 现代化 UI**：渐变配色、毛玻璃效果、平滑动画
- **🔄 空间统计可视化**：个人/公共/全局三级空间使用量
- **📡 网络延迟检测**：实时 Ping 显示网络状态
- **📄 Office 预览**：支持 Word / Excel / PowerPoint 在线预览
- **🖼️ 缩略图**：自动生成 WebP 缩略图并缓存

## 📁 目录结构

```
项目根目录/
├── index.html          # 前端界面（登录/上传/管理/预览）
├── admin.html          # 管理后台界面
├── script.js           # 前端 JavaScript 逻辑
├── style.css           # 前端样式
├── config.php          # 全局配置（读取 settings.json）
├── functions.php       # 公共函数库（会话管理、用户认证、缓存）
├── upload.php          # 上传、注册、登录、改密、删除
├── files.php           # 获取文件列表和空间信息
├── download.php        # 文件下载（支持 Range + X-Accel-Redirect）
├── preview.php         # 文件在线预览（支持 Range + X-Accel-Redirect）
├── share.php           # 生成和访问分享链接
├── zip.php             # ZIP 打包下载
├── thumb.php           # 缩略图生成
├── admin.php           # 管理后台 API
├── nginx.conf          # Nginx 配置（裸机部署）
├── nginx-pan.conf      # Nginx 配置（备用）
├── docker-compose.yml  # Docker Compose 编排
├── docker/
│   ├── Dockerfile          # Docker 镜像构建
│   ├── entrypoint.sh       # 容器入口脚本
│   ├── supervisord.conf    # 进程管理配置
│   ├── nginx/
│   │   └── default.conf    # 容器内 Nginx 配置
│   └── php/
│       └── php.ini         # PHP 运行时配置
└── README.md

/var/www/uploads/       # 存储目录（Web根目录外）
├── users.json          # 用户信息
├── settings.json       # 动态配置（管理后台修改）
├── public/             # 公共空间文件
├── shares/             # 分享记录
├── cache/              # 空间大小缓存 + 分片临时文件
├── thumbs/             # 缩略图缓存
└── md5(密码)/          # 各用户的个人目录
```

## 🚀 部署方式

### 方式一：Docker 一键部署（推荐）

#### 从 GitHub 克隆部署

```bash
git clone https://github.com/你的用户名/你的仓库名.git pan
cd pan
docker-compose up -d
```

#### 从本地部署

```bash
cd 项目目录
docker-compose up -d
```

部署完成后：
- 访问 `http://你的IP:6666`
- 管理员默认密码：`admin`
- 管理后台：`http://你的IP:6666/admin.html`

#### Docker 常用命令

```bash
# 启动
docker-compose up -d

# 查看日志
docker-compose logs -f

# 停止
docker-compose down

# 重新构建（更新代码后）
docker-compose up -d --build

# 数据备份
docker run --rm -v pan-data:/data -v $(pwd):/backup alpine tar czf /backup/pan-backup.tar.gz -C /data .
```

### 方式二：裸机部署

#### 1. 安装依赖

```bash
sudo apt update
sudo apt install nginx php-fpm php-cli php-mbstring php-json php-common php-gd php-zip
```

#### 2. 创建目录

```bash
sudo mkdir -p /var/www/html/pan
sudo mkdir -p /var/www/uploads

sudo chown -R www-data:www-data /var/www/html/pan
sudo chown -R www-data:www-data /var/www/uploads
sudo chmod -R 755 /var/www/html/pan
sudo chmod -R 775 /var/www/uploads
```

#### 3. 上传文件

将所有文件上传到 `/var/www/html/pan/`

#### 4. 配置 Nginx

使用项目中的 `nginx.conf`，复制到 Nginx 配置目录：

```bash
sudo cp nginx.conf /etc/nginx/sites-available/pan
sudo ln -s /etc/nginx/sites-available/pan /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### 5. 启动 PHP-FPM

```bash
sudo systemctl restart php8.2-fpm
```

#### 6. 域名解析

将域名 A 记录指向服务器 IP。

## 🎯 首次使用

- 访问 `http://你的域名:6666`（Docker）或 `http://你的域名/`（裸机）
- **管理员默认密码**：`admin`
- 点击「注册账号」创建新用户
- 登录后即可上传和管理文件
- 管理员可在侧边栏看到「管理后台」入口

## 📝 使用技巧

- **暗色模式**：点击侧边栏底部的 🌙 按钮切换
- **批量操作**：勾选多个文件后点「删除」批量删除
- **公共空间**：侧边栏切换「公共空间」视图
- **文件预览**：点击文件旁的 👁️ 图标在线预览
- **分享文件**：点击文件旁的 🔗 图标生成分享链接
- **打包下载**：选中多个文件后点击「打包」按钮
- **管理后台**：管理员登录后侧边栏出现「管理后台」入口

## ⚙️ 注意事项

- 存储目录 `/var/www/uploads/` 位于 Web 根目录外，不可直接通过 URL 访问
- 首次部署建议删除旧的 `users.json`，让系统自动重建
- 文件上传大小限制由 Nginx `client_max_body_size` 和 PHP `upload_max_filesize` 共同决定
- 系统会自动清理过期分享记录
- 空间大小缓存 30 秒自动刷新，上传/删除后立即清除
- 全局空间配额上限不能超过服务器磁盘总容量的 50%
- 管理后台修改的参数实时生效，无需重启服务

## 🔒 安全说明

- 使用 PHP Session 管理登录态，密码不会出现在 URL 和日志中
- Session Cookie 启用 `HttpOnly` 和 `SameSite=Lax` 保护
- 所有文件操作均进行路径穿越检测
- 文件上传双重验证：扩展名黑名单 + MIME type 检测
- 会话过期时间：7 天（可在管理后台调整）
- 分片上传同样进行文件类型检查
- 下载和预览支持 X-Accel-Redirect，避免 PHP 进程长时间占用

## 📋 更新日志

### v3.0
- 新增管理后台（系统信息、参数设置、黑名单管理、用户管理）
- 配置从硬编码常量改为 settings.json 动态读取
- 空间配额限制不超过服务器磁盘总容量 50%
- 文件上传添加扩展名黑名单 + MIME type 双重验证
- 分片上传添加文件类型检查
- 修复递归删除文件夹不完整的问题
- 修复 findUserByMd5() 函数空实现
- ZIP 打包路径拼接改用 safeJoinPath()
- 下载文件名编码改用 RFC 5987 标准，中文不再乱码
- 下载支持 HTTP Range 请求（断点续传）
- 缩略图改用 finfo 检测 MIME type
- 分片合并改用流式读写，降低内存占用
- 分片上传从串行改为 3 并发并行上传
- Nginx 配置优化（sendfile、gzip、缓冲区）
- 下载和预览支持 X-Accel-Redirect 加速
- 新增 Docker 一键部署（docker-compose up -d）
- 分片大小默认 5MB，可在管理后台调整

### v2.0
- 引入 PHP Session 安全认证
- 新增暗色模式支持
- 全面 UI 重设计（渐变、毛玻璃、动画）
- 响应式布局优化（移动端适配）
- 空间大小缓存机制
- 代码重构：常量定义、路径安全、错误处理
- 修复：管理员批量删除公共文件、注册不返回角色信息
