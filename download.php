<?php
/**
 * WPAN 个人网盘系统 - 文件下载
 * 版本: 2.0 (会话重构版)
 */
require_once __DIR__ . '/functions.php';

initSystem();

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
$userDir = getCurrentUserDir();
if (!$userDir && !empty($password)) {
    $userDir = getUserDir($password);
}

if (!isset($_GET['file'])) {
    die('没有提供文件名');
}

$fileName = basename($_GET['file']);
$userDirParam = isset($_GET['userDir']) ? basename($_GET['userDir']) : '';
$subDir = isset($_GET['dir']) ? trim($_GET['dir']) : '';

// 确定文件路径（支持子文件夹）
$basePath = '';
if ($isAdminUser && !empty($userDirParam)) {
    $basePath = BASE_UPLOAD_DIR . $userDirParam . '/';
} elseif ($userDir) {
    $basePath = $userDir;
} else {
    die('无法确定文件路径');
}

// 安全拼接子文件夹路径
if (!empty($subDir)) {
    $safeSubDir = str_replace(['..', '\\'], '', $subDir);
    $safeSubDir = trim($safeSubDir, '/');
    $filePath = $basePath . $safeSubDir . '/' . $fileName;
} else {
    $filePath = $basePath . $fileName;
}

// 安全检查：防止路径穿越
$realFilePath = realpath($filePath);
if ($realFilePath === false || !file_exists($realFilePath) || !is_file($realFilePath)) {
    die('文件不存在');
}

// 非管理员只能访问自己目录的文件
if (!$isAdminUser && $userDir) {
    $realUserDir = realpath($userDir);
    if ($realUserDir === false || strpos($realFilePath, $realUserDir) !== 0) {
        die('无效的文件路径');
    }
}

$originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $fileName);

// 安全地设置下载头
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($originalName) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($realFilePath));

ob_clean();
flush();

readfile($realFilePath);
