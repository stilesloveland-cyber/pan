<?php
require_once __DIR__ . '/functions.php';

initSystem();

$password = isset($_GET['password']) ? $_GET['password'] : '';
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => '请输入密码']);
    exit;
}

$user = findUserByPassword($password);
if (!$user) {
    echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
    exit;
}

$isAdminUser = isAdmin($user);
$userDir = getUserDir($password);

$files = [];

if ($isAdminUser) {
    $userDirs = scandir($baseUploadDir);
    foreach ($userDirs as $userDirName) {
        if ($userDirName != '.' && $userDirName != '..' && is_dir($baseUploadDir . $userDirName)) {
            $currentUserDir = $baseUploadDir . $userDirName . '/';
            $handle = opendir($currentUserDir);

            if ($handle) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != '.' && $entry != '..' && !is_dir($currentUserDir . $entry)) {
                        $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $entry);
                        $files[] = [
                            'name' => $originalName,
                            'size' => filesize($currentUserDir . $entry),
                            'date' => filemtime($currentUserDir . $entry),
                            'filename' => $entry,
                            'userDir' => $userDirName
                        ];
                    }
                }
                closedir($handle);
            }
        }
    }
} else {
    $handle = opendir($userDir);

    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..' && !is_dir($userDir . $entry)) {
                $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $entry);
                $files[] = [
                    'name' => $originalName,
                    'size' => filesize($userDir . $entry),
                    'date' => filemtime($userDir . $entry),
                    'filename' => $entry
                ];
            }
        }
        closedir($handle);
    }
}

$usedSize = calculateDirectorySize($userDir);
$globalUsedSize = calculateGlobalSize($baseUploadDir);
$publicUsedSize = calculateDirectorySize($publicDir);

usort($files, function($a, $b) {
    return $b['date'] - $a['date'];
});

$publicFiles = [];
$publicHandle = opendir($publicDir);
if ($publicHandle) {
    while (false !== ($entry = readdir($publicHandle))) {
        if ($entry != '.' && $entry != '..' && !is_dir($publicDir . $entry)) {
            $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $entry);
            $publicFiles[] = [
                'name' => $originalName,
                'size' => filesize($publicDir . $entry),
                'date' => filemtime($publicDir . $entry),
                'filename' => $entry,
                'isPublic' => true
            ];
        }
    }
    closedir($publicHandle);
}

usort($publicFiles, function($a, $b) {
    return $b['date'] - $a['date'];
});

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'files' => $files,
    'publicFiles' => $publicFiles,
    'usedSize' => $usedSize,
    'maxSize' => $maxUserSize,
    'globalUsedSize' => $globalUsedSize,
    'globalMaxSize' => $maxTotalSize,
    'publicUsedSize' => $publicUsedSize,
    'publicMaxSize' => $maxPublicSize
]);
