<?php
/**
 * WPAN 个人网盘系统 - ZIP 打包下载
 */
require_once __DIR__ . '/functions.php';

initSystem();

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
$userDir = getCurrentUserDir();
if (!$userDir && !empty($password)) {
    $userDir = getUserDir($password);
}
if (!$userDir) die('无法确定用户目录');

// 接收文件名列表（JSON 数组，或逗号分隔）
$filesRaw = isset($_GET['files']) ? $_GET['files'] : '';
if (empty($filesRaw)) die('请选择要下载的文件');

// 尝试 JSON 解析，失败则按逗号分隔
$fileNames = json_decode($filesRaw, true);
if (!is_array($fileNames)) {
    $fileNames = explode(',', $filesRaw);
}
$fileNames = array_map('trim', $fileNames);
$fileNames = array_filter($fileNames);
$fileNames = array_map('basename', $fileNames);

// 子文件夹路径
$subDir = isset($_GET['dir']) ? trim($_GET['dir']) : '';
if (!empty($subDir)) {
    $safeSubDir = str_replace(['..', '\\'], '', $subDir);
    $safeSubDir = trim($safeSubDir, '/');
}

// 创建临时 ZIP 文件
$zipPath = sys_get_temp_dir() . '/wpan_' . uniqid() . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die('无法创建 ZIP 文件');
}

$addedCount = 0;

foreach ($fileNames as $fileName) {
    if (empty($fileName)) continue;

    // 拼接文件路径（含子文件夹）
    if ($isAdminUser && isset($_GET['userDir'])) {
        $basePath = BASE_UPLOAD_DIR . basename($_GET['userDir']) . '/';
    } else {
        $basePath = $userDir;
    }

    if (!empty($safeSubDir)) {
        $filePath = $basePath . $safeSubDir . '/' . $fileName;
    } else {
        $filePath = $basePath . $fileName;
    }

    if (file_exists($filePath) && is_file($filePath)) {
        $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $fileName);
        // 如果文件在子文件夹中，保留相对路径结构
        $zipPathName = !empty($safeSubDir) ? $safeSubDir . '/' . $originalName : $originalName;
        $zip->addFile($filePath, $zipPathName);
        $addedCount++;
    }
}

$zip->close();

if ($addedCount === 0) {
    @unlink($zipPath);
    die('没有可下载的文件');
}

// 输出 ZIP
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="files_' . date('Ymd') . '.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache');

readfile($zipPath);
@unlink($zipPath);
