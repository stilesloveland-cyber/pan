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

// 管理员获取所有用户文件
$files = [];
$skipDirs = ['public', 'shares', 'cache'];
if ($isAdminUser) {
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
} elseif ($userDir) {
    $files = getFileList($userDir);
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
    'files' => $files,
    'publicFiles' => $publicFiles,
    'usedSize' => $usedSize,
    'maxSize' => MAX_USER_SIZE,
    'globalUsedSize' => $globalUsedSize,
    'globalMaxSize' => MAX_TOTAL_SIZE,
    'publicUsedSize' => $publicUsedSize,
    'publicMaxSize' => MAX_PUBLIC_SIZE
]);
