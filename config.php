<?php
define('BASE_UPLOAD_DIR', '/var/www/uploads/');
define('USERS_FILE', BASE_UPLOAD_DIR . 'users.json');
define('SHARE_DIR', BASE_UPLOAD_DIR . 'shares/');
define('PUBLIC_DIR', BASE_UPLOAD_DIR . 'public/');
define('CACHE_DIR', BASE_UPLOAD_DIR . 'cache/');
define('THUMB_DIR', BASE_UPLOAD_DIR . 'thumbs/');
define('SETTINGS_FILE', BASE_UPLOAD_DIR . 'settings.json');

$DEFAULT_SETTINGS = [
    'max_total_size' => 10 * 1024 * 1024 * 1024,
    'max_user_size' => 2 * 1024 * 1024 * 1024,
    'max_public_size' => 5 * 1024 * 1024 * 1024,
    'max_file_size' => 10 * 1024 * 1024 * 1024,
    'max_files_per_upload' => 50,
    'chunk_size' => 5 * 1024 * 1024,
    'blocked_extensions' => [
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr',
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7',
        'py', 'pl', 'sh', 'bash', 'zsh',
        'jar', 'war',
        'asp', 'aspx', 'cgi', 'jsp',
        'dll', 'sys', 'vbs', 'vbe', 'jse',
    ],
    'session_lifetime' => 86400 * 7,
    'cache_ttl' => 30,
    'thumb_width' => 200,
    'thumb_height' => 200,
];

function getSettings() {
    global $DEFAULT_SETTINGS;
    if (file_exists(SETTINGS_FILE)) {
        $saved = json_decode(file_get_contents(SETTINGS_FILE), true);
        if (is_array($saved)) return array_merge($DEFAULT_SETTINGS, $saved);
    }
    return $DEFAULT_SETTINGS;
}

function saveSettings($settings) {
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT), LOCK_EX);
}

function getServerDiskInfo() {
    $total = @disk_total_space(BASE_UPLOAD_DIR);
    $free = @disk_free_space(BASE_UPLOAD_DIR);
    return [
        'total' => $total !== false ? $total : 0,
        'free' => $free !== false ? $free : 0,
        'used' => $total !== false ? $total - $free : 0,
    ];
}

$settings = getSettings();

define('MAX_TOTAL_SIZE', $settings['max_total_size']);
define('MAX_USER_SIZE', $settings['max_user_size']);
define('MAX_PUBLIC_SIZE', $settings['max_public_size']);
define('MAX_FILE_SIZE', $settings['max_file_size']);
define('MAX_FILES_PER_UPLOAD', $settings['max_files_per_upload']);
define('CHUNK_SIZE', $settings['chunk_size']);
define('SESSION_LIFETIME', $settings['session_lifetime']);
define('CACHE_TTL', $settings['cache_ttl']);
define('THUMB_WIDTH', $settings['thumb_width']);
define('THUMB_HEIGHT', $settings['thumb_height']);

$BLOCKED_EXTENSIONS = $settings['blocked_extensions'];

$IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
$VIDEO_EXTS = ['mp4', 'webm', 'avi', 'mov'];
$AUDIO_EXTS = ['mp3', 'wav', 'flac', 'ogg', 'aac'];
$TEXT_EXTS  = ['txt', 'md', 'html', 'css', 'js', 'json', 'xml', 'php', 'log', 'yaml', 'yml', 'conf', 'ini', 'cfg'];
$OFFICE_EXTS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

function isBlockedExtension($filename) {
    global $BLOCKED_EXTENSIONS;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $BLOCKED_EXTENSIONS);
}

function isSafeFile($tmpPath, $filename) {
    if (isBlockedExtension($filename)) return false;
    if (!file_exists($tmpPath)) return true;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    $blockedMimes = [
        'text/x-php', 'application/x-php', 'application/x-httpd-php',
        'application/x-httpd-php-source', 'application/x-sh',
        'text/x-shellscript', 'application/x-executable',
        'application/x-dosexec'
    ];
    foreach ($blockedMimes as $b) {
        if (strpos($mime, $b) !== false) return false;
    }
    return true;
}
