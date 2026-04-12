<?php
// 配置
$baseUploadDir = '/var/www/uploads/'; // Web根目录外部的存储路径
$adminPassword = 'admin'; // 管理员密码
$shareDir = '/var/www/uploads/shares/'; // 分享记录存储目录

// 确保分享记录目录存在
if (!file_exists($shareDir)) {
    mkdir($shareDir, 0755, true);
}

// 处理生成分享链接请求
if (isset($_POST['action']) && $_POST['action'] === 'create_share') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    $expiry = isset($_POST['expiry']) ? $_POST['expiry'] : '1'; // 默认1天
    $userDir = isset($_POST['userDir']) ? $_POST['userDir'] : '';

    if (empty($password) || empty($file)) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }

    // 检查是否为管理员
    $isAdmin = ($password === $adminPassword);

    // 确定文件路径
    if ($isAdmin && !empty($userDir)) {
        // 管理员可以分享所有用户的文件
        $filePath = $baseUploadDir . $userDir . '/' . basename($file);
    } else {
        // 普通用户只能分享自己的文件
        $userDirPath = $baseUploadDir . md5($password) . '/';
        $filePath = $userDirPath . basename($file);
    }

    // 安全检查：确保文件存在
    if (!file_exists($filePath) || !is_file($filePath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        exit;
    }

    // 安全检查：确保文件路径在允许的范围内
    if (!$isAdmin && strpos(realpath($filePath), realpath($userDirPath)) !== 0) {
        echo json_encode(['success' => false, 'message' => '无效的文件路径']);
        exit;
    }

    // 生成唯一分享ID
    $shareId = uniqid();

    // 计算过期时间
    $expiryTime = time() + ($expiry * 24 * 3600);

    // 保存分享记录
    $shareData = [
        'file' => $file,
        'userDir' => $userDir,
        'expiry' => $expiryTime,
        'created' => time()
    ];

    file_put_contents($shareDir . $shareId . '.json', json_encode($shareData));

    // 生成分享链接
    $shareUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/pan/share.php?id=' . $shareId;

    echo json_encode(['success' => true, 'message' => '分享链接生成成功', 'shareUrl' => $shareUrl, 'expiry' => $expiryTime]);
    exit;
}

// 处理访问分享链接请求
if (isset($_GET['id'])) {
    $shareId = $_GET['id'];
    $shareFile = $shareDir . $shareId . '.json';

    if (!file_exists($shareFile)) {
        die('分享链接不存在或已过期');
    }

    // 读取分享记录
    $shareData = json_decode(file_get_contents($shareFile), true);

    // 检查是否过期
    if (time() > $shareData['expiry']) {
        // 删除过期的分享记录
        unlink($shareFile);
        die('分享链接已过期');
    }

    // 确定文件路径
    if (!empty($shareData['userDir'])) {
        // 分享的是其他用户的文件
        $filePath = $baseUploadDir . $shareData['userDir'] . '/' . basename($shareData['file']);
    } else {
        // 分享的是自己的文件
        // 注意：这里无法确定原始用户，所以需要遍历所有用户目录查找文件
        $found = false;
        $userDirs = scandir($baseUploadDir);
        foreach ($userDirs as $dir) {
            if ($dir != '.' && $dir != '..' && $dir != 'public' && $dir != 'shares' && is_dir($baseUploadDir . $dir)) {
                $potentialPath = $baseUploadDir . $dir . '/' . basename($shareData['file']);
                if (file_exists($potentialPath)) {
                    $filePath = $potentialPath;
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            die('分享的文件不存在');
        }
    }

    // 安全检查：确保文件存在
    if (!file_exists($filePath) || !is_file($filePath)) {
        die('分享的文件不存在');
    }

    // 提取原始文件名
    $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $shareData['file']);

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
    exit;
}

// 清理过期的分享记录
function cleanExpiredShares() {
    global $shareDir;
    $files = scandir($shareDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $shareData = json_decode(file_get_contents($shareDir . $file), true);
            if (time() > $shareData['expiry']) {
                unlink($shareDir . $file);
            }
        }
    }
}

// 定期清理过期分享记录
cleanExpiredShares();
?>