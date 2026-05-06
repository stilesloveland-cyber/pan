<?php
/**
 * WPAN 个人网盘系统 - 文件列表 API
 * 版本: 2.0 (会话重构版)
 */
require_once __DIR__ . '/functions.php';

initSystem();

header('Content-Type: application/json; charset=utf-8');

// 认证（会话优先，兼容密码参数）
$user = getCurrentUser();
$password = isset($_GET['password']) ? $_GET['password'] : '';

if (!$user && !empty($password)) {
    $user = findUserByPassword($password);
}

if (!$user) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$isAdminUser = $user['data']['role'] === 'admin';
$userDir = getCurrentUserDir();
if (!$userDir && !empty($password)) {
    $userDir = getUserDir($password);
}

// 当前浏览的目录（支持子文件夹）
$currentListDir = $userDir;
$currentPath = '';
if (isset($_GET['dir']) && !empty($_GET['dir']) && $userDir) {
    $reqDir = safeJoinPath($userDir, $_GET['dir']);
    if (strpos($reqDir, $userDir) === 0 && is_dir($reqDir)) {
        $currentListDir = $reqDir;
        $currentPath = trim(str_replace($userDir, '', $reqDir), '/');
    }
}

// 获取文件夹列表
$folders = $currentListDir ? getFolderList($currentListDir) : [];

// 管理员获取所有用户文件（仅在根目录时）
$files = [];
$skipDirs = ['public', 'shares', 'cache'];
if ($isAdminUser && empty($currentPath)) {
    $userDirs = scandir(BASE_UPLOAD_DIR);
    foreach ($userDirs as $userDirName) {
        if ($userDirName != '.' && $userDirName != '..' && !in_array($userDirName, $skipDirs) && is_dir(BASE_UPLOAD_DIR . $userDirName)) {
            $currentUserDir = BASE_UPLOAD_DIR . $userDirName . '/';
            $subFiles = getFileList($currentUserDir);
            foreach ($subFiles as &$f) {
                $f['userDir'] = $userDirName;
            }
            $files = array_merge($files, $subFiles);
        }
    }
} elseif ($currentListDir) {
    $files = getFileList($currentListDir);
}

// 公共文件
$publicFiles = getFileList(PUBLIC_DIR);
foreach ($publicFiles as &$f) {
    $f['isPublic'] = true;
}

// 排序（默认按时间倒序）
usort($files, function($a, $b) {
    return $b['date'] - $a['date'];
});
usort($publicFiles, function($a, $b) {
    return $b['date'] - $a['date'];
});

// 空间统计
$usedSize = $userDir ? calculateDirectorySize($userDir) : 0;
$globalUsedSize = calculateGlobalSize();
$publicUsedSize = calculateDirectorySize(PUBLIC_DIR);

echo json_encode([
    'success' => true,
    'admin' => $isAdminUser,
    'currentPath' => $currentPath,
    'folders' => $folders,
    'files' => $files,
    'publicFiles' => $publicFiles,
    'usedSize' => $usedSize,
    'maxSize' => MAX_USER_SIZE,
    'globalUsedSize' => $globalUsedSize,
    'globalMaxSize' => MAX_TOTAL_SIZE,
    'publicUsedSize' => $publicUsedSize,
    'publicMaxSize' => MAX_PUBLIC_SIZE
]);
