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