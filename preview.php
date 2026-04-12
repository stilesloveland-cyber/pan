<?php
// 配置
$baseUploadDir = '/var/www/uploads/'; // Web根目录外部的存储路径
$adminPassword = 'admin'; // 管理员密码
$usersFile = $baseUploadDir . 'users.json'; // 用户存储文件

// 确保目录存在
if (!file_exists($baseUploadDir)) {
    mkdir($baseUploadDir, 0755, true);
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

// 获取参数
$file = isset($_GET['file']) ? $_GET['file'] : '';
$password = isset($_GET['password']) ? $_GET['password'] : '';
$userDir = isset($_GET['userDir']) ? $_GET['userDir'] : '';

if (empty($file) || empty($password)) {
    die('缺少必要参数');
}

// 验证用户是否注册
if (!isUserRegistered($password)) {
    die('未注册的账户，请先注册');
}

// 检查是否为管理员
$isAdmin = ($password === $adminPassword);

// 确定文件路径
if ($isAdmin && !empty($userDir)) {
    // 管理员可以预览所有用户的文件
    $filePath = $baseUploadDir . $userDir . '/' . basename($file);
} else {
    // 普通用户只能预览自己的文件
    $userDirPath = $baseUploadDir . md5($password) . '/';
    $filePath = $userDirPath . basename($file);
}

// 安全检查：确保文件存在
if (!file_exists($filePath) || !is_file($filePath)) {
    die('文件不存在');
}

// 安全检查：确保文件路径在允许的范围内
if (!$isAdmin && strpos(realpath($filePath), realpath($userDirPath)) !== 0) {
    die('无效的文件路径');
}

// 提取原始文件名
$originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $file);

// 获取文件扩展名
$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// 根据文件类型设置MIME类型
$mimeType = '';
switch ($fileExt) {
    // 图片类型
    case 'jpg':
    case 'jpeg':
        $mimeType = 'image/jpeg';
        break;
    case 'png':
        $mimeType = 'image/png';
        break;
    case 'gif':
        $mimeType = 'image/gif';
        break;
    case 'webp':
        $mimeType = 'image/webp';
        break;
    // 文本类型
    case 'txt':
        $mimeType = 'text/plain';
        break;
    case 'md':
        $mimeType = 'text/markdown';
        break;
    case 'html':
        $mimeType = 'text/html';
        break;
    case 'css':
        $mimeType = 'text/css';
        break;
    case 'js':
        $mimeType = 'application/javascript';
        break;
    // PDF类型
    case 'pdf':
        $mimeType = 'application/pdf';
        break;
    // 视频类型
    case 'mp4':
        $mimeType = 'video/mp4';
        break;
    case 'avi':
        $mimeType = 'video/x-msvideo';
        break;
    case 'mov':
        $mimeType = 'video/quicktime';
        break;
    // 音频类型
    case 'mp3':
        $mimeType = 'audio/mpeg';
        break;
    case 'wav':
        $mimeType = 'audio/wav';
        break;
    case 'flac':
        $mimeType = 'audio/flac';
        break;
    default:
        $mimeType = 'application/octet-stream';
        break;
}

// 设置HTTP头
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $originalName . '"');
header('Content-Length: ' . filesize($filePath));

// 输出文件内容
readfile($filePath);
?>