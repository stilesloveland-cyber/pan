# WPAN 项目解剖报告

---

## 1. 一句话白话总结

这是一个基于 PHP + Nginx 的**轻量级多用户个人网盘系统**（名称为"XX网盘"），核心解决的问题是：让用户通过浏览器即可上传、管理、预览、分享和下载文件，无需依赖第三方云存储服务，自建私有云盘。

---

## 2. 全景架构与模块关系

### 2.1 核心目录树结构

```
e:\trae项目\website\wpan\
├── index.html              # 前端主界面（登录/注册/文件管理/预览/分享）
├── index.php               # 与 index.html 内容完全重复的 PHP 版本入口
├── admin.html              # 管理后台前端界面
├── script.js               # 前端全部 JavaScript 逻辑（单文件，约1500+行）
├── style.css               # 前端全部样式（单文件，含暗色模式）
├── logo.svg                # 网盘 Logo
├── config.php              # 全局配置中心（常量定义 + 动态设置读取）
├── functions.php           # 公共函数库（会话管理、用户认证、缓存、文件操作）
├── upload.php              # 核心 API：上传/注册/登录/改密/删除/创建文件夹/移动/重命名
├── files.php               # 文件列表 API：获取文件/文件夹/空间统计
├── download.php            # 文件下载（支持 Range 断点续传 + X-Accel-Redirect）
├── preview.php             # 文件在线预览（图片/视频/音频/PDF/文本/Office）
├── share.php               # 文件分享（创建分享链接 + 匿名访问分享文件）
├── zip.php                 # 多文件 ZIP 打包下载
├── thumb.php               # 图片缩略图生成（WebP 格式缓存）
├── admin.php               # 管理后台 API（设置/用户管理/系统信息）
├── fix_users.php           # 修复工具：重建 users.json
├── nginx.conf              # 裸机部署 Nginx 配置
├── nginx-pan.conf          # 备用 Nginx 配置
├── .htaccess               # Apache 兼容配置
├── docker-compose.yml      # Docker Compose 编排文件
├── docker/
│   ├── Dockerfile          # Docker 镜像构建（php:8.2-fpm-alpine + nginx + supervisor）
│   ├── entrypoint.sh       # 容器入口脚本（初始化目录和权限）
│   ├── supervisord.conf    # 进程管理（php-fpm + nginx 同容器运行）
│   ├── nginx/
│   │   └── default.conf    # 容器内 Nginx 站点配置
│   └── php/
│       └── php.ini         # PHP 运行时配置
├── README.md               # 项目说明文档
└── DEPLOYMENT.md           # 部署文档
```

### 2.2 关键核心文件（Top 5）

| 排名 | 文件 | 角色 |
|------|------|------|
| 1 | [functions.php](file:///e:/trae项目/website/wpan/functions.php) | **系统心脏**。包含会话管理、用户认证（登录/注册/改密）、空间统计缓存、文件操作辅助函数（路径安全拼接、目录遍历、递归删除等）。所有 PHP API 文件的第一行 `require_once` 都指向它。 |
| 2 | [config.php](file:///e:/trae项目/website/wpan/config.php) | **配置中枢**。定义所有路径常量（上传目录、用户文件、分享目录、缓存目录等）、默认系统参数（空间配额、分片大小、黑名单等）、动态设置读取函数 `getSettings()`/`saveSettings()`、文件安全检测函数。 |
| 3 | [upload.php](file:///e:/trae项目/website/wpan/upload.php) | **最复杂的 API 入口**。承担了注册、登录、注销、改密、文件上传（普通 + 分片3并发）、文件删除、创建文件夹、移动文件、重命名等几乎所有写操作。一个文件处理 10+ 种 action。 |
| 4 | [script.js](file:///e:/trae项目/website/wpan/script.js) | **前端大脑**。单文件包含全部前端逻辑：会话检测、登录注册、文件列表渲染、拖拽上传、分片并行上传、预览、分享、暗色模式、右键菜单、灯箱等。 |
| 5 | [index.html](file:///e:/trae项目/website/wpan/index.html) | **前端骨架**。包含登录界面、注册弹窗、主界面（侧边栏 + 文件列表 + 上传区）、所有弹窗（新建文件夹/移动/重命名/改密/预览/分享）、右键菜单、灯箱组件。 |

### 2.3 数据流图

#### 用户登录流程

```
浏览器(index.html)
  |  用户输入密码，点击"登录"
  v
script.js: handleLogin()
  |  POST upload.php  action=login  password=xxx
  v
upload.php
  |  调用 loginUser($password)
  v
functions.php: loginUser()
  |  findUserByPassword() --> 读取 users.json --> password_verify()
  |  验证通过 --> 写入 $_SESSION['password_md5'] / $_SESSION['user_id'] / $_SESSION['role']
  |  session_regenerate_id(true) 防固定会话攻击
  v
upload.php --> 返回 JSON {success:true, role:"admin"|"user"}
  v
script.js: showMain() --> fetch files.php 获取文件列表
```

#### 文件上传流程（分片上传）

```
浏览器(script.js)
  |  用户选择文件 --> 计算分片 --> 3并发上传
  v
POST upload.php  action=upload_chunk
  |  upload_id / chunk_index / total_chunks / file_name / chunk文件
  v
upload.php
  |  isBlockedExtension() 检查扩展名
  |  chunkIndex===0 时检查空间配额
  |  move_uploaded_file() 保存到 /var/www/uploads/cache/chunks/{upload_id}/{index}
  v
所有分片上传完毕后:
POST upload.php  action=merge_chunks
  |  验证所有分片存在
  |  流式合并: fopen('wb') + stream_copy_to_stream() 逐片写入
  |  文件名: uniqid() + '_' + 原始文件名
  |  保存到 用户目录 或 PUBLIC_DIR
  |  清理分片 + clearSizeCache()
  v
返回 JSON {success:true, usedSize, maxSize, globalUsedSize, ...}
```

#### 文件下载流程

```
浏览器
  |  GET download.php?file=xxx&dir=yyy
  v
download.php
  |  认证检查 (Session优先, 兼容密码参数)
  |  确定文件路径 + realpath() 安全检查
  |  非管理员: strpos($realFilePath, $realUserDir) 路径穿越防护
  |  设置 X-Accel-Redirect: /protected-uploads/... (Nginx内部重定向加速)
  |  支持 HTTP Range 请求 (断点续传)
  v
Nginx 收到 X-Accel-Redirect
  |  location /protected-uploads { internal; alias /var/www/uploads; }
  |  直接 sendfile() 输出文件，PHP进程不阻塞
  v
浏览器收到文件流
```

#### 文件分享流程

```
用户A 创建分享:
  POST share.php  action=create_share  file=xxx  expiry=7
  |  生成 shareId = uniqid('share_', true)
  |  写入 /var/www/uploads/shares/{shareId}.json
  |  返回分享链接: http://host/share.php?id={shareId}

用户B 访问分享链接（无需登录）:
  GET share.php?id={shareId}
  |  读取 shares/{shareId}.json
  |  检查过期时间
  |  直接 readfile() 输出文件内容（内联预览）
  |  cleanExpiredShares() 顺便清理过期分享
```

#### 管理后台流程

```
浏览器(admin.html)
  |  所有请求带 Session Cookie
  v
admin.php
  |  验证: 必须是 admin 角色
  |  action=get_settings   --> 读取 settings.json + 磁盘信息
  |  action=save_settings  --> 校验配额上限(<=磁盘50%) --> 写入 settings.json
  |  action=get_users      --> 读取 users.json
  |  action=delete_user    --> 删除用户（不能删admin）
  |  action=change_user_role --> 修改角色
  |  action=reset_user_password --> 重置密码
  |  action=system_info    --> PHP版本/磁盘/用户数/全局已用空间
```

---

## 3. 底层技术栈与依赖

### 3.1 核心语言版本

- **后端**: PHP 8.2（Docker 镜像基于 `php:8.2-fpm-alpine`）
- **前端**: 原生 HTML5 + CSS3 + JavaScript（ES6+），无任何前端框架
- **Web 服务器**: Nginx（容器内通过 Supervisor 与 PHP-FPM 同进程运行）

### 3.2 关键第三方依赖

**后端（PHP 扩展，Docker 构建时安装）**:
| 依赖 | 用途 |
|------|------|
| php-gd | 图片缩略图生成（imagecreatefromjpeg/png/gif/webp/bmp + imagewebp） |
| php-zip | ZIP 打包下载（ZipArchive 类） |
| php-fpm | FastCGI 进程管理器 |

**前端（CDN 引入）**:
| 依赖 | 用途 |
|------|------|
| Font Awesome 6.4.0 | 全站图标（来自 cdn.bootcdn.net） |

**基础设施**:
| 依赖 | 用途 |
|------|------|
| Supervisor | 容器内进程管理（同时运行 php-fpm 和 nginx） |
| Alpine Linux | Docker 基础镜像，极小体积 |

### 3.3 零数据库设计

本项目**不使用任何数据库**，所有数据以 JSON 文件存储在文件系统上：
- `users.json` -- 用户账号信息
- `settings.json` -- 系统动态配置
- `shares/*.json` -- 分享记录
- `cache/*.cache` -- 空间大小缓存
- `thumbs/*.webp` -- 缩略图缓存

---

## 4. 如何运行此项目

### 方式一：Docker 一键部署（推荐）

**前置条件**: 安装 Docker 和 Docker Compose

**步骤**:

```bash
# 1. 进入项目目录
cd e:\trae项目\website\wpan

# 2. 一键启动
docker-compose up -d

# 3. 访问
# 主界面: http://localhost:6666
# 管理后台: http://localhost:6666/admin.html
# 默认管理员密码: admin
```

**无需配置环境变量**，项目不依赖任何 `.env` 文件。所有配置通过管理后台在线修改，写入 `settings.json`。

**Docker 端口映射**: 宿主机 `6666` --> 容器 `80`

**数据持久化**: Docker Volume `pan-data` 挂载到容器 `/var/www/uploads`

### 方式二：裸机部署

**前置条件**: Linux 服务器 + Nginx + PHP 8.2-FPM

```bash
# 1. 安装依赖
sudo apt install nginx php8.2-fpm php8.2-gd php8.2-zip php8.2-mbstring

# 2. 创建目录
sudo mkdir -p /var/www/html/pan /var/www/uploads
sudo chown -R www-data:www-data /var/www/html/pan /var/www/uploads

# 3. 复制项目文件到 /var/www/html/pan/
# 4. 复制 nginx.conf 到 /etc/nginx/sites-available/pan 并启用
# 5. 重启 nginx 和 php8.2-fpm
```

### 关键配置参数

| 参数 | 默认值 | 说明 |
|------|--------|------|
| 全局空间上限 | 10 GB | 不超过磁盘总容量的 50% |
| 单用户空间上限 | 2 GB | |
| 公共空间上限 | 5 GB | |
| 单文件最大 | 10 GB | 由 Nginx + PHP 共同限制 |
| 分片大小 | 5 MB | |
| 会话有效期 | 7 天 | |
| 空间缓存 TTL | 30 秒 | |
| 缩略图尺寸 | 200x200 | |

---

## 5. 潜在陷阱与代码质量评估

### 5.1 架构层面的问题

**问题1: upload.php 上帝文件**
[upload.php](file:///e:/trae项目/website/wpan/upload.php) 承担了注册、登录、注销、改密、文件上传（普通+分片）、文件删除、创建文件夹、移动文件、重命名等 10+ 种操作。单个文件超过 500 行，通过 `if/elseif` 链式判断 `$_POST['action']` 来路由。这违反了单一职责原则，维护和扩展困难。

**问题2: index.html 与 index.php 内容完全重复**
[index.html](file:///e:/trae项目/website/wpan/index.html) 和 [index.php](file:///e:/trae项目/website/wpan/index.php) 的内容完全一致，属于冗余文件。Nginx 配置中 `index index.html index.php`，实际只会命中 `index.html`，`index.php` 从未被使用。

**问题3: 无路由层，API 散落**
每个功能都是独立的 PHP 文件（upload.php, files.php, download.php, preview.php, share.php, zip.php, thumb.php, admin.php），没有统一的路由分发和中间件层。认证逻辑在每个文件中重复编写（约 10-20 行/文件），存在遗漏风险。

### 5.2 安全隐患

**隐患1: 用户 ID 使用密码 MD5**
用户目录以 `md5(密码)` 命名（见 [functions.php:L177-178](file:///e:/trae项目/website/wpan/functions.php#L177-L178)），MD5 是快速哈希，如果文件系统被泄露，攻击者可通过彩虹表反推密码。此外，修改密码时需要 rename 整个用户目录（[functions.php:L203-205](file:///e:/trae项目/website/wpan/functions.php#L203-L205)），如果目录很大或跨文件系统，rename 可能失败。

**隐患2: Session 中存储 password_md5**
[functions.php:L75](file:///e:/trae项目/website/wpan/functions.php#L75) 将密码的 MD5 值存入 `$_SESSION['password_md5']`，用于标识用户目录。如果 Session 存储被泄露，攻击者可直接获取密码 MD5。

**隐患3: 分享文件无访问控制**
[share.php:L98-157](file:///e:/trae项目/website/wpan/share.php#L98-L157) 中，访问分享链接无需任何认证，只要知道 shareId 即可下载文件。shareId 使用 `uniqid('share_', true)` 生成，可预测性较高（基于时间戳），理论上可被暴力枚举。

**隐患4: fix_users.php 可被直接访问**
[fix_users.php](file:///e:/trae项目/website/wpan/fix_users.php) 是一个无认证的修复脚本，任何人都可通过 URL 直接访问执行，可能被恶意利用。

**隐患5: 管理后台 admin.html 无独立认证**
[admin.html](file:///e:/trae项目/website/wpan/admin.html) 的前端页面任何人都可以访问，虽然 API 层 (admin.php) 有角色验证，但界面暴露本身可能泄露系统信息。

### 5.3 代码质量问题

**问题1: 认证代码大量重复**
每个 PHP API 文件中都有几乎相同的认证逻辑（约 10-20 行），模式为"Session 优先，兼容密码参数"。这应该提取为中间件函数。

**问题2: 错误处理不一致**
部分 API 使用 `die()` 直接输出文本（如 [download.php:L19](file:///e:/trae项目/website/wpan/download.php#L19) `die('请先登录')`），部分使用 `echo json_encode()` 返回 JSON，部分使用 `http_response_code()`。前端难以统一处理错误。

**问题3: 前端全局变量污染**
[script.js](file:///e:/trae项目/website/wpan/script.js) 使用大量全局变量（currentPassword, files, publicFiles, folders, currentView, displayView, currentPath, selectedFiles, usedSize, maxSize 等），没有使用模块化或命名空间，存在变量冲突风险。

**问题4: 硬编码的 CDN 依赖**
[index.html:L8](file:///e:/trae项目/website/wpan/index.html#L8) 和 [admin.html:L8](file:///e:/trae项目/website/wpan/admin.html#L8) 硬编码了 `cdn.bootcdn.net` 的 Font Awesome CDN 地址，在内网或 CDN 不可用时会导致图标全部丢失。

**问题5: 无输入长度限制**
注册密码、文件夹名称、文件重命名等输入没有长度限制，极端情况下可能导致文件系统问题或 JSON 文件膨胀。

### 5.4 扩展新功能时的"雷区"提醒

1. **添加新 API 端点时**：必须手动复制完整的认证逻辑块，否则会留下安全漏洞。建议先重构为统一中间件。
2. **修改用户系统时**：用户目录与密码 MD5 强绑定，任何涉及用户标识的改动都需要同时处理目录重命名，极易出错。
3. **添加文件类型支持时**：需要在 [config.php](file:///e:/trae项目/website/wpan/config.php) 的 `$IMAGE_EXTS`/`$VIDEO_EXTS` 等数组、[preview.php](file:///e:/trae项目/website/wpan/preview.php) 的 `$mimeTypes` 映射、[share.php](file:///e:/trae项目/website/wpan/share.php) 的 `$mimeTypes` 映射三处同步修改，容易遗漏。
4. **调整空间配额时**：`settings.json` 中的配置在 `config.php` 加载时被转为 PHP 常量（`define()`），运行期间不可变。管理后台修改后，**已运行的 PHP 进程不会感知变化**，只有新请求才会加载新配置。
5. **并发安全**：`users.json` 和 `settings.json` 的读写没有任何文件锁（flock），高并发下可能出现数据丢失或损坏。
6. **大文件处理**：虽然分片上传降低了内存压力，但 `merge_chunks` 仍然需要 PHP 进程长时间运行（`set_time_limit(300)`），超大文件合并可能超时。

---

[项目扫描完毕] 大 Boss，该项目的底细已被彻底摸清，请您审阅！
