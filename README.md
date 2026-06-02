# XX网盘系统 v4.0

一个基于 PHP + Nginx 的现代化个人网盘系统，支持多用户、文件管理、在线预览、分享、管理后台等功能。

## ✨ 功能特性

### 核心功能
- **用户系统**：注册、登录、修改密码、退出登录
- **文件上传**：拖拽/点击上传，多文件并发，实时进度条和速度显示，分片并行上传（5并发，10MB分片）
- **文件下载**：保留原始文件名，支持断点续传（Range 请求），X-Accel-Redirect 加速
- **文件删除**：单选/批量删除（支持个人空间和公共空间），递归删除文件夹
- **在线预览**：图片 / 视频 / 音频 / PDF / 文本 / 代码 / Office 文档
- **文件分享**：生成带有效期（1/7/30天）的分享链接
- **文件夹管理**：新建文件夹、重命名、移动、目录树导航、面包屑路径
- **ZIP 打包下载**：多文件打包为 ZIP 下载

### v4.0 新特性
- **🛡️ 安全风险检测**：管理后台新增安全风险面板，8 项自动检测（默认密码、Cookie Secure、敏感文件暴露、MIME 检查、磁盘空间、会话有效期、前端权限、频率限制）
- **🔒 全面安全加固**：分片合并 MIME 验证、文件锁防并发损坏、路径过滤统一 safeJoinPath、fix_users.php 认证保护、管理面板前端鉴权、右键菜单 XSS 修复
- **📊 MB/GB 单位显示**：管理后台空间配额改为 MB/GB 输入和显示，自动换算
- **⚡ 上传速度优化**：分片大小 5MB→10MB，并发数 3→5，进度条修复双重计数 BUG
- **🐳 Docker 增强**：资源限制、健康检查、日志配置、Nginx 安全头、环境变量端口
- **🌙 暗色模式修复**：文件夹缩略图颜色跟随主题变量
- **📱 移动端优化**：文件名和搜索框宽度增加

### 管理后台功能
- **系统信息**：PHP 版本、磁盘使用量、用户总数
- **参数设置**：空间配额（MB/GB 单位）、上传限制、分片大小、会话配置、缩略图配置
- **文件类型黑名单**：在线增删禁止上传的扩展名
- **用户管理**：查看用户列表、删除用户（同步清理文件）、修改角色、重置密码（同步迁移目录）
- **安全风险**：8 项自动检测，风险等级标识（高/中/低），安全范围建议，修复建议

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
├── fix_users.php       # 用户数据修复工具（需管理员认证）
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
git clone https://github.com/stilesloveland-cyber/pan.git
cd pan
docker compose up -d
```

#### 从本地部署

```bash
cd 项目目录
docker compose up -d
```

部署完成后：
- 访问 `http://你的IP:6666`
- 管理员默认密码：`admin`
- 管理后台：`http://你的IP:6666/admin.html`

#### 自定义端口

```bash
PAN_PORT=8080 docker-compose up -d
```

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
- **管理员默认密码**：`admin`（登录后请立即修改）
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
- **安全检查**：管理后台点击「安全风险」查看系统安全状态

## ⚙️ 注意事项

- 存储目录 `/var/www/uploads/` 位于 Web 根目录外，不可直接通过 URL 访问
- 首次部署建议删除旧的 `users.json`，让系统自动重建
- 文件上传大小限制由 Nginx `client_max_body_size` 和 PHP `upload_max_filesize` 共同决定
- 系统会自动清理过期分享记录
- 空间大小缓存 30 秒自动刷新，上传/删除后立即清除
- 全局空间配额上限不能超过服务器磁盘总容量的 50%
- 管理后台修改的参数实时生效，无需重启服务
- Docker 部署默认限制 512MB 内存、1 CPU 核心，可在 docker-compose.yml 中调整
- Docker 端口可通过环境变量 `PAN_PORT` 自定义

## 🔒 安全说明

- 使用 PHP Session 管理登录态，密码不会出现在 URL 和日志中
- Session Cookie 启用 `HttpOnly` 和 `SameSite=Lax` 保护
- 所有文件操作均进行路径穿越检测（统一使用 safeJoinPath）
- 文件上传双重验证：扩展名黑名单 + MIME type 检测
- 分片上传合并后同样进行 MIME type 验证
- JSON 文件读写使用 LOCK_EX 文件锁，防止并发数据损坏
- 管理面板前端增加权限校验，非管理员自动重定向
- fix_users.php 增加管理员认证保护
- Nginx 安全头：X-Frame-Options、X-Content-Type-Options、X-XSS-Protection、Referrer-Policy
- Nginx 拦截 fix_users.php 外部访问
- 密码长度最少 4 位
- 下载和预览支持 X-Accel-Redirect，避免 PHP 进程长时间占用
- 管理后台提供安全风险检测面板，可查看 8 项安全指标

## 📋 更新日志

### v4.0
- 新增安全风险检测面板（8 项自动检测：默认密码、Cookie Secure、敏感文件暴露、MIME 检查、磁盘空间、会话有效期、前端权限、频率限制）
- 管理后台空间配额改为 MB/GB 单位显示和输入，自动换算
- 修复管理员点击「管理后台」不跳转的 BUG
- 修复上传进度显示超出原文件大小的 BUG（双重计数问题）
- 修复右键菜单 XSS 漏洞（文件名含特殊字符时代码注入）
- 修复暗色模式下文件夹缩略图颜色硬编码问题
- 分片上传合并后增加 MIME type 验证
- 分片上传 file_size 参数增加有效性校验
- JSON 文件读写添加 LOCK_EX 文件锁（users.json/settings.json/cache）
- 路径过滤统一使用 safeJoinPath（download/preview/share/thumb 4 个文件）
- fix_users.php 增加管理员认证保护
- admin.html 增加前端权限校验
- 删除用户时同步清理其上传目录
- 重置密码时同步迁移用户目录
- 注册/改密/重置密码增加密码强度校验（≥4 位）
- files.php 密码参数兼容 POST 传递
- upload.php dir 参数从 $_REQUEST 改为 $_GET
- 提取前端重复代码：getFileIconInfo()、buildDownloadUrl() 公共函数
- Ping 检测间隔从 3s 优化为 15s
- 修复 searchFiles 临时修改全局状态的问题
- 分片上传优化：分片大小 5MB→10MB，并发数 3→5
- Docker 优化：固定镜像版本、资源限制、健康检查、日志配置
- Docker Compose 支持环境变量自定义端口
- Nginx 添加安全响应头和 fix_users.php 访问拦截
- Nginx 添加 fastcgi_intercept_errors 防止 PHP 源码泄露
- 移动端响应式优化：文件名宽度、搜索框宽度增加
- 新增 requireAuth() 集中认证函数
- clearSizeCache() 支持精确清除指定缓存键

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
