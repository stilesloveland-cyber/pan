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
        'session_lifetime', 'cache_ttl', 'thumb_width', 'thumb_height'
    ];
    foreach ($intFields as $f) {
        if (isset($data[$f])) $settings[$f] = max(0, intval($data[$f]));
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
    unset($users[$userId]);
    saveUsers($users);
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
    $users = getUsers();
    if (!isset($users[$userId])) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    $users[$userId]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    saveUsers($users);
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

echo json_encode(['success' => false, 'message' => '未知操作']);
