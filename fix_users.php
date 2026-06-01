<?php
require_once __DIR__ . '/functions.php';
initSystem();

$user = getCurrentUser();
if (!$user || !isset($user['data']['role']) || $user['data']['role'] !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (!file_exists(USERS_FILE)) {
    $adminDir = BASE_UPLOAD_DIR . md5('admin') . '/';
    $users = [
        md5('admin') => [
            'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
            'registered' => time(),
            'role' => 'admin'
        ]
    ];
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
    if (!file_exists($adminDir)) {
        mkdir($adminDir, 0755, true);
    }
    echo "Created admin user successfully!\n";
} else {
    echo "users.json already exists!\n";
}
