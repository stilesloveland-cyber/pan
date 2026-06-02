<?php
require_once __DIR__ . '/functions.php';

initSystem();

header('Content-Type: application/json; charset=utf-8');

// ========== Ping 检测 ==========
if (isset($_GET['action']) && $_GET['action'] === 'ping') {
    echo 'pong';
    exit;
}

// ========== 注销 ==========
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    logoutUser();
    echo json_encode(['success' => true, 'message' => '已退出登录']);
    exit;
}

// ========== 注册 ==========
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '请输入密码']);
        exit;
    }
    if (strlen($password) < 4) {
        echo json_encode(['success' => false, 'message' => '密码长度不能少于4位']);
        exit;
    }

    if (registerUser($password)) {
        // 注册后自动登录
        $user = loginUser($password);
        echo json_encode([
            'success' => true,
            'message' => '注册成功',
            'role' => $user['data']['role']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '该密码已被注册']);
    }
    exit;
}

// ========== 登录 ==========
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '请输入密码']);
        exit;
    }

    $user = loginUser($password);
    if ($user) {
        echo json_encode([
            'success' => true,
            'message' => '登录成功',
            'role' => $user['data']['role']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
    }
    exit;
}

// ========== 修改密码 ==========
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '请输入当前密码和新密码']);
        exit;
    }
    if (strlen($newPassword) < 4) {
        echo json_encode(['success' => false, 'message' => '新密码长度不能少于4位']);
        exit;
    }

    if (changeUserPassword($currentPassword, $newPassword)) {
        echo json_encode(['success' => true, 'message' => '密码修改成功，请重新登录']);
    } else {
        echo json_encode(['success' => false, 'message' => '当前密码错误']);
    }
    exit;
}

// ========== 认证（会话优先，兼容旧版密码参数） ==========
$user = getCurrentUser();
$password = '';

// 如果会话未登录，尝试密码参数（向后兼容）
if (!$user) {
    $password = isset($_POST['password']) ? $_POST['password'] : (isset($_GET['password']) ? $_GET['password'] : '');
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }
    $user = findUserByPassword($password);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
        exit;
    }
}

$isAdminUser = $user['data']['role'] === 'admin';
$userDir = $password ? getUserDir($password) : getCurrentUserDir();

if (!$userDir || (!file_exists($userDir) && !$isAdminUser)) {
    if ($userDir) mkdir($userDir, 0755, true);
}

// ========== 获取当前目录路径（支持子文件夹） ==========
$currentDir = $userDir;
$reqDirParam = isset($_POST['dir']) ? $_POST['dir'] : (isset($_GET['dir']) ? $_GET['dir'] : '');
if (!empty($reqDirParam)) {
    $reqDir = safeJoinPath($userDir, $reqDirParam);
    if (strpos($reqDir, $userDir) === 0) {
        $currentDir = $reqDir;
    }
}

// ========== 创建文件夹 ==========
if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folderName = isset($_POST['folder_name']) ? trim($_POST['folder_name']) : '';
    if (empty($folderName)) {
        echo json_encode(['success' => false, 'message' => '请输入文件夹名称']);
        exit;
    }
    if (createFolder($currentDir, $folderName)) {
        echo json_encode(['success' => true, 'message' => '文件夹创建成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '文件夹创建失败（可能已存在）']);
    }
    exit;
}

// ========== 获取文件夹列表（用于移动弹窗） ==========
if (isset($_GET['action']) && $_GET['action'] === 'list_folders') {
    $allFolders = [];
    if ($userDir) {
        $allFolders = getAllFoldersRecursive($userDir, $userDir, '');
    }
    echo json_encode(['success' => true, 'folders' => $allFolders]);
    exit;
}

// ========== 移动文件/文件夹 ==========
if (isset($_POST['action']) && $_POST['action'] === 'move') {
    $targetDir = isset($_POST['target_dir']) ? trim($_POST['target_dir']) : '';
    $itemName = isset($_POST['item']) ? basename(trim($_POST['item'])) : '';

    if (empty($itemName) || empty($targetDir)) {
        echo json_encode(['success' => false, 'message' => '缺少参数']);
        exit;
    }

    // 安全拼接目标路径
    $targetPath = safeJoinPath($userDir, $targetDir);
    if (strpos($targetPath, $userDir) !== 0) {
        echo json_encode(['success' => false, 'message' => '无效的目标路径']);
        exit;
    }

    $sourcePath = $userDir . $itemName;
    $destPath = $targetPath . $itemName;

    if (!file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'message' => '源文件不存在']);
        exit;
    }
    if (file_exists($destPath)) {
        echo json_encode(['success' => false, 'message' => '目标位置已存在同名文件']);
        exit;
    }

    if (rename($sourcePath, $destPath)) {
        clearSizeCache();
        echo json_encode(['success' => true, 'message' => '移动成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '移动失败']);
    }
    exit;
}

// ========== 重命名 ==========
if (isset($_POST['action']) && $_POST['action'] === 'rename') {
    $oldName = isset($_POST['old_name']) ? trim($_POST['old_name']) : '';
    $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
    if (empty($oldName) || empty($newName)) {
        echo json_encode(['success' => false, 'message' => '请提供原名称和新名称']);
        exit;
    }
    if (renameFileOrFolder($currentDir, $oldName, $newName)) {
        echo json_encode(['success' => true, 'message' => '重命名成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '重命名失败（文件不存在或新名称已存在）']);
    }
    exit;
}

// ========== 删除文件/文件夹 ==========
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $files = isset($_POST['files']) ? $_POST['files'] : (isset($_POST['file']) ? [$_POST['file']] : []);
    $userDirParam = isset($_POST['userDir']) ? $_POST['userDir'] : '';

    if (empty($files)) {
        echo json_encode(['success' => false, 'message' => '请选择要删除的文件']);
        exit;
    }

    $deletedCount = 0;
    $isPublicDelete = isset($_POST['is_public']) && $_POST['is_public'] === 'true';

    foreach ($files as $file) {
        $fileName = basename($file);

        if ($isPublicDelete) {
            $filePath = PUBLIC_DIR . $fileName;
        } elseif ($isAdminUser && !empty($userDirParam)) {
            $filePath = BASE_UPLOAD_DIR . basename($userDirParam) . '/' . $fileName;
        } else {
            // 普通用户：在当前目录（含子文件夹）中删除
            $filePath = $currentDir . $fileName;
        }

        if (file_exists($filePath)) {
            if (is_dir($filePath)) {
                if (rrmdir($filePath)) $deletedCount++;
            } elseif (is_file($filePath)) {
                if (unlink($filePath)) $deletedCount++;
            }
        }
    }

    clearSizeCache();

    if ($deletedCount > 0) {
        $currentUsedSize = $userDir ? calculateDirectorySize($userDir) : 0;
        echo json_encode([
            'success' => true,
            'message' => "成功删除 {$deletedCount} 个文件",
            'usedSize' => $currentUsedSize,
            'maxSize' => MAX_USER_SIZE
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '文件删除失败，文件可能不存在']);
    }
    exit;
}

// ========== 分片上传 - 接收单个分片 ==========
if (isset($_POST['action']) && $_POST['action'] === 'upload_chunk') {
    set_time_limit(300);
    $uploadId = isset($_POST['upload_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['upload_id']) : '';
    $chunkIndex = isset($_POST['chunk_index']) ? intval($_POST['chunk_index']) : 0;
    $totalChunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : 0;
    $fileName = isset($_POST['file_name']) ? $_POST['file_name'] : '';
    $fileSize = isset($_POST['file_size']) ? intval($_POST['file_size']) : 0;
    if ($fileSize <= 0 || $fileSize > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'message' => '文件大小参数无效']);
        exit;
    }
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';

    if (empty($uploadId) || empty($fileName) || !isset($_FILES['chunk'])) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }

    if (isBlockedExtension($fileName)) {
        echo json_encode(['success' => false, 'message' => '该文件类型不允许上传']);
        exit;
    }

    // 仅在第一个分片检查空间配额
    if ($chunkIndex === 0) {
        $globalUsedSize = calculateGlobalSize();
        $userUsedSize = $userDir ? calculateDirectorySize($userDir) : 0;
        $publicUsedSize = calculateDirectorySize(PUBLIC_DIR);

        if ($globalUsedSize + $fileSize > MAX_TOTAL_SIZE) {
            echo json_encode(['success' => false, 'message' => '全局空间不足']);
            exit;
        }
        if ($isPublic) {
            if ($publicUsedSize + $fileSize > MAX_PUBLIC_SIZE) {
                echo json_encode(['success' => false, 'message' => '公共空间不足']);
                exit;
            }
        } else {
            if ($userUsedSize + $fileSize > MAX_USER_SIZE) {
                echo json_encode(['success' => false, 'message' => '个人空间不足']);
                exit;
            }
        }
    }

    $chunkDir = CACHE_DIR . 'chunks/' . $uploadId . '/';
    if (!file_exists($chunkDir)) {
        mkdir($chunkDir, 0755, true);
    }

    $chunkFile = $chunkDir . $chunkIndex;
    if (move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '分片写入失败']);
    }
    exit;
}

// ========== 分片上传 - 合并分片 ==========
if (isset($_POST['action']) && $_POST['action'] === 'merge_chunks') {
    set_time_limit(300);
    $uploadId = isset($_POST['upload_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['upload_id']) : '';
    $totalChunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : 0;
    $fileName = isset($_POST['file_name']) ? $_POST['file_name'] : '';
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';

    if (empty($uploadId) || empty($fileName) || $totalChunks === 0) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }

    if (isBlockedExtension($fileName)) {
        echo json_encode(['success' => false, 'message' => '该文件类型不允许上传']);
        exit;
    }

    $chunkDir = CACHE_DIR . 'chunks/' . $uploadId . '/';
    if (!is_dir($chunkDir)) {
        echo json_encode(['success' => false, 'message' => '分片数据不存在']);
        exit;
    }

    // 验证所有分片已上传
    for ($i = 0; $i < $totalChunks; $i++) {
        if (!file_exists($chunkDir . $i)) {
            echo json_encode(['success' => false, 'message' => "分片 {$i} 缺失"]);
            exit;
        }
    }

    $uniqueName = uniqid() . '_' . $fileName;
    if ($isPublic) {
        $destination = PUBLIC_DIR . $uniqueName;
    } else {
        $destination = $currentDir . $uniqueName;
    }

    $targetDir = dirname($destination);
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // 合并所有分片
    $fp = @fopen($destination, 'wb');
    if (!$fp) {
        echo json_encode(['success' => false, 'message' => '无法创建目标文件']);
        exit;
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkFile = $chunkDir . $i;
        $cfp = fopen($chunkFile, 'rb');
        stream_copy_to_stream($cfp, $fp);
        fclose($cfp);
        unlink($chunkFile);
    }
    fclose($fp);

    if (!isSafeFile($destination, $fileName)) {
        @unlink($destination);
        @rmdir($chunkDir);
        echo json_encode(['success' => false, 'message' => '文件类型不允许上传']);
        exit;
    }

    // 清理分片目录
    @rmdir($chunkDir);

    clearSizeCache();

    $userUsedSize = $userDir ? calculateDirectorySize($userDir) : 0;
    $globalUsedSize = calculateGlobalSize();
    $publicUsedSize = calculateDirectorySize(PUBLIC_DIR);

    echo json_encode([
        'success' => true,
        'message' => '文件上传成功',
        'files' => [$uniqueName],
        'usedSize' => $userUsedSize,
        'maxSize' => MAX_USER_SIZE,
        'globalUsedSize' => $globalUsedSize,
        'globalMaxSize' => MAX_TOTAL_SIZE,
        'publicUsedSize' => $publicUsedSize,
        'publicMaxSize' => MAX_PUBLIC_SIZE
    ]);
    exit;
}

// ========== 分片上传 - 取消/清理 ==========
if (isset($_POST['action']) && $_POST['action'] === 'cancel_chunks') {
    $uploadId = isset($_POST['upload_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['upload_id']) : '';
    if (!empty($uploadId)) {
        $chunkDir = CACHE_DIR . 'chunks/' . $uploadId . '/';
        if (is_dir($chunkDir)) {
            $files = scandir($chunkDir);
            foreach ($files as $f) {
                if ($f != '.' && $f != '..') unlink($chunkDir . $f);
            }
            @rmdir($chunkDir);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// ========== 上传文件 ==========
if (isset($_FILES['files'])) {
    // 上传大小限制由 Nginx 和 php.ini 控制，运行时 ini_set 无效
    // 仅设置执行时间
    set_time_limit(300);

    $files = $_FILES['files'];
    $uploadedFiles = [];
    $errors = [];
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';

    $globalUsedSize = calculateGlobalSize();
    $userUsedSize = $userDir ? calculateDirectorySize($userDir) : 0;
    $publicUsedSize = calculateDirectorySize(PUBLIC_DIR);

    $fileList = [];
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $fileList[] = [
                'name' => $files['name'][$i],
                'size' => $files['size'][$i],
                'tmp' => $files['tmp_name'][$i],
                'error' => $files['error'][$i]
            ];
        }
    } else {
        $fileList[] = [
            'name' => $files['name'],
            'size' => $files['size'],
            'tmp' => $files['tmp_name'],
            'error' => $files['error']
        ];
    }

    foreach ($fileList as $file) {
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmp = $file['tmp'];
        $fileError = $file['error'];

        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "文件 {$fileName} 上传出错: " . getUploadErrorMsg($fileError);
            continue;
        }

        if (!isSafeFile($fileTmp, $fileName)) {
            $errors[] = "文件 {$fileName} 类型不允许上传";
            continue;
        }

        if ($globalUsedSize + $fileSize > MAX_TOTAL_SIZE) {
            $errors[] = "文件 {$fileName} 上传失败：全局空间不足";
            continue;
        }

        if ($isPublic) {
            if ($publicUsedSize + $fileSize > MAX_PUBLIC_SIZE) {
                $errors[] = "文件 {$fileName} 上传失败：公共空间不足";
                continue;
            }
        } else {
            if ($userUsedSize + $fileSize > MAX_USER_SIZE) {
                $errors[] = "文件 {$fileName} 上传失败：个人空间不足";
                continue;
            }
        }

        $uniqueName = uniqid() . '_' . $fileName;
        if ($isPublic) {
            $destination = PUBLIC_DIR . $uniqueName;
        } else {
            // 上传到当前子文件夹
            $destination = $currentDir . $uniqueName;
        }

        $targetDir = dirname($destination);
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (move_uploaded_file($fileTmp, $destination)) {
            $uploadedFiles[] = $uniqueName;
            if ($isPublic) {
                $publicUsedSize += $fileSize;
            } else {
                $userUsedSize += $fileSize;
            }
            $globalUsedSize += $fileSize;
        } else {
            $errors[] = "文件 {$fileName} 上传失败";
        }
    }

    clearSizeCache();

    if (empty($errors)) {
        echo json_encode([
            'success' => true,
            'message' => '文件上传成功',
            'files' => $uploadedFiles,
            'usedSize' => $userUsedSize,
            'maxSize' => MAX_USER_SIZE,
            'globalUsedSize' => $globalUsedSize,
            'globalMaxSize' => MAX_TOTAL_SIZE,
            'publicUsedSize' => $publicUsedSize,
            'publicMaxSize' => MAX_PUBLIC_SIZE
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => '没有文件上传']);
