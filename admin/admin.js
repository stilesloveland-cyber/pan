// 管理后台脚本

// 全局变量
let currentPassword = '';
let isAdmin = false;

// 初始化
function init() {
    // 加载用户信息
    loadUserInfo();
    
    // 初始化标签页
    initTabs();
    
    // 加载用户列表
    loadUsers();
    
    // 加载空间信息
    loadSpaceInfo();
    
    // 加载操作日志
    loadLogs();
    
    // 加载公告
    loadAnnouncements();
}

// 加载用户信息
function loadUserInfo() {
    const password = localStorage.getItem('currentPassword');
    if (!password) {
        window.location.href = '../index.html';
        return;
    }
    
    currentPassword = password;
    
    // 验证管理员权限
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'checkAdmin',
            password: currentPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.isAdmin) {
            window.location.href = '../index.html';
        } else {
            isAdmin = true;
        }
    })
    .catch(error => {
        console.error('验证管理员权限失败:', error);
        window.location.href = '../index.html';
    });
}

// 初始化标签页
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // 移除所有活动状态
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'bg-primary', 'text-white');
                btn.classList.add('hover:bg-gray-100', 'dark:hover:bg-gray-700');
            });
            
            // 添加活动状态
            button.classList.add('active', 'bg-primary', 'text-white');
            button.classList.remove('hover:bg-gray-100', 'dark:hover:bg-gray-700');
            
            // 显示对应内容
            const tabId = button.dataset.tab;
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => {
                pane.classList.add('hidden');
                pane.classList.remove('active');
            });
            
            document.getElementById(`${tabId}-tab`).classList.remove('hidden');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
}

// 加载用户列表
function loadUsers() {
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'getUsers',
            password: currentPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderUsers(data.users);
        }
    })
    .catch(error => {
        console.error('加载用户列表失败:', error);
    });
}

// 渲染用户列表
function renderUsers(users) {
    const tableBody = document.getElementById('users-table-body');
    tableBody.innerHTML = '';
    
    users.forEach(user => {
        const row = document.createElement('tr');
        row.className = 'border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">${user.id}</td>
            <td class="px-4 py-3 text-sm">
                <span class="px-2 py-1 rounded-full text-xs ${user.role === 'admin' ? 'bg-primary/10 text-primary' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'}">
                    ${user.role === 'admin' ? '管理员' : '普通用户'}
                </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${user.spaceUsed} / ${user.spaceQuota}</td>
            <td class="px-4 py-3 text-sm">
                <button class="text-primary hover:underline mr-3" onclick="editUser('${user.id}')">编辑</button>
                <button class="text-red-500 hover:underline" onclick="deleteUser('${user.id}')">删除</button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

// 加载空间信息
function loadSpaceInfo() {
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'getSpaceInfo',
            password: currentPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('global-space').textContent = `${data.global.used} / ${data.global.total}`;
            document.getElementById('global-space-bar').style.width = data.global.percentage + '%';
            document.getElementById('total-space').textContent = data.global.total;
            
            document.getElementById('personal-space').textContent = `${data.personal.used} / ${data.personal.total}`;
            document.getElementById('personal-space-bar').style.width = data.personal.percentage + '%';
            document.getElementById('personal-quota').textContent = data.personal.quota;
        }
    })
    .catch(error => {
        console.error('加载空间信息失败:', error);
    });
}

// 加载操作日志
function loadLogs() {
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'getLogs',
            password: currentPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderLogs(data.logs);
        }
    })
    .catch(error => {
        console.error('加载操作日志失败:', error);
    });
}

// 渲染操作日志
function renderLogs(logs) {
    const tableBody = document.getElementById('logs-table-body');
    tableBody.innerHTML = '';
    
    logs.forEach(log => {
        const row = document.createElement('tr');
        row.className = 'border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${log.time}</td>
            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">${log.user}</td>
            <td class="px-4 py-3 text-sm">
                <span class="px-2 py-1 rounded-full text-xs ${getLogTypeClass(log.type)}">
                    ${getLogTypeText(log.type)}
                </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">${log.details}</td>
        `;
        tableBody.appendChild(row);
    });
}

// 获取日志类型样式
function getLogTypeClass(type) {
    switch (type) {
        case 'upload': return 'bg-success/10 text-success';
        case 'delete': return 'bg-danger/10 text-danger';
        case 'rename': return 'bg-warning/10 text-warning';
        case 'login': return 'bg-info/10 text-info';
        default: return 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
    }
}

// 获取日志类型文本
function getLogTypeText(type) {
    switch (type) {
        case 'upload': return '上传';
        case 'delete': return '删除';
        case 'rename': return '重命名';
        case 'login': return '登录';
        default: return type;
    }
}

// 加载公告
function loadAnnouncements() {
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'getAnnouncements',
            password: currentPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderAnnouncements(data.announcements);
        }
    })
    .catch(error => {
        console.error('加载公告失败:', error);
    });
}

// 渲染公告
function renderAnnouncements(announcements) {
    const announcementsList = document.getElementById('announcements-list');
    announcementsList.innerHTML = '';
    
    if (announcements.length === 0) {
        announcementsList.innerHTML = `
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <i class="fas fa-bullhorn text-4xl mb-4"></i>
                <p>暂无公告</p>
            </div>
        `;
        return;
    }
    
    announcements.forEach(announcement => {
        const announcementCard = document.createElement('div');
        announcementCard.className = 'bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-4';
        announcementCard.innerHTML = `
            <div class="flex items-start justify-between mb-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">${announcement.title}</h3>
                <div class="flex gap-2">
                    <button class="text-primary hover:underline" onclick="editAnnouncement(${announcement.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="text-red-500 hover:underline" onclick="deleteAnnouncement(${announcement.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-300 mb-3">${announcement.content}</p>
            <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                <span>发布时间: ${announcement.created}</span>
                <span>有效期: ${announcement.expiry}天</span>
            </div>
        `;
        announcementsList.appendChild(announcementCard);
    });
}

// 显示添加用户弹窗
function showAddUserModal() {
    document.getElementById('add-user-modal').style.display = 'flex';
}

// 关闭添加用户弹窗
function closeAddUserModal() {
    document.getElementById('add-user-modal').style.display = 'none';
    document.getElementById('user-password').value = '';
    document.getElementById('user-role').value = 'user';
}

// 处理添加用户
function handleAddUser(event) {
    event.preventDefault();
    
    const password = document.getElementById('user-password').value;
    const role = document.getElementById('user-role').value;
    
    if (!password) {
        alert('请输入密码');
        return;
    }
    
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'addUser',
            password: currentPassword,
            newPassword: password,
            role: role
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('用户添加成功');
            closeAddUserModal();
            loadUsers();
        } else {
            alert('用户添加失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('添加用户失败:', error);
        alert('添加用户失败');
    });
}

// 显示添加公告弹窗
function showAddAnnouncementModal() {
    document.getElementById('add-announcement-modal').style.display = 'flex';
}

// 关闭添加公告弹窗
function closeAddAnnouncementModal() {
    document.getElementById('add-announcement-modal').style.display = 'none';
    document.getElementById('announcement-title').value = '';
    document.getElementById('announcement-content').value = '';
    document.getElementById('announcement-expiry').value = '7';
}

// 处理添加公告
function handleAddAnnouncement(event) {
    event.preventDefault();
    
    const title = document.getElementById('announcement-title').value;
    const content = document.getElementById('announcement-content').value;
    const expiry = document.getElementById('announcement-expiry').value;
    
    if (!title || !content) {
        alert('请填写标题和内容');
        return;
    }
    
    fetch('../upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'addAnnouncement',
            password: currentPassword,
            title: title,
            content: content,
            expiry: expiry
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('公告发布成功');
            closeAddAnnouncementModal();
            loadAnnouncements();
        } else {
            alert('公告发布失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('发布公告失败:', error);
        alert('发布公告失败');
    });
}

// 编辑用户
function editUser(userId) {
    // 这里可以实现编辑用户的功能
    alert('编辑用户功能开发中');
}

// 删除用户
function deleteUser(userId) {
    if (confirm('确定要删除这个用户吗？')) {
        fetch('../upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'deleteUser',
                password: currentPassword,
                userId: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('用户删除成功');
                loadUsers();
            } else {
                alert('用户删除失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('删除用户失败:', error);
            alert('删除用户失败');
        });
    }
}

// 编辑公告
function editAnnouncement(announcementId) {
    // 这里可以实现编辑公告的功能
    alert('编辑公告功能开发中');
}

// 删除公告
function deleteAnnouncement(announcementId) {
    if (confirm('确定要删除这个公告吗？')) {
        fetch('../upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'deleteAnnouncement',
                password: currentPassword,
                announcementId: announcementId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('公告删除成功');
                loadAnnouncements();
            } else {
                alert('公告删除失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('删除公告失败:', error);
            alert('删除公告失败');
        });
    }
}

// 显示公共空间设置
function showPublicSpaceSettings() {
    alert('公共空间设置功能开发中');
}

// 返回主站
function backToMain() {
    window.location.href = '../index.html';
}

// 退出登录
function logout() {
    localStorage.removeItem('currentPassword');
    window.location.href = '../index.html';
}

// 页面加载完成后初始化
window.onload = init;