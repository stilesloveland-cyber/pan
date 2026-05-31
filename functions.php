<?php
/**
 * WPAN 个人网盘系统 - 公共函数库
 * 版本: 2.0 (会话重构版)
 */
require_once __DIR__ . '/config.php';

// ========== 会话管理 ==========
function initSystem() {
    // 启动会话（安全配置）
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // 创建必要目录
    $dirs = [BASE_UPLOAD_DIR, PUBLIC_DIR, SHARE_DIR, CACHE_DIR];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // 初始化默认管理员
    if (!file_exists(USERS_FILE)) {
        $adminDir = BASE_UPLOAD_DIR . md5('admin') . '/';
        $users = [
            'admin' => [
                'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
                'registered' => time(),
                'role' => 'admin'
            ]
        ];
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
        if (!file_exists($adminDir)) {
            mkdir($adminDir, 0755, true);
        }
    }
}

// ========== 用户认证（会话优先，兼容密码参数） ==========

/**
 * 获取当前认证密码.
 * 优先从会话获取, 其次从请求参数获取.
 */
function getAuthPassword() {
    // 会话优先
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['password_md5'])) {
        return $_SESSION['password_md5'];
    }
    return null;
}

/**
 * 检查是否已通过会话登录
 */
function isSessionLoggedIn() {
    return session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['password_md5']);
}

/**
 * 通过密码登录（验证密码，建立会话）
 */
function loginUser($password) {
    $user = findUserByPassword($password);
    if ($user) {
        $_SESSION['password_md5'] = md5($password);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['data']['role'];
        $_SESSION['logged_in'] = true;
        session_regenerate_id(true);
        return $user;
    }
    return null;
}

/**
 * 登出
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * 获取当前用户信息（优先会话，其次密码参数）
 */
function getCurrentUser() {
    // 会话优先
    if (isSessionLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'data' => [
                'role' => $_SESSION['role'],
                'password_md5' => $_SESSION['password_md5']
            ]
        ];
    }
    return null;
}

/**
 * 获取当前用户的目录路径
 */
function getCurrentUserDir() {
    $user = getCurrentUser();
    if ($user) {
        return BASE_UPLOAD_DIR . $user['data']['password_md5'] . '/';
    }
    return null;
}

/**
 * 检查当前用户是否为管理员
 */
function isCurrentUserAdmin() {
    $user = getCurrentUser();
    return $user && isset($user['data']['role']) && $user['data']['role'] === 'admin';
}

// ========== 用户管理 ==========

function getUsers() {
    return json_decode(file_get_contents(USERS_FILE), true);
}

function saveUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
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

function findUserByMd5($md5) {
    $users = getUsers();
    foreach ($users as $userId => $userData) {
        $userDir = BASE_UPLOAD_DIR . $md5 . '/';
        if (is_dir($userDir)) {
            return ['id' => $userId, 'data' => $userData];
        }
    }
    return null;
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

            // 更新会话
            if (isSessionLoggedIn()) {
                $_SESSION['password_md5'] = md5($newPassword);
            }

            return true;
        }
    }
    return false;
}

function getUserDir($password) {
    return BASE_UPLOAD_DIR . md5($password) . '/';
}

// ========== 空间统计（带缓存） ==========

function getCachedSize($cacheKey) {
    $cacheFile = CACHE_DIR . md5($cacheKey) . '.cache';
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && time() - $data['time'] < 30) { // 30秒缓存
            return $data['size'];
        }
    }
    return null;
}

function setCachedSize($cacheKey, $size) {
    $cacheFile = CACHE_DIR . md5($cacheKey) . '.cache';
    file_put_contents($cacheFile, json_encode(['size' => $size, 'time' => time()]));
}

function calculateDirectorySize($dir) {
    $cacheKey = 'dirsize_' . $dir;
    $cached = getCachedSize($cacheKey);
    if ($cached !== null) return $cached;

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
    setCachedSize($cacheKey, $size);
    return $size;
}

function calculateGlobalSize() {
    $cacheKey = 'globalsize';
    $cached = getCachedSize($cacheKey);
    if ($cached !== null) return $cached;

    $size = 0;
    $dir = BASE_UPLOAD_DIR;
    if (!is_dir($dir)) return 0;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . $file;
            if (is_file($path)) {
                $size += filesize($path);
            } elseif (is_dir($path) && $file != 'shares' && $file != 'cache') {
                $size += calculateDirectorySize($path);
            }
        }
    }
    setCachedSize($cacheKey, $size);
    return $size;
}

/**
 * 清除空间大小缓存（上传/删除后调用）
 */
function clearSizeCache() {
    $cacheDir = CACHE_DIR;
    if (!is_dir($cacheDir)) return;
    $files = scandir($cacheDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
            unlink($cacheDir . $file);
        }
    }
}

// ========== 文件操作辅助 ==========

function getUploadErrorMsg($errorCode) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => '文件超过了 php.ini 中的上传大小限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过了表单中的上传大小限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_FILE => '没有文件上传',
        UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
    ];
    return isset($messages[$errorCode]) ? $messages[$errorCode] : '未知错误';
}

/**
 * 安全地获取文件列表（防止路径穿越）
 */
function getFileList($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    $handle = opendir($dir);
    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..' && !is_dir($dir . $entry)) {
                $originalName = preg_replace('/^[0-9a-fA-F_]+_/', '', $entry);
                $files[] = [
                    'name' => $originalName,
                    'size' => filesize($dir . $entry),
                    'date' => filemtime($dir . $entry),
                    'filename' => $entry
                ];
            }
        }
        closedir($handle);
    }
    return $files;
}

/**
 * 安全地获取文件夹列表
 */
function getFolderList($dir) {
    $folders = [];
    if (!is_dir($dir)) return $folders;
    $handle = opendir($dir);
    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..' && is_dir($dir . $entry)) {
                $folders[] = [
                    'name' => $entry,
                    'date' => filemtime($dir . $entry)
                ];
            }
        }
        closedir($handle);
    }
    usort($folders, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $folders;
}

/**
 * 安全地拼接路径（防止路径穿越）
 */
function safeJoinPath($base, $subPath) {
    $subPath = str_replace('\\', '/', $subPath);
    // 移除开头的 /
    $subPath = ltrim($subPath, '/');
    // 移除以 ./ 和 ../ 开头的遍历
    $parts = explode('/', $subPath);
    $safeParts = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') continue;
        $safeParts[] = $part;
    }
    if (empty($safeParts)) return rtrim($base, '/') . '/';
    return rtrim($base, '/') . '/' . implode('/', $safeParts) . '/';
}

/**
 * 创建文件夹
 */
function createFolder($baseDir, $folderName) {
    $folderName = basename(trim($folderName));
    if (empty($folderName)) return false;
    $path = rtrim($baseDir, '/') . '/' . $folderName;
    if (file_exists($path)) return false;
    return mkdir($path, 0755, true);
}

/**
 * 重命名文件或文件夹
 */
function renameFileOrFolder($baseDir, $oldName, $newName) {
    $oldName = basename(trim($oldName));
    $newName = basename(trim($newName));
    if (empty($oldName) || empty($newName)) return false;
    $oldPath = rtrim($baseDir, '/') . '/' . $oldName;
    $newPath = rtrim($baseDir, '/') . '/' . $newName;
    if (!file_exists($oldPath) || file_exists($newPath)) return false;
    return rename($oldPath, $newPath);
}

/**
 * 获取当前目录下的所有内容（文件夹+文件）
 */
function getDirectoryContents($dir) {
    $items = [];
    if (!is_dir($dir)) return $items;

    // 获取文件夹
    $folders = getFolderList($dir);
    foreach ($folders as $f) {
        $f['isDir'] = true;
        $f['size'] = 0;
        $items[] = $f;
    }

    // 获取文件
    $files = getFileList($dir);
    foreach ($files as $f) {
        $f['isDir'] = false;
        $items[] = $f;
    }

    return $items;
}

/**
 * 递归获取所有子文件夹（用于移动文件时选择目标）
 */
function getAllFoldersRecursive($baseDir, $currentDir, $relativePath) {
    $result = [];
    $handle = opendir($currentDir);
    if ($handle) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..' && is_dir($currentDir . $entry)) {
                $fullPath = $currentDir . $entry . '/';
                $relPath = $relativePath ? $relativePath . '/' . $entry : $entry;
                $result[] = [
                    'name' => $entry,
                    'path' => $relPath
                ];
                $subDirs = getAllFoldersRecursive($baseDir, $fullPath, $relPath);
                $result = array_merge($result, $subDirs);
            }
        }
        closedir($handle);
    }
    return $result;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return false;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    return rmdir($dir);
}

function cleanExpiredShares() {
    if (!is_dir(SHARE_DIR)) return;
    $files = scandir(SHARE_DIR);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $shareData = json_decode(file_get_contents(SHARE_DIR . $file), true);
            if (time() > $shareData['expiry']) {
                unlink(SHARE_DIR . $file);
            }
        }
    }
}
