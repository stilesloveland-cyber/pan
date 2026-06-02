<?php
require_once __DIR__ . '/functions.php';

initSystem();

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user || !isset($user['data']['role']) || $user['data']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权访问']);
    exit;
}

function formatBytes($bytes) {
    if ($bytes <= 0) return '0 B';
    $k = 1024;
    $s = ['B','KB','MB','GB','TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $s[$i];
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action === 'get_settings') {
    $settings = getSettings();
    $disk = getServerDiskInfo();
    $maxAllowed = floor($disk['total'] * 0.5);
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'disk' => $disk,
        'max_allowed_total' => $maxAllowed,
        'php_version' => phpversion(),
        'user_count' => count(getUsers()),
    ]);
    exit;
}

if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => '无效数据']);
        exit;
    }

    $disk = getServerDiskInfo();
    $maxAllowed = floor($disk['total'] * 0.5);

    $settings = getSettings();

    $intFields = [
        'max_total_size', 'max_user_size', 'max_public_size',
        'max_file_size', 'max_files_per_upload', 'chunk_size',
        'session_lifetime', 'cache_ttl', 'thumb_width', 'thumb_height',
        'max_login_attempts', 'login_ban_minutes', 'recycle_bin_days',
        'max_preview_size', 'max_share_expiry_days', 'per_page_count',
        'max_concurrent_downloads'
    ];
    foreach ($intFields as $f) {
        if (isset($data[$f])) $settings[$f] = max(0, intval($data[$f]));
    }

    $boolFields = [
        'allow_registration', 'auto_rename', 'enable_share',
        'maintenance_mode', 'enable_public_area'
    ];
    foreach ($boolFields as $f) {
        if (isset($data[$f])) $settings[$f] = (bool)$data[$f];
    }

    $stringFields = [
        'site_name', 'default_sort', 'default_sort_order'
    ];
    foreach ($stringFields as $f) {
        if (isset($data[$f])) $settings[$f] = trim(strval($data[$f]));
    }

    if ($settings['max_total_size'] > $maxAllowed) {
        $settings['max_total_size'] = $maxAllowed;
    }
    if ($settings['max_user_size'] > $settings['max_total_size']) {
        $settings['max_user_size'] = $settings['max_total_size'];
    }
    if ($settings['max_public_size'] > $settings['max_total_size']) {
        $settings['max_public_size'] = $settings['max_total_size'];
    }

    if (isset($data['blocked_extensions']) && is_array($data['blocked_extensions'])) {
        $settings['blocked_extensions'] = array_values(array_filter(array_map('trim', $data['blocked_extensions'])));
    }

    saveSettings($settings);
    clearSizeCache();

    echo json_encode(['success' => true, 'message' => '设置已保存', 'settings' => $settings]);
    exit;
}

if ($action === 'get_users') {
    $users = getUsers();
    $result = [];
    foreach ($users as $id => $u) {
        $result[] = [
            'id' => $id,
            'role' => isset($u['role']) ? $u['role'] : 'user',
            'registered' => isset($u['registered']) ? $u['registered'] : 0,
        ];
    }
    echo json_encode(['success' => true, 'users' => $result]);
    exit;
}

if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => '缺少用户ID']);
        exit;
    }
    if ($userId === 'admin') {
        echo json_encode(['success' => false, 'message' => '不能删除管理员']);
        exit;
    }
    $users = getUsers();
    if (!isset($users[$userId])) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    $userDir = BASE_UPLOAD_DIR . $userId . '/';
    if (is_dir($userDir)) {
        rrmdir($userDir);
    }
    unset($users[$userId]);
    saveUsers($users);
    clearSizeCache();
    echo json_encode(['success' => true, 'message' => '用户已删除']);
    exit;
}

if ($action === 'change_user_role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
    $newRole = isset($_POST['role']) ? $_POST['role'] : '';
    if (empty($userId) || !in_array($newRole, ['admin', 'user'])) {
        echo json_encode(['success' => false, 'message' => '参数无效']);
        exit;
    }
    $users = getUsers();
    if (!isset($users[$userId])) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    $users[$userId]['role'] = $newRole;
    saveUsers($users);
    echo json_encode(['success' => true, 'message' => '角色已更新']);
    exit;
}

if ($action === 'reset_user_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    if (empty($userId) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        exit;
    }
    if (strlen($newPassword) < 4) {
        echo json_encode(['success' => false, 'message' => '密码长度不能少于4位']);
        exit;
    }
    $users = getUsers();
    if (!isset($users[$userId])) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    $newUserId = md5($newPassword);
    if (isset($users[$newUserId]) && $newUserId !== $userId) {
        echo json_encode(['success' => false, 'message' => '新密码已被其他用户使用']);
        exit;
    }
    $oldDir = BASE_UPLOAD_DIR . $userId . '/';
    $newDir = BASE_UPLOAD_DIR . $newUserId . '/';
    if ($userId !== $newUserId) {
        if (is_dir($oldDir)) {
            rename($oldDir, $newDir);
        }
        $users[$newUserId] = $users[$userId];
        $users[$newUserId]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        unset($users[$userId]);
    } else {
        $users[$userId]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    saveUsers($users);
    clearSizeCache();
    echo json_encode(['success' => true, 'message' => '密码已重置']);
    exit;
}

if ($action === 'system_info') {
    $disk = getServerDiskInfo();
    echo json_encode([
        'success' => true,
        'php_version' => phpversion(),
        'disk' => $disk,
        'user_count' => count(getUsers()),
        'global_used' => calculateGlobalSize(),
        'upload_dir' => BASE_UPLOAD_DIR,
    ]);
    exit;
}

if ($action === 'security_check') {
    $risks = [];
    $users = getUsers();
    $settings = getSettings();

    $adminUser = isset($users['admin']) ? $users['admin'] : null;
    $defaultPwd = false;
    if ($adminUser) {
        $defaultPwd = password_verify('admin', $adminUser['password_hash']);
    }
    $risks[] = [
        'id' => 'default_admin_password',
        'label' => '默认管理员密码',
        'severity' => 'high',
        'status' => $defaultPwd ? 'warning' : 'pass',
        'description' => $defaultPwd ? '管理员仍在使用默认密码 admin' : '管理员密码已修改',
        'current' => $defaultPwd ? '默认密码 admin' : '已修改',
        'safe_range' => '非默认强密码',
        'fix_suggestion' => '请立即修改管理员默认密码'
    ];

    $cookieSecure = ini_get('session.cookie_secure');
    $risks[] = [
        'id' => 'cookie_secure',
        'label' => 'Cookie Secure 标志',
        'severity' => 'medium',
        'status' => $cookieSecure ? 'pass' : 'warning',
        'description' => $cookieSecure ? 'Cookie Secure 已启用' : 'Cookie Secure 未启用，会话可能通过 HTTP 传输',
        'current' => $cookieSecure ? '已启用' : '未启用',
        'safe_range' => '生产环境必须启用 HTTPS',
        'fix_suggestion' => '在 functions.php 中将 session cookie secure 设为 true，并确保站点使用 HTTPS'
    ];

    $fixUsersPath = __DIR__ . '/fix_users.php';
    $fixUsersExposed = file_exists($fixUsersPath);
    $risks[] = [
        'id' => 'fix_users_exposed',
        'label' => 'fix_users.php 可公开访问',
        'severity' => 'high',
        'status' => $fixUsersExposed ? 'warning' : 'pass',
        'description' => $fixUsersExposed ? 'fix_users.php 文件存在且可能无认证保护' : 'fix_users.php 不存在或已移除',
        'current' => $fixUsersExposed ? '文件存在' : '不存在',
        'safe_range' => '不可公开访问',
        'fix_suggestion' => '删除 fix_users.php 或在文件顶部添加管理员认证检查'
    ];

    $risks[] = [
        'id' => 'chunk_upload_mime',
        'label' => '分片上传 MIME 检查',
        'severity' => 'high',
        'status' => 'warning',
        'description' => '分片上传流程仅检查扩展名，合并后未执行 MIME 类型验证',
        'current' => '仅扩展名检查',
        'safe_range' => '合并后应执行 isSafeFile() 检查',
        'fix_suggestion' => '在 merge_chunks 完成后调用 isSafeFile() 验证文件 MIME 类型'
    ];

    $disk = getServerDiskInfo();
    $diskFree = $disk['free'];
    $maxFileOverDisk = ($settings['max_file_size'] > $diskFree * 0.8);
    $risks[] = [
        'id' => 'max_file_size_vs_disk',
        'label' => '单文件大小上限合理性',
        'severity' => 'low',
        'status' => $maxFileOverDisk ? 'warning' : 'pass',
        'description' => $maxFileOverDisk ? '单文件大小上限超过磁盘可用空间的 80%' : '单文件大小上限在合理范围内',
        'current' => formatBytes($settings['max_file_size']) . ' / 可用 ' . formatBytes($diskFree),
        'safe_range' => '不超过磁盘可用空间的 80%',
        'fix_suggestion' => '降低单文件大小上限或扩容磁盘'
    ];

    $sessionTooLong = $settings['session_lifetime'] > 604800;
    $risks[] = [
        'id' => 'session_lifetime',
        'label' => '会话有效期',
        'severity' => 'medium',
        'status' => $sessionTooLong ? 'warning' : 'pass',
        'description' => $sessionTooLong ? '会话有效期超过 7 天，存在安全风险' : '会话有效期在合理范围内',
        'current' => round($settings['session_lifetime'] / 86400, 1) . ' 天',
        'safe_range' => '1-7 天',
        'fix_suggestion' => '缩短会话有效期至合理范围'
    ];

    $risks[] = [
        'id' => 'admin_page_exposed',
        'label' => '管理面板前端权限校验',
        'severity' => 'low',
        'status' => 'pass',
        'description' => '管理面板已添加前端权限校验',
        'current' => '已校验',
        'safe_range' => '非管理员应重定向',
        'fix_suggestion' => '已修复'
    ];

    $risks[] = [
        'id' => 'upload_no_ratelimit',
        'label' => '上传接口频率限制',
        'severity' => 'medium',
        'status' => 'warning',
        'description' => '上传接口无请求频率限制，可能被滥用',
        'current' => '无限制',
        'safe_range' => '建议限制每分钟上传请求次数',
        'fix_suggestion' => '添加基于 IP 或会话的上传频率限制机制'
    ];

    echo json_encode(['success' => true, 'risks' => $risks]);
    exit;
}

echo json_encode(['success' => false, 'message' => '未知操作']);
