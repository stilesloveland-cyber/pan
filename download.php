<?php
// 配置
$baseUploadDir = '/var/www/uploads/'; // Web根目录外部的存储路径
$adminPassword = 'admin'; // 管理员密码

// 获取用户密码
$password = isset($_GET['password']) ? $_GET['password'] : '';
if (empty($password)) {
    die('请输入密码');
}

// 检查是否为管理员
$isAdmin = ($password === $adminPassword);

// 生成用户目录名（使用密码的MD5作为目录名，确保安全性）
$userDir = $baseUploadDir . md5($password) . '/';

// 检查是否提供了文件名
if (!isset($_GET['file'])) {
    die('没有提供文件名');
}

$fileName = $_GET['file'];
$userDirParam = isset($_GET['userDir']) ? $_GET['userDir'] : '';

// 确定文件路径
if ($isAdmin && !empty($userDirParam)) {
    // 管理员可以下载所有用户的文件
    $filePath = $baseUploadDir . $userDirParam . '/' . basename($fileName);
} else {
    // 普通用户只能下载自己的文件
    $filePath = $userDir . basename($fileName);
}

// 安全检查：确保文件存在
if (!file_exists($filePath) || !is_file($filePath)) {
    die('文件不存在');
}

// 安全检查：确保文件路径在允许的范围内
if (!$isAdmin && strpos(realpath($filePath), realpath($userDir)) !== 0) {
    die('无效的文件路径');
}

// 提取原始文件名（移除前面的唯一ID）
$originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $fileName);

// 设置HTTP头
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $originalName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// 清除输出缓冲区
ob_clean();
flush();

// 读取并输出文件内容
readfile($filePath);
exit;
?>