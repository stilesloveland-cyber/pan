<?php
require_once __DIR__ . '/functions.php';

initSystem();

if (isset($_GET['action']) && $_GET['action'] === 'ping') {
    echo 'pong';
    exit;
}

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

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '请输入密码']);
        exit;
    }

    $user = findUserByPassword($password);
    if ($user) {
        echo json_encode(['success' => true, 'message' => '登录成功', 'role' => $user['data']['role']]);
    } else {
        echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '请输入当前密码和新密码']);
        exit;
    }

    if (changeUserPassword($currentPassword, $newPassword)) {
        echo json_encode(['success' => true, 'message' => '密码修改成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '当前密码错误']);
    }
    exit;
}

$password = isset($_POST['password']) ? $_POST['password'] : (isset($_GET['password']) ? $_GET['password'] : '');
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => '请输入密码']);
    exit;
}

$user = findUserByPassword($password);
if (!$user) {
    echo json_encode(['success' => false, 'message' => '未注册的账户，请先注册']);
    exit;
}

$isAdminUser = isAdmin($user);
$userDir = getUserDir($password);

if (!file_exists($userDir) && !$isAdminUser) {
    mkdir($userDir, 0755, true);
}

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $files = isset($_POST['files']) ? $_POST['files'] : (isset($_POST['file']) ? [$_POST['file']] : []);
    $userDirParam = isset($_POST['userDir']) ? $_POST['userDir'] : '';

    if (empty($files)) {
        echo json_encode(['success' => false, 'message' => '请选择要删除的文件']);
        exit;
    }

    $deletedCount = 0;

    foreach ($files as $file) {
        if ($isAdminUser && !empty($userDirParam)) {
            $filePath = $baseUploadDir . $userDirParam . '/' . basename($file);
        } else {
            $filePath = $userDir . basename($file);
        }

        if (file_exists($filePath) && is_file($filePath)) {
            if ($isAdminUser || strpos(realpath($filePath), realpath($userDir)) === 0) {
                if (unlink($filePath)) {
                    $deletedCount++;
                }
            }
        }
    }

    if ($deletedCount > 0) {
        $usedSize = calculateDirectorySize($userDir);
        echo json_encode(['success' => true, 'message' => '文件删除成功', 'usedSize' => $usedSize, 'maxSize' => $maxTotalSize]);
    } else {
        echo json_encode(['success' => false, 'message' => '文件删除失败']);
    }
    exit;
}

if (isset($_FILES['files'])) {
    ini_set('upload_max_filesize', '10G');
    ini_set('post_max_size', '10G');
    ini_set('max_execution_time', 300);
    ini_set('max_input_time', 300);
    ini_set('memory_limit', '512M');

    $files = $_FILES['files'];
    $uploadedFiles = [];
    $errors = [];
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === 'true';

    $globalUsedSize = calculateGlobalSize($baseUploadDir);
    $userUsedSize = calculateDirectorySize($userDir);
    $publicUsedSize = calculateDirectorySize($publicDir);

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

        if ($globalUsedSize + $fileSize > $maxTotalSize) {
            $errors[] = "文件 {$fileName} 上传失败：全局空间不足";
            continue;
        }

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

        $uniqueName = uniqid() . '_' . $fileName;
        $destination = $isPublic ? $publicDir . $uniqueName : $userDir . $uniqueName;

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
    exit;
}

echo json_encode(['success' => false, 'message' => '没有文件上传']);
