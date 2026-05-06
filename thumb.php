<?php
/**
 * WPAN 个人网盘系统 - 缩略图生成
 * 自动为图片生成缩略图并缓存
 */
require_once __DIR__ . '/functions.php';

initSystem();

$file = isset($_GET['file']) ? $_GET['file'] : '';
$userDirParam = isset($_GET['userDir']) ? $_GET['userDir'] : '';
$subDir = isset($_GET['dir']) ? trim($_GET['dir']) : '';

if (empty($file)) {
    http_response_code(400);
    die('缺少参数');
}

// 认证
$user = getCurrentUser();
$password = isset($_GET['password']) ? $_GET['password'] : '';
if (!$user && !empty($password)) {
    $user = findUserByPassword($password);
}
if (!$user) {
    http_response_code(403);
    die('请先登录');
}

$isAdminUser = $user['data']['role'] === 'admin';
$fileName = basename($file);

// 确定源文件路径
if ($isAdminUser && !empty($userDirParam)) {
    $sourcePath = BASE_UPLOAD_DIR . basename($userDirParam) . '/' . $fileName;
} else {
    $userDirPath = getCurrentUserDir();
    if (!$userDirPath && !empty($password)) {
        $userDirPath = getUserDir($password);
    }
    if (!$userDirPath) die('无法确定路径');
    
    if (!empty($subDir)) {
        $safeSubDir = str_replace(['..', '\\'], '', $subDir);
        $safeSubDir = trim($safeSubDir, '/');
        $sourcePath = $userDirPath . $safeSubDir . '/' . $fileName;
    } else {
        $sourcePath = $userDirPath . $fileName;
    }
}

// 安全检查
$realPath = realpath($sourcePath);
if ($realPath === false || !file_exists($realPath) || !is_file($realPath)) {
    http_response_code(404);
    die('文件不存在');
}

// 只处理图片
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
if (!in_array($ext, $imageExts)) {
    // 非图片直接输出原文件
    header('Content-Type: ' . mime_content_type($realPath));
    readfile($realPath);
    exit;
}

// 缩略图缓存路径
$thumbDir = THUMB_DIR;
if (!file_exists($thumbDir)) mkdir($thumbDir, 0755, true);
$thumbFile = $thumbDir . md5($realPath . THUMB_WIDTH . THUMB_HEIGHT) . '.webp';

// 如果缩略图已缓存，直接输出
if (file_exists($thumbFile) && filemtime($thumbFile) >= filemtime($realPath)) {
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=86400');
    readfile($thumbFile);
    exit;
}

// 生成缩略图
$srcImage = null;
switch ($ext) {
    case 'jpg':
    case 'jpeg':
        $srcImage = @imagecreatefromjpeg($realPath);
        break;
    case 'png':
        $srcImage = @imagecreatefrompng($realPath);
        break;
    case 'gif':
        $srcImage = @imagecreatefromgif($realPath);
        break;
    case 'webp':
        $srcImage = @imagecreatefromwebp($realPath);
        break;
    case 'bmp':
        $srcImage = @imagecreatefrombmp($realPath);
        break;
}

if (!$srcImage) {
    // GD 库不支持，直接输出原图
    header('Content-Type: ' . mime_content_type($realPath));
    readfile($realPath);
    exit;
}

// 计算缩略图尺寸
$srcW = imagesx($srcImage);
$srcH = imagesy($srcImage);
$thumbW = THUMB_WIDTH;
$thumbH = THUMB_HEIGHT;

$ratio = min($thumbW / $srcW, $thumbH / $srcH);
$newW = (int)($srcW * $ratio);
$newH = (int)($srcH * $ratio);

$thumbImage = imagecreatetruecolor($newW, $newH);
imagealphablending($thumbImage, false);
imagesavealpha($thumbImage, true);
imagecopyresampled($thumbImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

// 保存为 webp
imagewebp($thumbImage, $thumbFile, 80);
imagedestroy($srcImage);
imagedestroy($thumbImage);

header('Content-Type: image/webp');
header('Cache-Control: public, max-age=86400');
readfile($thumbFile);
