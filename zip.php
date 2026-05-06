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

$files = isset($_GET['files']) ? $_GET['files'] : (isset($_POST['files']) ? $_POST['files'] : '');
if (empty($files)) {
    die('请选择要下载的文件');
}

// 解析文件名列表（逗号分隔）
$fileNames = explode(',', $files);
$fileNames = array_map('trim', $fileNames);
$fileNames = array_filter($fileNames);
$fileNames = array_map('basename', $fileNames);

// 创建临时 ZIP 文件
$zipPath = sys_get_temp_dir() . '/wpan_' . uniqid() . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die('无法创建 ZIP 文件');
}

$addedCount = 0;

foreach ($fileNames as $fileName) {
    if (empty($fileName)) continue;

    // 确定文件路径
    if ($isAdminUser && isset($_GET['userDir'])) {
        $filePath = BASE_UPLOAD_DIR . basename($_GET['userDir']) . '/' . $fileName;
    } elseif ($userDir) {
        $filePath = $userDir . $fileName;
    } else {
        continue;
    }

    if (file_exists($filePath) && is_file($filePath)) {
        $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $fileName);
        $zip->addFile($filePath, $originalName);
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
