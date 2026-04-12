<?php
// 配置
$baseUploadDir = '/var/www/uploads/'; // Web根目录外部的存储路径
$maxTotalSize = 10 * 1024 * 1024 * 1024; // 10GB
$maxUserSize = 2 * 1024 * 1024 * 1024; // 单用户最大空间2GB
$maxPublicSize = 5 * 1024 * 1024 * 1024; // 公共空间最大5GB
$adminPassword = 'admin'; // 管理员密码
$usersFile = $baseUploadDir . 'users.json'; // 用户存储文件

// 确保基础上传目录存在
if (!file_exists($baseUploadDir)) {
    mkdir($baseUploadDir, 0755, true);
}

// 确保公共空间目录存在
$publicDir = $baseUploadDir . 'public/';
if (!file_exists($publicDir)) {
    mkdir($publicDir, 0755, true);
}

// 初始化用户文件
if (!file_exists($usersFile)) {
    $initialUsers = [
        'admin' => [
            'password' => md5($adminPassword),
            'registered' => time(),
            'role' => 'admin'
        ]
    ];
    file_put_contents($usersFile, json_encode($initialUsers));
}

// 读取用户列表
function getUsers() {
    global $usersFile;
    return json_decode(file_get_contents($usersFile), true);
}

// 检查用户是否已注册
function isUserRegistered($password) {
    global $adminPassword;
    $users = getUsers();
    $passwordHash = md5($password);
    
    // 检查管理员
    if ($password === $adminPassword) {
        return true;
    }
    
    // 检查普通用户
    foreach ($users as $user) {
        if (isset($user['password']) && $user['password'] === $passwordHash) {
            return true;
        }
    }
    return false;
}

// 获取用户密码
$password = isset($_GET['password']) ? $_GET['password'] : '';
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => '请输入密码']);
    exit;
}

// 验证用户是否注册
if (!isUserRegistered($password)) {
    echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
    exit;
}

// 检查是否为管理员
$isAdmin = ($password === $adminPassword);

// 生成用户目录名（使用密码的MD5作为目录名，确保安全性）
$userDir = $baseUploadDir . md5($password) . '/';

// 确保用户目录存在
if (!file_exists($userDir) && !$isAdmin) {
    mkdir($userDir, 0755, true);
}

// 计算当前用户已使用的空间
function calculateDirectorySize($dir) {
    $size = 0;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . $file;
            if (is_file($path)) {
                $size += filesize($path);
            } elseif (is_dir($path)) {
                $size += calculateDirectorySize($path);
            }
        }
    }
    return $size;
}

// 获取文件列表
$files = [];

if ($isAdmin) {
    // 管理员可以看到所有用户的文件
    $userDirs = scandir($baseUploadDir);
    foreach ($userDirs as $userDirName) {
        if ($userDirName != '.' && $userDirName != '..' && is_dir($baseUploadDir . $userDirName)) {
            $currentUserDir = $baseUploadDir . $userDirName . '/';
            $handle = opendir($currentUserDir);
            
            if ($handle) {
                while (false !== ($entry = readdir($handle))) {
                    // 跳过隐藏文件和目录
                    if ($entry != '.' && $entry != '..' && !is_dir($currentUserDir . $entry)) {
                        // 提取原始文件名（移除前面的唯一ID）
                        $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $entry);
                        
                        $files[] = [
                            'name' => $originalName,
                            'size' => filesize($currentUserDir . $entry),
                            'date' => filemtime($currentUserDir . $entry),
                            'filename' => $entry,
                            'userDir' => $userDirName // 记录文件所属用户目录
                        ];
                    }
                }
                closedir($handle);
            }
        }
    }
} else {
    // 普通用户只能看到自己的文件
    $handle = opendir($userDir);
    
    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            // 跳过隐藏文件和目录
            if ($entry != '.' && $entry != '..' && !is_dir($userDir . $entry)) {
                // 提取原始文件名（移除前面的唯一ID）
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

// 计算空间使用情况
$usedSize = calculateDirectorySize($userDir);
$globalUsedSize = calculateGlobalSize($baseUploadDir);
$publicUsedSize = calculateDirectorySize($publicDir);

// 按修改时间排序（最新的在前）
usort($files, function($a, $b) {
    return $b['date'] - $a['date'];
});

// 获取公共空间文件
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

// 按修改时间排序公共空间文件
usort($publicFiles, function($a, $b) {
    return $b['date'] - $a['date'];
});

// 计算全局总空间使用情况
function calculateGlobalSize($dir) {
    $size = 0;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . $file;
            if (is_file($path)) {
                $size += filesize($path);
            } elseif (is_dir($path)) {
                $size += calculateDirectorySize($path);
            }
        }
    }
    return $size;
}

// 返回JSON格式
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
?>