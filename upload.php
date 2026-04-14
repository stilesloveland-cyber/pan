<?php
// 配置
$baseUploadDir = '/var/www/uploads/'; // Web根目录外部的存储路径
$maxTotalSize = 10 * 1024 * 1024 * 1024; // 10GB
$maxUserSize = 2 * 1024 * 1024 * 1024; // 单用户最大空间2GB
$maxPublicSize = 5 * 1024 * 1024 * 1024; // 公共空间最大5GB
$allowedExtensions = ['*']; // 允许所有文件类型
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

// 保存用户列表
function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users));
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

// 注册新用户
function registerUser($password) {
    $users = getUsers();
    $passwordHash = md5($password);
    
    // 检查是否已存在
    foreach ($users as $user) {
        if (isset($user['password']) && $user['password'] === $passwordHash) {
            return false;
        }
    }
    
    // 添加新用户
    $users['user_' . uniqid()] = [
        'password' => $passwordHash,
        'registered' => time(),
        'role' => 'user'
    ];
    
    saveUsers($users);
    return true;
}

// 处理ping请求
if (isset($_GET['action']) && $_GET['action'] === 'ping') {
    echo 'pong';
    exit;
}

// 处理分块上传
if (isset($_POST['action']) && $_POST['action'] === 'upload_chunk') {
    $fileId = isset($_POST['fileId']) ? $_POST['fileId'] : '';
    $chunk = isset($_POST['chunk']) ? (int)$_POST['chunk'] : 0;
    $totalChunks = isset($_POST['totalChunks']) ? (int)$_POST['totalChunks'] : 0;
    $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';
    
    if (empty($fileId) || empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => '上传失败']);
        exit;
    }
    
    // 创建临时目录
    $tempDir = $baseUploadDir . 'temp/' . $fileId . '/';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // 保存分块
    $chunkFile = $tempDir . $chunk;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $chunkFile)) {
        echo json_encode(['success' => true, 'message' => '分块上传成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '分块保存失败']);
    }
    exit;
}

// 检查上传进度
if (isset($_POST['action']) && $_POST['action'] === 'check_progress') {
    $fileId = isset($_POST['fileId']) ? $_POST['fileId'] : '';
    
    if (empty($fileId)) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }
    
    // 检查临时目录
    $tempDir = $baseUploadDir . 'temp/' . $fileId . '/';
    if (!file_exists($tempDir)) {
        echo json_encode(['success' => true, 'currentChunk' => 0]);
        exit;
    }
    
    // 计算已上传的分块数
    $chunks = scandir($tempDir);
    $uploadedChunks = [];
    foreach ($chunks as $chunk) {
        if (is_numeric($chunk)) {
            $uploadedChunks[] = (int)$chunk;
        }
    }
    
    if (empty($uploadedChunks)) {
        echo json_encode(['success' => true, 'currentChunk' => 0]);
    } else {
        sort($uploadedChunks);
        $currentChunk = end($uploadedChunks) + 1;
        echo json_encode(['success' => true, 'currentChunk' => $currentChunk]);
    }
    exit;
}

// 合并分块
if (isset($_POST['action']) && $_POST['action'] === 'merge_chunks') {
    $fileId = isset($_POST['fileId']) ? $_POST['fileId'] : '';
    $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';
    
    if (empty($fileId) || empty($filename)) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }
    
    // 检查临时目录
    $tempDir = $baseUploadDir . 'temp/' . $fileId . '/';
    if (!file_exists($tempDir)) {
        echo json_encode(['success' => false, 'message' => '临时文件不存在']);
        exit;
    }
    
    // 确保用户目录存在
    $publicDir = $baseUploadDir . 'public/';
    if (!file_exists($publicDir)) {
        mkdir($publicDir, 0755, true);
    }
    
    $userDir = $baseUploadDir . md5($password) . '/';
    if (!file_exists($userDir)) {
        mkdir($userDir, 0755, true);
    }
    
    // 计算全局总空间使用情况
    $globalUsedSize = calculateGlobalSize($baseUploadDir);
    
    // 计算用户空间使用情况
    $userUsedSize = calculateDirectorySize($userDir);
    
    // 计算公共空间使用情况
    $publicUsedSize = calculateDirectorySize($publicDir);
    
    // 计算文件总大小
    $chunks = scandir($tempDir);
    $totalSize = 0;
    foreach ($chunks as $chunk) {
        if (is_numeric($chunk)) {
            $chunkFile = $tempDir . $chunk;
            $totalSize += filesize($chunkFile);
        }
    }
    
    // 检查空间是否足够
    if ($globalUsedSize + $totalSize > $maxTotalSize) {
        echo json_encode(['success' => false, 'message' => '全局空间不足']);
        exit;
    }
    
    if ($isPublic) {
        if ($publicUsedSize + $totalSize > $maxPublicSize) {
            echo json_encode(['success' => false, 'message' => '公共空间不足']);
            exit;
        }
    } else {
        if ($userUsedSize + $totalSize > $maxUserSize) {
            echo json_encode(['success' => false, 'message' => '个人空间不足']);
            exit;
        }
    }
    
    // 生成唯一文件名
    $uniqueName = uniqid() . '_' . $filename;
    $destination = $isPublic ? $publicDir . $uniqueName : $userDir . $uniqueName;
    
    // 确保目标目录存在
    $targetDir = dirname($destination);
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // 合并分块
    $output = fopen($destination, 'wb');
    if (!$output) {
        echo json_encode(['success' => false, 'message' => '文件创建失败']);
        exit;
    }
    
    $chunks = scandir($tempDir);
    $chunkNumbers = [];
    foreach ($chunks as $chunk) {
        if (is_numeric($chunk)) {
            $chunkNumbers[] = (int)$chunk;
        }
    }
    
    sort($chunkNumbers);
    
    foreach ($chunkNumbers as $chunkNumber) {
        $chunkFile = $tempDir . $chunkNumber;
        $input = fopen($chunkFile, 'rb');
        if ($input) {
            stream_copy_to_stream($input, $output);
            fclose($input);
        }
    }
    
    fclose($output);
    
    // 清理临时文件
    array_map('unlink', glob($tempDir . '*'));
    rmdir($tempDir);
    
    // 更新空间使用情况
    if ($isPublic) {
        $publicUsedSize += $totalSize;
    } else {
        $userUsedSize += $totalSize;
    }
    $globalUsedSize += $totalSize;
    
    echo json_encode([
        'success' => true, 
        'message' => '文件上传成功', 
        'usedSize' => $userUsedSize, 
        'maxSize' => $maxUserSize,
        'globalUsedSize' => $globalUsedSize,
        'globalMaxSize' => $maxTotalSize,
        'publicUsedSize' => $publicUsedSize,
        'publicMaxSize' => $maxPublicSize
    ]);
    exit;
}

// 处理用户注册
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '请输入密码']);
        exit;
    }
    
    if (registerUser($password)) {
        echo json_encode(['success' => true, 'message' => '注册成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '该密码已被注册']);
    }
    exit;
}

// 处理用户登录验证
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '请输入密码']);
        exit;
    }
    
    if (isUserRegistered($password)) {
        echo json_encode(['success' => true, 'message' => '登录成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
    }
    exit;
}

// 处理密码修改请求
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '请输入当前密码和新密码']);
        exit;
    }
    
    // 验证当前密码
    if (!isUserRegistered($currentPassword)) {
        echo json_encode(['success' => false, 'message' => '当前密码错误']);
        exit;
    }
    
    // 创建新密码目录
    $newDir = $baseUploadDir . md5($newPassword) . '/';
    if (file_exists($newDir)) {
        echo json_encode(['success' => false, 'message' => '新密码已被使用']);
        exit;
    }
    
    // 复制文件到新目录
    $currentDir = $baseUploadDir . md5($currentPassword) . '/';
    if (rename($currentDir, $newDir)) {
        // 更新用户密码
        $users = getUsers();
        $currentHash = md5($currentPassword);
        $newHash = md5($newPassword);
        
        foreach ($users as $key => $user) {
            if (isset($user['password']) && $user['password'] === $currentHash) {
                $users[$key]['password'] = $newHash;
                break;
            }
        }
        saveUsers($users);
        
        echo json_encode(['success' => true, 'message' => '密码修改成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '密码修改失败']);
    }
    exit;
}

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

// 获取用户密码
$password = isset($_POST['password']) ? $_POST['password'] : (isset($_GET['password']) ? $_GET['password'] : '');
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

// 处理删除请求
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $files = isset($_POST['files']) ? $_POST['files'] : (isset($_POST['file']) ? [$_POST['file']] : []);
    $userDirParam = isset($_POST['userDir']) ? $_POST['userDir'] : '';
    
    if (empty($files)) {
        echo json_encode(['success' => false, 'message' => '请选择要删除的文件']);
        exit;
    }
    
    $deletedCount = 0;
    
    foreach ($files as $file) {
        // 确定文件路径
        if ($isAdmin && !empty($userDirParam)) {
            // 管理员可以删除所有用户的文件
            $filePath = $baseUploadDir . $userDirParam . '/' . basename($file);
        } else {
            // 普通用户只能删除自己的文件
            $filePath = $userDir . basename($file);
        }
        
        // 安全检查：确保文件存在
        if (file_exists($filePath) && is_file($filePath)) {
            // 安全检查：确保文件路径在允许的范围内
            if ($isAdmin || strpos(realpath($filePath), realpath($userDir)) === 0) {
                if (unlink($filePath)) {
                    $deletedCount++;
                }
            }
        }
    }
    
    if ($deletedCount > 0) {
        // 计算删除后的空间使用情况
        $usedSize = calculateDirectorySize($userDir);
        echo json_encode(['success' => true, 'message' => '文件删除成功', 'usedSize' => $usedSize, 'maxSize' => $maxTotalSize]);
    } else {
        echo json_encode(['success' => false, 'message' => '文件删除失败']);
    }
    exit;
}

// 处理重命名请求
if (isset($_POST['action']) && $_POST['action'] === 'rename') {
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    $newName = isset($_POST['new_name']) ? $_POST['new_name'] : '';
    $userDirParam = isset($_POST['userDir']) ? $_POST['userDir'] : '';
    
    if (empty($file) || empty($newName)) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }
    
    // 获取原始文件的扩展名
    $originalExtension = pathinfo($file, PATHINFO_EXTENSION);
    $newExtension = pathinfo($newName, PATHINFO_EXTENSION);
    
    // 非管理员用户不能修改文件后缀
    if (!$isAdmin && $originalExtension !== $newExtension) {
        echo json_encode(['success' => false, 'message' => '普通用户不能修改文件后缀']);
        exit;
    }
    
    // 确定文件路径
    if ($isAdmin && !empty($userDirParam)) {
        // 管理员可以重命名所有用户的文件
        $filePath = $baseUploadDir . $userDirParam . '/' . basename($file);
        $newFilePath = $baseUploadDir . $userDirParam . '/' . uniqid() . '_' . $newName;
    } else {
        // 普通用户只能重命名自己的文件
        $filePath = $userDir . basename($file);
        $newFilePath = $userDir . uniqid() . '_' . $newName;
    }
    
    // 安全检查：确保文件存在
    if (file_exists($filePath) && is_file($filePath)) {
        // 安全检查：确保文件路径在允许的范围内
        if ($isAdmin || strpos(realpath($filePath), realpath($userDir)) === 0) {
            if (rename($filePath, $newFilePath)) {
                echo json_encode(['success' => true, 'message' => '文件重命名成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '重命名失败']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '无权限操作']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
    }
    exit;
}

// 处理创建文件夹请求
if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folderName = isset($_POST['folder_name']) ? $_POST['folder_name'] : '';
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';
    
    if (empty($folderName)) {
        echo json_encode(['success' => false, 'message' => '文件夹名称不能为空']);
        exit;
    }
    
    // 确定文件夹路径
    $targetDir = $isPublic ? $publicDir : $userDir;
    $folderPath = $targetDir . $folderName . '/';
    
    // 安全检查：确保文件夹不存在
    if (!file_exists($folderPath)) {
        if (mkdir($folderPath, 0755, true)) {
            echo json_encode(['success' => true, 'message' => '文件夹创建成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '创建失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '文件夹已存在']);
    }
    exit;
}

// 处理移动文件请求
if (isset($_POST['action']) && $_POST['action'] === 'move_file') {
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    $targetFolder = isset($_POST['target_folder']) ? $_POST['target_folder'] : '';
    $userDirParam = isset($_POST['userDir']) ? $_POST['userDir'] : '';
    
    if (empty($file) || empty($targetFolder)) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }
    
    // 确定文件路径
    if ($isAdmin && !empty($userDirParam)) {
        // 管理员可以移动所有用户的文件
        $filePath = $baseUploadDir . $userDirParam . '/' . basename($file);
        $targetPath = $baseUploadDir . $userDirParam . '/' . $targetFolder . '/' . basename($file);
    } else {
        // 普通用户只能移动自己的文件
        $filePath = $userDir . basename($file);
        $targetPath = $userDir . $targetFolder . '/' . basename($file);
    }
    
    // 安全检查：确保文件存在
    if (file_exists($filePath) && is_file($filePath)) {
        // 安全检查：确保文件路径在允许的范围内
        if ($isAdmin || strpos(realpath($filePath), realpath($userDir)) === 0) {
            // 确保目标文件夹存在
            $targetDir = dirname($targetPath);
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            if (rename($filePath, $targetPath)) {
                echo json_encode(['success' => true, 'message' => '文件移动成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '移动失败']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '无权限操作']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
    }
    exit;
}

// 处理批量移动文件请求
if (isset($_POST['action']) && $_POST['action'] === 'batch_move') {
    $files = isset($_POST['files']) ? $_POST['files'] : [];
    $targetFolder = isset($_POST['target_folder']) ? $_POST['target_folder'] : '';
    
    if (empty($files) || empty($targetFolder)) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }
    
    $movedCount = 0;
    
    foreach ($files as $file) {
        // 确定文件路径
        $filePath = $userDir . basename($file);
        $targetPath = $userDir . $targetFolder . '/' . basename($file);
        
        // 安全检查：确保文件存在
        if (file_exists($filePath) && is_file($filePath)) {
            // 安全检查：确保文件路径在允许的范围内
            if (strpos(realpath($filePath), realpath($userDir)) === 0) {
                // 确保目标文件夹存在
                $targetDir = dirname($targetPath);
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                if (rename($filePath, $targetPath)) {
                    $movedCount++;
                }
            }
        }
    }
    
    if ($movedCount > 0) {
        echo json_encode(['success' => true, 'message' => '文件移动成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '移动失败']);
    }
    exit;
}

// 处理批量重命名文件请求
if (isset($_POST['action']) && $_POST['action'] === 'batch_rename') {
    $files = isset($_POST['files']) ? $_POST['files'] : [];
    $prefix = isset($_POST['prefix']) ? $_POST['prefix'] : '';
    
    if (empty($files) || empty($prefix)) {
        echo json_encode(['success' => false, 'message' => '参数错误']);
        exit;
    }
    
    $renamedCount = 0;
    $counter = 1;
    
    foreach ($files as $file) {
        // 确定文件路径
        $filePath = $userDir . basename($file);
        
        // 安全检查：确保文件存在
        if (file_exists($filePath) && is_file($filePath)) {
            // 安全检查：确保文件路径在允许的范围内
            if (strpos(realpath($filePath), realpath($userDir)) === 0) {
                // 获取文件扩展名
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $newName = $prefix . '_' . $counter . ($extension ? '.' . $extension : '');
                $newFilePath = $userDir . uniqid() . '_' . $newName;
                
                if (rename($filePath, $newFilePath)) {
                    $renamedCount++;
                    $counter++;
                }
            }
        }
    }
    
    if ($renamedCount > 0) {
        echo json_encode(['success' => true, 'message' => '文件重命名成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '重命名失败']);
    }
    exit;
}

// 处理上传请求
if (isset($_FILES['files'])) {
    // 增加上传限制
    ini_set('upload_max_filesize', '10G');
    ini_set('post_max_size', '10G');
    ini_set('max_execution_time', 300);
    ini_set('max_input_time', 300);
    ini_set('memory_limit', '512M');
    
    $files = $_FILES['files'];
    $uploadedFiles = [];
    $errors = [];
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';
    
    // 计算全局总空间使用情况
    $globalUsedSize = calculateGlobalSize($baseUploadDir);
    
    // 计算用户空间使用情况
    $userUsedSize = calculateDirectorySize($userDir);
    
    // 计算公共空间使用情况
    $publicUsedSize = calculateDirectorySize($publicDir);
    
    // 处理多个文件
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $fileName = $files['name'][$i];
            $fileSize = $files['size'][$i];
            $fileTmp = $files['tmp_name'][$i];
            $fileError = $files['error'][$i];
            
            if ($fileError === UPLOAD_ERR_OK) {
                // 检查全局总空间是否足够
                if ($globalUsedSize + $fileSize > $maxTotalSize) {
                    $errors[] = "文件 {$fileName} 上传失败：全局空间不足";
                    continue;
                }
                
                // 检查用户空间或公共空间是否足够
                if ($isPublic) {
                    if ($publicUsedSize + $fileSize > $maxPublicSize) {
                        $errors[] = "文件 {$fileName} 上传失败：公共空间不足";
                        continue;
                    }
                } else {
                    if ($userUsedSize + $fileSize > $maxUserSize) {
                        $errors[] = "文件 {$fileName} 上传失败：个人空间不足";
                        continue;
                    }
                }
                
                // 验证文件扩展名（如果有限制）
                if (!empty($allowedExtensions) && $allowedExtensions[0] !== '*') {
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (!in_array($fileExt, $allowedExtensions)) {
                        $errors[] = "文件 {$fileName} 类型不允许";
                        continue;
                    }
                }
                
                // 生成唯一文件名，避免覆盖
                $uniqueName = uniqid() . '_' . $fileName;
                $destination = $isPublic ? $publicDir . $uniqueName : $userDir . $uniqueName;
                
                // 确保目标目录存在
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
                    $errors[] = "文件 {$fileName} 上传失败：" . error_get_last()['message'];
                }
            } else {
                $errors[] = "文件 {$fileName} 上传出错: " . getUploadErrorMsg($fileError);
            }
        }
    } else {
        // 单个文件
        $fileName = $files['name'];
        $fileSize = $files['size'];
        $fileTmp = $files['tmp_name'];
        $fileError = $files['error'];
        
        if ($fileError === UPLOAD_ERR_OK) {
            // 检查全局总空间是否足够
            if ($globalUsedSize + $fileSize > $maxTotalSize) {
                $errors[] = "文件上传失败：全局空间不足";
            } else {
                // 检查用户空间或公共空间是否足够
                if ($isPublic) {
                    if ($publicUsedSize + $fileSize > $maxPublicSize) {
                        $errors[] = "文件上传失败：公共空间不足";
                    } else {
                        // 验证文件扩展名（如果有限制）
                        if (!empty($allowedExtensions) && $allowedExtensions[0] !== '*') {
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, $allowedExtensions)) {
                                $errors[] = "文件类型不允许";
                            } else {
                                // 生成唯一文件名，避免覆盖
                                $uniqueName = uniqid() . '_' . $fileName;
                                $destination = $publicDir . $uniqueName;
                                
                                // 确保目标目录存在
                                $targetDir = dirname($destination);
                                if (!file_exists($targetDir)) {
                                    mkdir($targetDir, 0755, true);
                                }
                                
                                if (move_uploaded_file($fileTmp, $destination)) {
                                    $uploadedFiles[] = $uniqueName;
                                    $publicUsedSize += $fileSize;
                                    $globalUsedSize += $fileSize;
                                } else {
                                    $errors[] = "文件上传失败：" . error_get_last()['message'];
                                }
                            }
                        } else {
                            // 生成唯一文件名，避免覆盖
                            $uniqueName = uniqid() . '_' . $fileName;
                            $destination = $publicDir . $uniqueName;
                            
                            // 确保目标目录存在
                            $targetDir = dirname($destination);
                            if (!file_exists($targetDir)) {
                                mkdir($targetDir, 0755, true);
                            }
                            
                            if (move_uploaded_file($fileTmp, $destination)) {
                                $uploadedFiles[] = $uniqueName;
                                $publicUsedSize += $fileSize;
                                $globalUsedSize += $fileSize;
                            } else {
                                $errors[] = "文件上传失败：" . error_get_last()['message'];
                            }
                        }
                    }
                } else {
                    if ($userUsedSize + $fileSize > $maxUserSize) {
                        $errors[] = "文件上传失败：个人空间不足";
                    } else {
                        // 验证文件扩展名（如果有限制）
                        if (!empty($allowedExtensions) && $allowedExtensions[0] !== '*') {
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, $allowedExtensions)) {
                                $errors[] = "文件类型不允许";
                            } else {
                                // 生成唯一文件名，避免覆盖
                                $uniqueName = uniqid() . '_' . $fileName;
                                $destination = $userDir . $uniqueName;
                                
                                // 确保目标目录存在
                                $targetDir = dirname($destination);
                                if (!file_exists($targetDir)) {
                                    mkdir($targetDir, 0755, true);
                                }
                                
                                if (move_uploaded_file($fileTmp, $destination)) {
                                    $uploadedFiles[] = $uniqueName;
                                    $userUsedSize += $fileSize;
                                    $globalUsedSize += $fileSize;
                                } else {
                                    $errors[] = "文件上传失败：" . error_get_last()['message'];
                                }
                            }
                        } else {
                            // 生成唯一文件名，避免覆盖
                            $uniqueName = uniqid() . '_' . $fileName;
                            $destination = $userDir . $uniqueName;
                            
                            // 确保目标目录存在
                            $targetDir = dirname($destination);
                            if (!file_exists($targetDir)) {
                                mkdir($targetDir, 0755, true);
                            }
                            
                            if (move_uploaded_file($fileTmp, $destination)) {
                                $uploadedFiles[] = $uniqueName;
                                $userUsedSize += $fileSize;
                                $globalUsedSize += $fileSize;
                            } else {
                                $errors[] = "文件上传失败：" . error_get_last()['message'];
                            }
                        }
                    }
                }
            }
        } else {
            $errors[] = "文件上传出错: " . getUploadErrorMsg($fileError);
        }
    }
    
    if (empty($errors)) {
        echo json_encode([
            'success' => true, 
            'message' => '文件上传成功', 
            'files' => $uploadedFiles, 
            'usedSize' => $userUsedSize, 
            'maxSize' => $maxUserSize,
            'globalUsedSize' => $globalUsedSize,
            'globalMaxSize' => $maxTotalSize,
            'publicUsedSize' => $publicUsedSize,
            'publicMaxSize' => $maxPublicSize
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '没有文件上传']);
}

// 获取上传错误信息
function getUploadErrorMsg($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return '文件超过了php.ini中的上传大小限制';
        case UPLOAD_ERR_FORM_SIZE:
            return '文件超过了表单中的上传大小限制';
        case UPLOAD_ERR_PARTIAL:
            return '文件只上传了一部分';
        case UPLOAD_ERR_NO_FILE:
            return '没有文件上传';
        case UPLOAD_ERR_NO_TMP_DIR:
            return '缺少临时文件夹';
        case UPLOAD_ERR_CANT_WRITE:
            return '文件写入失败';
        case UPLOAD_ERR_EXTENSION:
            return '文件上传被扩展阻止';
        default:
            return '未知错误';
    }
}
?>