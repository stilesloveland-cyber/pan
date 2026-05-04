<?php
$baseUploadDir = '/var/www/uploads/';
$usersFile = $baseUploadDir . 'users.json';
$shareDir = $baseUploadDir . 'shares/';
$publicDir = $baseUploadDir . 'public/';

$maxTotalSize = 10 * 1024 * 1024 * 1024;
$maxUserSize = 2 * 1024 * 1024 * 1024;
$maxPublicSize = 5 * 1024 * 1024 * 1024;

function initSystem() {
    global $baseUploadDir, $usersFile, $shareDir, $publicDir;

    if (!file_exists($baseUploadDir)) {
        mkdir($baseUploadDir, 0755, true);
    }
    if (!file_exists($publicDir)) {
        mkdir($publicDir, 0755, true);
    }
    if (!file_exists($shareDir)) {
        mkdir($shareDir, 0755, true);
    }

    if (!file_exists($usersFile)) {
        $users = [
            'admin' => [
                'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
                'registered' => time(),
                'role' => 'admin'
            ]
        ];
        file_put_contents($usersFile, json_encode($users));
        $adminDir = getUserDir('admin');
        if (!file_exists($adminDir)) {
            mkdir($adminDir, 0755, true);
        }
    }
}

function getUsers() {
    global $usersFile;
    return json_decode(file_get_contents($usersFile), true);
}

function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users));
}

function findUserByPassword($password) {
    $users = getUsers();
    foreach ($users as $userId => $userData) {
        if (password_verify($password, $userData['password_hash'])) {
            return ['id' => $userId, 'data' => $userData];
        }
    }
    return null;
}

function isAdmin($user) {
    return $user && isset($user['data']['role']) && $user['data']['role'] === 'admin';
}

function registerUser($password) {
    $users = getUsers();

    foreach ($users as $userData) {
        if (password_verify($password, $userData['password_hash'])) {
            return false;
        }
    }

    $users[] = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'registered' => time(),
        'role' => 'user'
    ];

    saveUsers($users);

    $userDir = getUserDir($password);
    if (!file_exists($userDir)) {
        mkdir($userDir, 0755, true);
    }

    return true;
}

function changeUserPassword($oldPassword, $newPassword) {
    $users = getUsers();
    foreach ($users as $userId => $userData) {
        if (password_verify($oldPassword, $userData['password_hash'])) {
            $users[$userId]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            saveUsers($users);

            $oldDir = getUserDir($oldPassword);
            $newDir = getUserDir($newPassword);
            if (file_exists($oldDir)) {
                rename($oldDir, $newDir);
            }

            return true;
        }
    }
    return false;
}

function getUserDir($password) {
    return $GLOBALS['baseUploadDir'] . md5($password) . '/';
}

function calculateDirectorySize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
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

function calculateGlobalSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . $file;
            if (is_file($path)) {
                $size += filesize($path);
            } elseif (is_dir($path) && $file != 'shares') {
                $size += calculateDirectorySize($path);
            }
        }
    }
    return $size;
}

function getUploadErrorMsg($errorCode) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => '文件超过了php.ini中的上传大小限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过了表单中的上传大小限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_FILE => '没有文件上传',
        UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
    ];
    return isset($messages[$errorCode]) ? $messages[$errorCode] : '未知错误';
}

function cleanExpiredShares() {
    global $shareDir;
    if (!is_dir($shareDir)) return;
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
