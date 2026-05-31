<?php
require_once __DIR__ . '/functions.php';
initSystem();

// 直接调用 initSystem() 应该会自动创建正确的 users.json
// 如果没有，我们手动创建
if (!file_exists(USERS_FILE)) {
    $adminDir = BASE_UPLOAD_DIR . md5('admin') . '/';
    $users = [
        md5('admin') => [
            'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
            'registered' => time(),
            'role' => 'admin'
        ]
    ];
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    if (!file_exists($adminDir)) {
        mkdir($adminDir, 0755, true);
    }
    echo "Created admin user successfully!\n";
} else {
    echo "users.json already exists!\n";
}
