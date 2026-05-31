<?php
/**
 * WPAN 个人网盘系统 - 文件在线预览
 * 版本: 2.0 (会话重构版)
 */
require_once __DIR__ . '/functions.php';

initSystem();

$file = isset($_GET['file']) ? $_GET['file'] : '';
$userDirParam = isset($_GET['userDir']) ? $_GET['userDir'] : '';

if (empty($file)) {
    die('缺少必要参数');
}

// 认证（会话优先，兼容密码参数）
$user = getCurrentUser();
$password = isset($_GET['password']) ? $_GET['password'] : '';

if (!$user && !empty($password)) {
    $user = findUserByPassword($password);
}

if (!$user) {
    die('请先登录');
}

$isAdminUser = $user['data']['role'] === 'admin';
$fileName = basename($file);
$subDir = isset($_GET['dir']) ? trim($_GET['dir']) : '';

// 确定文件路径（支持子文件夹）
$basePath = '';
if ($isAdminUser && !empty($userDirParam)) {
    $basePath = BASE_UPLOAD_DIR . basename($userDirParam) . '/';
} else {
    $userDirPath = getCurrentUserDir();
    if (!$userDirPath && !empty($password)) {
        $userDirPath = getUserDir($password);
    }
    if (!$userDirPath) {
        die('无法确定用户目录');
    }
    $basePath = $userDirPath;
}

// 安全拼接子文件夹路径
if (!empty($subDir)) {
    $safeSubDir = str_replace(['..', '\\'], '', $subDir);
    $safeSubDir = trim($safeSubDir, '/');
    $filePath = $basePath . $safeSubDir . '/' . $fileName;
} else {
    $filePath = $basePath . $fileName;
}

// 安全检查
$realFilePath = realpath($filePath);
if ($realFilePath === false || !file_exists($realFilePath) || !is_file($realFilePath)) {
    die('文件不存在');
}

if (!$isAdminUser) {
    $userDirPath = getCurrentUserDir();
    if (!$userDirPath && !empty($password)) {
        $userDirPath = getUserDir($password);
    }
    $realUserDir = realpath($userDirPath);
    if ($realUserDir === false || strpos($realFilePath, $realUserDir) !== 0) {
        die('无效的文件路径');
    }
}

$originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $fileName);
$fileExt = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));

$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
    'txt' => 'text/plain; charset=utf-8',
    'md' => 'text/markdown; charset=utf-8',
    'html' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'xml' => 'application/xml; charset=utf-8',
    'php' => 'text/plain; charset=utf-8',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'flac' => 'audio/flac',
    'ogg' => 'audio/ogg',
    'aac' => 'audio/aac'
];

$mimeType = isset($mimeTypes[$fileExt]) ? $mimeTypes[$fileExt] : 'application/octet-stream';

$fileSize = filesize($realFilePath);

// 视频/音频文件支持 HTTP Range（流式播放、拖动进度条）
$isStreamable = in_array($fileExt, ['mp4', 'webm', 'avi', 'mov', 'mp3', 'wav', 'flac', 'ogg', 'aac']);

if ($isStreamable && isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
    $start = intval($matches[1]);
    $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
    if ($end >= $fileSize) $end = $fileSize - 1;
    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
    header("Content-Length: $length");
    header('Content-Type: ' . $mimeType);
    $encodedName = rawurlencode($originalName);
    header("Content-Disposition: inline; filename*=UTF-8''$encodedName; filename=\"$encodedName\"");
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache');

    $fp = fopen($realFilePath, 'rb');
    fseek($fp, $start);
    $chunkSize = 8192;
    $sent = 0;
    while ($sent < $length && !feof($fp)) {
        $read = min($chunkSize, $length - $sent);
        echo fread($fp, $read);
        $sent += $read;
        flush();
    }
    fclose($fp);
} else {
    $encodedName = rawurlencode($originalName);
    header('Content-Type: ' . $mimeType);
    header("Content-Disposition: inline; filename*=UTF-8''$encodedName; filename=\"$encodedName\"");
    header('Content-Length: ' . $fileSize);
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache');
    header('X-Accel-Redirect: /protected-uploads/' . ltrim(str_replace(BASE_UPLOAD_DIR, '', $realFilePath), '/'));

    readfile($realFilePath);
}
