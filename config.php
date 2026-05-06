<?php
/**
 * WPAN 个人网盘系统 - 全局配置
 * 
 * 统一管理路径、配额、文件类型限制等设置
 */

// ========== 路径配置 ==========
define('BASE_UPLOAD_DIR', '/var/www/uploads/');
define('USERS_FILE', BASE_UPLOAD_DIR . 'users.json');
define('SHARE_DIR', BASE_UPLOAD_DIR . 'shares/');
define('PUBLIC_DIR', BASE_UPLOAD_DIR . 'public/');
define('CACHE_DIR', BASE_UPLOAD_DIR . 'cache/');
define('THUMB_DIR', BASE_UPLOAD_DIR . 'thumbs/');

// ========== 空间配额 ==========
define('MAX_TOTAL_SIZE', 10 * 1024 * 1024 * 1024);   // 全局 10GB
define('MAX_USER_SIZE', 2 * 1024 * 1024 * 1024);     // 个人 2GB
define('MAX_PUBLIC_SIZE', 5 * 1024 * 1024 * 1024);   // 公共 5GB

// ========== 上传限制 ==========
define('MAX_FILE_SIZE', 10 * 1024 * 1024 * 1024);    // 单文件最大 10GB
define('MAX_FILES_PER_UPLOAD', 50);                   // 单次最多上传 50 个文件

// ========== 文件类型黑名单（禁止上传）=========
$BLOCKED_EXTENSIONS = [
    'exe', 'msi', 'bat', 'cmd', 'com', 'scr',
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7',
    'py', 'pl', 'sh', 'bash', 'zsh',
    'jar', 'war',
    'asp', 'aspx', 'cgi', 'jsp',
    'dll', 'sys', 'vbs', 'vbe', 'jse',
];

// ========== 会话配置 ==========
define('SESSION_LIFETIME', 86400 * 7);                // Session 7 天过期
define('CACHE_TTL', 30);                               // 空间缓存 30 秒

// ========== 缩略图配置 ==========
define('THUMB_WIDTH', 200);
define('THUMB_HEIGHT', 200);

// ========== 文件预览支持类型 ==========
$IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
$VIDEO_EXTS = ['mp4', 'webm', 'avi', 'mov'];
$AUDIO_EXTS = ['mp3', 'wav', 'flac', 'ogg', 'aac'];
$TEXT_EXTS  = ['txt', 'md', 'html', 'css', 'js', 'json', 'xml', 'php', 'log', 'yaml', 'yml', 'conf', 'ini', 'cfg'];
$OFFICE_EXTS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

// ========== 辅助函数 ==========
function isBlockedExtension($filename) {
    global $BLOCKED_EXTENSIONS;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $BLOCKED_EXTENSIONS);
}
