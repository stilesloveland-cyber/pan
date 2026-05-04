<?php
require_once __DIR__ . '/functions.php';

$password = isset($_GET['password']) ? $_GET['password'] : '';
if (empty($password)) {
    die('请输入密码');
}

$user = findUserByPassword($password);
if (!$user) {
    die('未注册的账户，请先注册');
}

$isAdminUser = isAdmin($user);
$userDir = getUserDir($password);

if (!isset($_GET['file'])) {
    die('没有提供文件名');
}

$fileName = $_GET['file'];
$userDirParam = isset($_GET['userDir']) ? $_GET['userDir'] : '';

if ($isAdminUser && !empty($userDirParam)) {
    $filePath = $baseUploadDir . $userDirParam . '/' . basename($fileName);
} else {
    $filePath = $userDir . basename($fileName);
}

if (!file_exists($filePath) || !is_file($filePath)) {
    die('文件不存在');
}

if (!$isAdminUser && strpos(realpath($filePath), realpath($userDir)) !== 0) {
    die('无效的文件路径');
}

$originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $fileName);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $originalName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

ob_clean();
flush();

readfile($filePath);
