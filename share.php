<?php
require_once __DIR__ . '/functions.php';

initSystem();

if (isset($_POST['action']) && $_POST['action'] === 'create_share') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    $expiry = isset($_POST['expiry']) ? $_POST['expiry'] : '1';
    $userDir = isset($_POST['userDir']) ? $_POST['userDir'] : '';

    if (empty($password) || empty($file)) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }

    $user = findUserByPassword($password);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户验证失败']);
        exit;
    }

    $isAdminUser = isAdmin($user);

    if ($isAdminUser && !empty($userDir)) {
        $filePath = $baseUploadDir . $userDir . '/' . basename($file);
    } else {
        $userDirPath = getUserDir($password);
        $filePath = $userDirPath . basename($file);
    }

    if (!file_exists($filePath) || !is_file($filePath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        exit;
    }

    if (!$isAdminUser && strpos(realpath($filePath), realpath($userDirPath)) !== 0) {
        echo json_encode(['success' => false, 'message' => '无效的文件路径']);
        exit;
    }

    $shareId = uniqid();
    $expiryTime = time() + ($expiry * 24 * 3600);

    $shareData = [
        'file' => $file,
        'userDir' => $isAdminUser ? $userDir : md5($password),
        'expiry' => $expiryTime,
        'created' => time()
    ];

    file_put_contents($shareDir . $shareId . '.json', json_encode($shareData));

    $shareUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/pan/share.php?id=' . $shareId;

    echo json_encode(['success' => true, 'message' => '分享链接生成成功', 'shareUrl' => $shareUrl, 'expiry' => $expiryTime]);
    exit;
}

if (isset($_GET['id'])) {
    $shareId = $_GET['id'];
    $shareFile = $shareDir . $shareId . '.json';

    if (!file_exists($shareFile)) {
        die('分享链接不存在或已过期');
    }

    $shareData = json_decode(file_get_contents($shareFile), true);

    if (time() > $shareData['expiry']) {
        unlink($shareFile);
        die('分享链接已过期');
    }

    $filePath = $baseUploadDir . $shareData['userDir'] . '/' . basename($shareData['file']);

    if (!file_exists($filePath) || !is_file($filePath)) {
        die('分享的文件不存在');
    }

    $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $shareData['file']);

    $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'txt' => 'text/plain', 'md' => 'text/markdown',
        'html' => 'text/html', 'css' => 'text/css',
        'js' => 'application/javascript',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4', 'avi' => 'video/x-msvideo', 'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac'
    ];

    $mimeType = isset($mimeTypes[$fileExt]) ? $mimeTypes[$fileExt] : 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $originalName . '"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
}

cleanExpiredShares();
