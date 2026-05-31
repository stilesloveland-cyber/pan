<?php
/**
 * WPAN 个人网盘系统 - 文件分享
 * 版本: 2.0 (会话重构版)
 */
require_once __DIR__ . '/functions.php';

initSystem();

header('Content-Type: application/json; charset=utf-8');

// ========== 创建分享链接 ==========
if (isset($_POST['action']) && $_POST['action'] === 'create_share') {
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    $expiry = isset($_POST['expiry']) ? (int)$_POST['expiry'] : 1;
    $userDirParam = isset($_POST['userDir']) ? $_POST['userDir'] : '';

    if (empty($file)) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }

    // 认证（会话优先，兼容密码参数）
    $user = getCurrentUser();
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!$user && !empty($password)) {
        $user = findUserByPassword($password);
    }

    if (!$user) {
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }

    $isAdminUser = $user['data']['role'] === 'admin';
    $fileName = basename($file);
    $subDir = isset($_POST['dir']) ? trim($_POST['dir']) : '';

    // 确定文件路径（支持子文件夹）
    if ($isAdminUser && !empty($userDirParam)) {
        $basePath = BASE_UPLOAD_DIR . basename($userDirParam) . '/';
        $shareUserDir = basename($userDirParam);
    } else {
        $userDirPath = getCurrentUserDir();
        if (!$userDirPath && !empty($password)) {
            $userDirPath = getUserDir($password);
        }
        if (!$userDirPath) {
            echo json_encode(['success' => false, 'message' => '无法确定用户目录']);
            exit;
        }
        $basePath = $userDirPath;
        $shareUserDir = basename($userDirPath);
    }

    if (!empty($subDir)) {
        $safeSubDir = str_replace(['..', '\\'], '', $subDir);
        $safeSubDir = trim($safeSubDir, '/');
        $filePath = $basePath . $safeSubDir . '/' . $fileName;
        $shareUserDir = $shareUserDir . '/' . $safeSubDir;
    } else {
        $filePath = $basePath . $fileName;
    }

    // 安全检查
    $realFilePath = realpath($filePath);
    if ($realFilePath === false || !file_exists($realFilePath) || !is_file($realFilePath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        exit;
    }

    $shareId = uniqid('share_', true);
    $expiryTime = time() + ($expiry * 86400);

    $shareData = [
        'file' => $fileName,
        'userDir' => $shareUserDir,
        'expiry' => $expiryTime,
        'created' => time()
    ];

    file_put_contents(SHARE_DIR . $shareId . '.json', json_encode($shareData));

    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $shareUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/share.php?id=' . $shareId;

    echo json_encode([
        'success' => true,
        'message' => '分享链接生成成功',
        'shareUrl' => $shareUrl,
        'expiry' => $expiryTime
    ]);
    exit;
}

// ========== 访问分享链接（无需登录） ==========
if (isset($_GET['id'])) {
    $shareId = basename($_GET['id']);
    $shareFile = SHARE_DIR . $shareId . '.json';

    if (!file_exists($shareFile)) {
        http_response_code(404);
        die('分享链接不存在或已过期');
    }

    $shareData = json_decode(file_get_contents($shareFile), true);

    if (time() > $shareData['expiry']) {
        unlink($shareFile);
        http_response_code(410);
        die('分享链接已过期');
    }

    $filePath = BASE_UPLOAD_DIR . basename($shareData['userDir']) . '/' . basename($shareData['file']);
    $realFilePath = realpath($filePath);

    if ($realFilePath === false || !file_exists($realFilePath) || !is_file($realFilePath)) {
        http_response_code(404);
        die('分享的文件不存在');
    }

    $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', basename($shareData['file']));
    $fileExt = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/markdown; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
        'flac' => 'audio/flac', 'ogg' => 'audio/ogg'
    ];

    $mimeType = isset($mimeTypes[$fileExt]) ? $mimeTypes[$fileExt] : 'application/octet-stream';

    $encodedName = rawurlencode($originalName);
    header('Content-Type: ' . $mimeType);
    header("Content-Disposition: inline; filename*=UTF-8''$encodedName; filename=\"$encodedName\"");
    header('Content-Length: ' . filesize($realFilePath));
    header('X-Content-Type-Options: nosniff');

    readfile($realFilePath);
    exit;
}

// 清理过期分享
cleanExpiredShares();

echo json_encode(['success' => false, 'message' => '请提供分享ID']);
