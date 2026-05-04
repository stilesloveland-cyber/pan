<?php
require_once __DIR__ . '/functions.php';

$password = isset($_GET['password']) ? $_GET['password'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';
$userDir = isset($_GET['userDir']) ? $_GET['userDir'] : '';

if (empty($file) || empty($password)) {
    die('缺少必要参数');
}

$user = findUserByPassword($password);
if (!$user) {
    die('未注册的账户，请先注册');
}

$isAdminUser = isAdmin($user);

if ($isAdminUser && !empty($userDir)) {
    $filePath = $baseUploadDir . $userDir . '/' . basename($file);
} else {
    $userDirPath = getUserDir($password);
    $filePath = $userDirPath . basename($file);
}

if (!file_exists($filePath) || !is_file($filePath)) {
    die('文件不存在');
}

if (!$isAdminUser && strpos(realpath($filePath), realpath($userDirPath)) !== 0) {
    die('无效的文件路径');
}

$originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $file);

$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'txt' => 'text/plain', 'md' => 'text/markdown',
    'html' => 'text/html', 'css' => 'text/css',
    'js' => 'application/javascript',
    'pdf' => 'application/pdf',
    'mp4' => 'video/mp4', 'avi' => 'video/x-msvideo', 'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac'
];

$mimeType = isset($mimeTypes[$fileExt]) ? $mimeTypes[$fileExt] : 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $originalName . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
