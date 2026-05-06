// 全局变量
let currentPassword = '';
let files = [];
let publicFiles = [];
let folders = [];
let currentView = 'personal';
let displayView = 'list';
let currentPath = '';
let selectedFiles = [];
let usedSize = 0;
let maxSize = 2 * 1024 * 1024 * 1024;
let globalUsedSize = 0;
let globalMaxSize = 10 * 1024 * 1024 * 1024;
let publicUsedSize = 0;
let publicMaxSize = 5 * 1024 * 1024 * 1024;
let isAdmin = false;
let isLoggedIn = false;

// DOM元素
const loginContainer = document.getElementById('login-container');
const mainContainer = document.getElementById('main-container');
const loginPassword = document.getElementById('login-password');
const uploadArea = document.getElementById('upload-area');
const fileInput = document.getElementById('file-input');
const fileList = document.getElementById('file-list');
const fileListView = document.getElementById('file-list-view');
const fileGridView = document.getElementById('file-grid-view');
const progressBar = document.getElementById('progress-bar');
const progressFill = document.getElementById('progress-fill');
const changePasswordModal = document.getElementById('change-password-modal');
const networkStatus = document.getElementById('network-status');
const pingValue = document.getElementById('ping-value');

// ========== 暗色模式 ==========
function getPreferredTheme() {
    const saved = localStorage.getItem('wpanTheme');
    if (saved) return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('wpanTheme', theme);
    const icon = document.getElementById('theme-icon');
    if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}

function toggleDarkMode() {
    const current = document.documentElement.getAttribute('data-theme');
    applyTheme(current === 'dark' ? 'light' : 'dark');
}

applyTheme(getPreferredTheme());

// ========== 移动端侧边栏 ==========
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('show');
}

// ========== 初始化 ==========
function init() {
    checkSession().then(loggedIn => {
        if (loggedIn) { showMain(); refreshFileList(); }
        else { showLogin(); }
    }).catch(() => { showLogin(); });

    initDragAndDrop();
    initFileInput();
    initViewToggle();
    initSidebarNavigation();
    startNetworkPing();
}

async function checkSession() {
    try {
        const response = await fetch('upload.php?action=ping', { cache: 'no-store' });
        if (!response.ok) return false;
        const fileResp = await fetch('files.php', { cache: 'no-store' });
        const data = await fileResp.json();
        if (data.success) {
            isLoggedIn = true;
            isAdmin = data.admin || false;
            files = data.files || [];
            publicFiles = data.publicFiles || [];
            updateSpaceUsage(data);
            return true;
        }
    } catch (e) {}
    return false;
}

function showLogin() {
    loginContainer.style.display = 'flex';
    mainContainer.style.display = 'none';
    loginPassword.value = '';
    setTimeout(() => loginPassword.focus(), 100);
}

function showRegisterModal() { document.getElementById('register-modal').classList.add('show'); }

function closeRegisterModal() {
    document.getElementById('register-modal').classList.remove('show');
    document.getElementById('register-password').value = '';
    document.getElementById('confirm-password').value = '';
}

function showMain() {
    loginContainer.style.display = 'none';
    mainContainer.style.display = 'block';
    const adminBadge = document.getElementById('admin-badge');
    adminBadge.style.display = isAdmin ? 'inline-block' : 'none';
    document.getElementById('user-id').textContent = isAdmin ? '管理员' : '用户';
}

function handleLogout() {
    if (!confirm('确定要退出登录吗？')) return;
    fetch('upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=logout'
    }).then(() => {
        isLoggedIn = false; currentPassword = ''; isAdmin = false;
        files = []; publicFiles = []; selectedFiles = [];
        showLogin(); showToast('已退出登录');
    }).catch(() => { showLogin(); });
}

function handleLogin(event) {
    event.preventDefault();
    const password = loginPassword.value.trim();
    if (!password) { alert('请输入密码'); return; }
    fetch('upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=login&password=${encodeURIComponent(password)}`
    }).then(r => r.json()).then(data => {
        if (data.success) {
            currentPassword = password;
            isAdmin = data.role === 'admin';
            isLoggedIn = true;
            showMain(); refreshFileList();
        } else { alert(data.message); }
    }).catch(error => { console.error('登录失败:', error); alert('登录失败，请重试'); });
}

function handleRegister(event) {
    event.preventDefault();
    const password = document.getElementById('register-password').value.trim();
    const confirmPassword = document.getElementById('confirm-password').value.trim();
    if (!password) { alert('请输入密码'); return; }
    if (password !== confirmPassword) { alert('两次输入的密码不一致'); return; }
    fetch('upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=register&password=${encodeURIComponent(password)}`
    }).then(r => r.json()).then(data => {
        if (data.success) {
            currentPassword = password; isAdmin = data.role === 'admin'; isLoggedIn = true;
            document.getElementById('register-modal').classList.remove('show');
            showMain(); refreshFileList(); showToast('注册成功');
        } else { alert(data.message); }
    }).catch(error => { console.error('注册失败:', error); alert('注册失败，请重试'); });
}

// ========== 新建文件夹 ==========
function showNewFolderModal() {
    document.getElementById('folder-name').value = '';
    document.getElementById('new-folder-modal').classList.add('show');
    setTimeout(() => document.getElementById('folder-name').focus(), 100);
}
function closeNewFolderModal() { document.getElementById('new-folder-modal').classList.remove('show'); }
function handleCreateFolder(event) {
    event.preventDefault();
    const folderName = document.getElementById('folder-name').value.trim();
    if (!folderName) { alert('请输入文件夹名称'); return; }
    if (/[\\/:*?"<>|]/.test(folderName)) { alert('文件夹名称包含非法字符'); return; }
    let body = `action=create_folder&folder_name=${encodeURIComponent(folderName)}`;
    if (currentPassword) body += `&password=${encodeURIComponent(currentPassword)}`;
    if (currentPath) body += `&dir=${encodeURIComponent(currentPath)}`;
    fetch('upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    }).then(r => r.json()).then(data => {
        if (data.success) { closeNewFolderModal(); refreshFileList(); showToast('文件夹创建成功'); }
        else { alert('创建失败：' + data.message); }
    }).catch(() => alert('创建失败，请重试'));
}

// ========== 移动文件 ==========
function loadFolderList(callback) {
    let url = 'upload.php?action=list_folders';
    if (currentPassword) url += `&password=${encodeURIComponent(currentPassword)}`;
    fetch(url).then(r => r.json()).then(data => {
        callback(data.success ? (data.folders || []) : []);
    }).catch(() => callback([]));
}

function showMoveModal(itemName, isBatch = false) {
    document.getElementById('move-item-name').value = isBatch ? '__batch__' : itemName;
    const select = document.getElementById('move-target');
    select.innerHTML = '<option value="">根目录</option>';
    document.getElementById('move-modal').classList.add('show');
    loadFolderList(function(folders) {
        folders.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.path; opt.textContent = '📁 ' + f.path;
            if (f.path === currentPath) opt.disabled = true;
            select.appendChild(opt);
        });
    });
}

function showMoveSelectedModal() {
    if (selectedFiles.length === 0) { showToast('请先选择要移动的文件', true); return; }
    showMoveModal('', true);
}

function closeMoveModal() { document.getElementById('move-modal').classList.remove('show'); }

function handleMove(event) {
    event.preventDefault();
    const itemVal = document.getElementById('move-item-name').value;
    const targetDir = document.getElementById('move-target').value;

    if (itemVal === '__batch__') {
        if (selectedFiles.length === 0) { alert('没有选中的文件'); return; }
        let successCount = 0, failCount = 0, completed = 0;
        const total = selectedFiles.length;
        selectedFiles.forEach(filename => {
            let body = `action=move&item=${encodeURIComponent(filename)}&target_dir=${encodeURIComponent(targetDir)}`;
            if (currentPassword) body += `&password=${encodeURIComponent(currentPassword)}`;
            if (currentPath) body += `&dir=${encodeURIComponent(currentPath)}`;
            fetch('upload.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(r => r.json()).then(data => {
                completed++;
                if (data.success) successCount++; else failCount++;
                if (completed === total) {
                    closeMoveModal(); selectedFiles = []; refreshFileList();
                    showToast(`移动完成：成功 ${successCount} 个${failCount > 0 ? '，失败 ' + failCount + ' 个' : ''}`);
                }
            }).catch(() => {
                completed++; failCount++;
                if (completed === total) { closeMoveModal(); refreshFileList(); showToast(`移动完成：成功 ${successCount} 个，失败 ${failCount} 个`, true); }
            });
        });
        return;
    }

    let body = `action=move&item=${encodeURIComponent(itemVal)}&target_dir=${encodeURIComponent(targetDir)}`;
    if (currentPassword) body += `&password=${encodeURIComponent(currentPassword)}`;
    if (currentPath) body += `&dir=${encodeURIComponent(currentPath)}`;
    fetch('upload.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    }).then(r => r.json()).then(data => {
        if (data.success) { closeMoveModal(); refreshFileList(); showToast('移动成功'); }
        else { alert('移动失败：' + data.message); }
    }).catch(() => alert('移动失败，请重试'));
}

// ========== 重命名 ==========
function showRenameModal(name, isDir = false) {
    document.getElementById('rename-old-name').value = name;
    document.getElementById('rename-is-dir').value = isDir ? 'true' : 'false';
    document.getElementById('rename-new-name').value = name;
    document.getElementById('rename-modal').classList.add('show');
    setTimeout(() => document.getElementById('rename-new-name').focus(), 100);
}
function closeRenameModal() { document.getElementById('rename-modal').classList.remove('show'); }
function handleRename(event) {
    event.preventDefault();
    const oldName = document.getElementById('rename-old-name').value;
    const newName = document.getElementById('rename-new-name').value.trim();
    if (!newName) { alert('请输入新名称'); return; }
    if (/[\\/:*?"<>|]/.test(newName)) { alert('名称包含非法字符'); return; }
    let body = `action=rename&old_name=${encodeURIComponent(oldName)}&new_name=${encodeURIComponent(newName)}`;
    if (currentPassword) body += `&password=${encodeURIComponent(currentPassword)}`;
    if (currentPath) body += `&dir=${encodeURIComponent(currentPath)}`;
    fetch('upload.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
    }).then(r => r.json()).then(data => {
        if (data.success) { closeRenameModal(); refreshFileList(); showToast('重命名成功'); }
        else { alert('重命名失败：' + data.message); }
    }).catch(() => alert('重命名失败，请重试'));
}

// 修改密码
function showChangePassword() { changePasswordModal.classList.add('show'); }
function closeChangePasswordModal() {
    changePasswordModal.classList.remove('show');
    document.getElementById('current-password').value = '';
    document.getElementById('new-password').value = '';
}
function handleChangePassword(event) {
    event.preventDefault();
    const c = document.getElementById('current-password').value;
    const n = document.getElementById('new-password').value;
    if (c && n) {
        fetch('upload.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=change_password&current_password=${encodeURIComponent(c)}&new_password=${encodeURIComponent(n)}`
        }).then(r => r.json()).then(data => {
            if (data.success) { showToast('密码修改成功，请重新登录'); closeChangePasswordModal(); setTimeout(() => handleLogout(), 1500); }
            else { alert('密码修改失败：' + data.message); }
        }).catch(error => { console.error('密码修改失败:', error); alert('密码修改失败，请重试'); });
    }
}

// 拖拽上传
function initDragAndDrop() {
    uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    uploadArea.addEventListener('dragleave', () => { uploadArea.classList.remove('dragover'); });
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault(); uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) uploadFiles(e.dataTransfer.files, false);
    });
    uploadArea.addEventListener('click', (e) => { if (!e.target.closest('button')) fileInput.click(); });
}

function initFileInput() {
    fileInput.addEventListener('change', (e) => { if (e.target.files.length > 0) uploadFiles(e.target.files, false); });
}

function uploadToPublic() { fileInput.click(); fileInput.dataset.isPublic = 'true'; }

const CHUNK_SIZE = 10 * 1024 * 1024; // 分片大小 10MB
let currentXhr = null; // 当前上传的 XHR，用于取消
let currentChunkUpload = null; // 当前分片上传的状态

/**
 * 分片上传大文件
 */
async function uploadFileInChunks(file, isPublic, progressCallbacks) {
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const uploadId = 'chunk_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    const totalBytes = file.size;
    let uploadedBytes = 0;
    const { onProgress, onSpeed } = progressCallbacks;
    const startTime = Date.now();

    const state = { cancelled: false };
    currentChunkUpload = state;
    console.log('[分片] 开始分片上传:', file.name, totalChunks + '片', file.size + 'bytes');

    for (let i = 0; i < totalChunks; i++) {
        if (state.cancelled) break;

        const start = i * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('action', 'upload_chunk');
        formData.append('upload_id', uploadId);
        formData.append('chunk_index', i);
        formData.append('total_chunks', totalChunks);
        formData.append('file_name', file.name);
        formData.append('file_size', file.size);
        formData.append('is_public', isPublic);
        if (currentPassword) formData.append('password', currentPassword);
        if (currentPath && !isPublic) formData.append('dir', currentPath);
        formData.append('chunk', chunk);

        console.log('[分片] 上传第', (i+1)+'/'+totalChunks, '片, 大小:', (end-start), 'bytes');
        // 上传分片（最多重试3次）
        let retries = 3;
        let success = false;
        while (retries > 0 && !success && !state.cancelled) {
            try {
                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    currentXhr = xhr;
                    xhr.onload = function () {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.success) { success = true; resolve(); }
                            else reject(new Error(data.message || '分片上传失败'));
                        } catch (e) { reject(e); }
                    };
                    xhr.onerror = () => reject(new Error('网络错误'));
                    xhr.onabort = () => reject(new Error('已取消'));
                    xhr.open('POST', 'upload.php');
                    xhr.send(formData);
                });
            } catch (e) {
                if (state.cancelled) {
                    retries = 0; // 取消时立即退出重试循环
                } else {
                    retries--;
                    if (retries === 0) throw e;
                    await new Promise(r => setTimeout(r, 1000)); // 重试前等待1秒
                }
            }

        if (state.cancelled) break;

        uploadedBytes += chunk.size;
        const pct = Math.min((uploadedBytes / totalBytes) * 100, 100);
        onProgress(pct, uploadedBytes, totalBytes);
        const elapsed = (Date.now() - startTime) / 1000;
        if (elapsed > 0) onSpeed(formatFileSize(uploadedBytes / elapsed) + '/s');
    }

    currentXhr = null;

    if (state.cancelled) {
        // 通知服务器清理分片
        try {
            await fetch('upload.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=cancel_chunks&upload_id=' + encodeURIComponent(uploadId)
            });
        } catch (e) { /* ignore */ }
        currentChunkUpload = null;
        return null;
    }

    // 合并分片
    onProgress(99, totalBytes, totalBytes);
    onSpeed('合并中...');

    const mergeFormData = new FormData();
    mergeFormData.append('action', 'merge_chunks');
    mergeFormData.append('upload_id', uploadId);
    mergeFormData.append('total_chunks', totalChunks);
    mergeFormData.append('file_name', file.name);
    mergeFormData.append('is_public', isPublic);
    if (currentPassword) mergeFormData.append('password', currentPassword);
    if (currentPath && !isPublic) mergeFormData.append('dir', currentPath);

    try {
        const resp = await fetch('upload.php', { method: 'POST', body: mergeFormData });
        const data = await resp.json();
        currentChunkUpload = null;
        return data;
    } catch (e) {
        currentChunkUpload = null;
        throw e;
    }
}

function uploadFiles(fileList, isPublic = false) {
    if (fileInput.dataset.isPublic === 'true') { isPublic = true; fileInput.dataset.isPublic = 'false'; }

    const progressSpeed = document.getElementById('progress-speed');
    const progressPercentText = document.getElementById('progress-percent-text');
    const progressPercent = document.getElementById('progress-percent');
    const progressSize = document.getElementById('progress-size');
    const progressFiles = document.getElementById('progress-files');
    const cancelBtn = document.getElementById('btn-cancel-upload');

    // 显示文件列表
    let filesHtml = '';
    for (let i = 0; i < fileList.length; i++) {
        const icon = fileList[i].size >= CHUNK_SIZE ? '<i class="fas fa-file"></i>' : '<i class="fas fa-file"></i>';
        const tag = fileList[i].size >= CHUNK_SIZE ? ' <span class="chunk-badge">分片</span>' : '';
        filesHtml += `<div class="progress-file-item"><span class="name">${icon} ${escHtml(fileList[i].name)}${tag}</span><span class="size">${formatFileSize(fileList[i].size)}</span></div>`;
    }
    progressFiles.innerHTML = filesHtml;

    let totalSize = 0;
    for (let i = 0; i < fileList.length; i++) totalSize += fileList[i].size;

    progressBar.classList.add('show');
    cancelBtn.style.display = 'inline-flex';
    progressFill.style.width = '0%';
    progressSpeed.textContent = '0 MB/s';
    progressPercentText.textContent = '0%';
    progressPercent.textContent = '0%';
    progressSize.textContent = `0 B / ${formatFileSize(totalSize)}`;

    const startTime = Date.now();
    let uploadedSize = 0;

    function updateProgress(pct, loaded, total) {
        uploadedSize = loaded || uploadedSize;
        const p = pct !== undefined ? pct : (total ? Math.min((uploadedSize / total) * 100, 100) : 0);
        progressFill.style.width = `${p}%`;
        progressPercent.textContent = `${Math.round(p)}%`;
        progressPercentText.textContent = `${Math.round(p)}%`;
        progressSize.textContent = `${formatFileSize(uploadedSize)} / ${formatFileSize(total || totalSize)}`;
        const elapsed = (Date.now() - startTime) / 1000;
        if (elapsed > 0 && uploadedSize > 0) progressSpeed.textContent = formatFileSize(uploadedSize / elapsed) + '/s';
    }

    function finishUpload(data) {
        progressBar.classList.remove('show');
        currentXhr = null;
        if (data && data.success) {
            refreshFileList();
            if (data.usedSize !== undefined) updateSpaceUsage(data);
            showToast('文件上传成功');
        } else {
            showToast('上传失败：' + (data?.message || '未知错误'), true);
        }
    }

    function handleError(msg) {
        progressBar.classList.remove('show');
        currentXhr = null;
        showToast(msg || '上传失败，请重试', true);
    }

    // 检查是否有大文件（>= 5MB）
    const hasLargeFile = Array.from(fileList).some(f => f.size >= CHUNK_SIZE);
    console.log('[上传] 大文件检测:', hasLargeFile, '文件大小:', fileList[0]?.size, '分片阈值:', CHUNK_SIZE);

    if (!hasLargeFile) {
        console.log('[上传] 走原始上传路径');
        // 所有文件都小，使用原始单次上传
        const formData = new FormData();
        for (let i = 0; i < fileList.length; i++) formData.append('files[]', fileList[i]);
        formData.append('is_public', isPublic);
        if (currentPassword) formData.append('password', currentPassword);
        if (currentPath && !isPublic) formData.append('dir', currentPath);

        const xhr = new XMLHttpRequest();
        currentXhr = xhr;

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                uploadedSize = e.loaded;
                updateProgress(null, e.loaded, e.total);
            }
        });

        xhr.addEventListener('load', function() {
            try { finishUpload(JSON.parse(xhr.responseText)); }
            catch (e) { handleError(); }
        });
        xhr.addEventListener('abort', function() {
            currentXhr = null; progressBar.classList.remove('show');
            showToast('上传已取消', true);
        });
        xhr.addEventListener('error', function() { handleError(); });
        xhr.open('POST', 'upload.php');
        xhr.send(formData);
    } else {
        console.log('[上传] 走分片上传路径，文件数:', fileList.length);
        // 有大文件，使用分片上传（逐文件处理）
        (async () => {
            currentXhr = null;
            let allSuccess = true;
            let lastData = null;

            for (let i = 0; i < fileList.length; i++) {
                const file = fileList[i];
                if (file.size >= CHUNK_SIZE) {
                    // 分片上传大文件
                    try {
                        progressSpeed.textContent = '准备分片...';
                        const data = await uploadFileInChunks(file, isPublic, {
                            onProgress: (pct, loaded, total) => {
                                // 用已上传总量计算整体百分比，不用当前文件的百分比
                                updateProgress(undefined, uploadedSize + loaded, totalSize);
                            },
                            onSpeed: (speed) => { progressSpeed.textContent = speed; }
                        });
                        if (data === null) {
                            // 已取消
                            progressBar.classList.remove('show');
                            showToast('上传已取消', true);
                            return;
                        }
                        uploadedSize += file.size;
                        lastData = data;
                        if (!data.success) { allSuccess = false; break; }
                    } catch (e) {
                        allSuccess = false;
                        lastData = { success: false, message: e.message || '大文件上传失败' };
                        break;
                    }
                } else {
                    // 小文件逐个上传
                    const formData = new FormData();
                    formData.append('files[]', file);
                    formData.append('is_public', isPublic);
                    if (currentPassword) formData.append('password', currentPassword);
                    if (currentPath && !isPublic) formData.append('dir', currentPath);

                    try {
                        const resp = await fetch('upload.php', { method: 'POST', body: formData });
                        const data = await resp.json();
                        uploadedSize += file.size;
                        updateProgress(null, uploadedSize, totalSize);
                        lastData = data;
                        if (!data.success) { allSuccess = false; break; }
                    } catch (e) {
                        allSuccess = false;
                        lastData = { success: false, message: e.message || '上传失败' };
                        break;
                    }
                }
            }

            if (allSuccess) {
                finishUpload(lastData || { success: true });
            } else {
                finishUpload(lastData || { success: false, message: '上传失败' });
            }
        })();
    }
}

function cancelUpload() {
    if (currentChunkUpload) {
        currentChunkUpload.cancelled = true;
        currentChunkUpload = null;
    }
    if (currentXhr) {
        currentXhr.abort();
        currentXhr = null;
    }
}

// 刷新文件列表
function refreshFileList() {
    let url = 'files.php';
    let params = [];
    if (currentPassword) params.push('password=' + encodeURIComponent(currentPassword));
    if (currentPath) params.push('dir=' + encodeURIComponent(currentPath));
    params.push('_=' + Date.now()); // 防缓存
    url += '?' + params.join('&');
    showSkeleton(); // 显示骨架屏

    fetch(url).then(r => r.json()).then(data => {
        if (data.success) {
            files = data.files || []; publicFiles = data.publicFiles || [];
            folders = data.folders || []; isAdmin = data.admin || isAdmin;
            currentPath = data.currentPath || '';
            updateSpaceUsage(data); renderFiles(); updateBreadcrumb();
            selectedFiles = []; updateSelectionUI();
        } else if (data.message && data.message.includes('登录')) { showLogin(); }
    }).catch(error => { console.error('获取文件列表失败:', error); });
}

// ========== 文件夹导航 ==========
function navigateToFolder(folderName) {
    currentPath = currentPath ? currentPath + '/' + folderName : folderName;
    selectedFiles = []; refreshFileList();
}
function navigateToPath(path) { currentPath = path; selectedFiles = []; refreshFileList(); }
function goToParentFolder() {
    if (!currentPath) return;
    const parts = currentPath.split('/'); parts.pop();
    currentPath = parts.join('/'); selectedFiles = []; refreshFileList();
}

function updateBreadcrumb() {
    const el = document.getElementById('breadcrumb');
    if (!currentPath || currentView !== 'personal') { el.style.display = 'none'; return; }
    el.style.display = 'flex';
    const parts = currentPath.split('/');
    let html = '<a href="#" onclick="navigateToPath(\'\'); return false"><i class="fas fa-home"></i> 根目录</a>';
    let accumulated = '';
    parts.forEach((part, i) => {
        if (!part) return;
        accumulated = accumulated ? accumulated + '/' + part : part;
        if (i === parts.length - 1) html += `<span class="sep"> › </span><span class="current">${escHtml(part)}</span>`;
        else html += `<span class="sep"> › </span><a href="#" onclick="navigateToPath('${accumulated}'); return false">${escHtml(part)}</a>`;
    });
    el.innerHTML = html;
}

function updateSpaceUsage(data) {
    usedSize = data.usedSize || 0; maxSize = data.maxSize || 2 * 1024 * 1024 * 1024;
    globalUsedSize = data.globalUsedSize || 0; globalMaxSize = data.globalMaxSize || 10 * 1024 * 1024 * 1024;
    publicUsedSize = data.publicUsedSize || 0; publicMaxSize = data.publicMaxSize || 5 * 1024 * 1024 * 1024;
    updateSpaceDisplay();
}

function initViewToggle() {
    const btns = document.querySelectorAll('.view-toggle button');
    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active'); displayView = btn.dataset.view; renderFiles();
        });
    });
}

function initSidebarNavigation() {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            sidebarLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            currentView = link.dataset.view;
            document.getElementById('section-title').textContent = currentView === 'personal' ? '个人文件' : '公共空间';
            document.getElementById('section-title-list').textContent = currentView === 'personal' ? '全部文件' : '公共空间';
            currentPath = '';
            document.getElementById('search-input').value = '';
            // 强制刷新数据
            refreshFileList();
            document.getElementById('search-input').value = ''; selectedFiles = []; updateSelectionUI();
            if (window.innerWidth <= 768) toggleSidebar();
        });
    });
    function checkMenuToggle() {
        const t = document.getElementById('menu-toggle');
        if (t) t.style.display = window.innerWidth <= 768 ? 'inline-flex' : 'none';
    }
    checkMenuToggle();
    window.addEventListener('resize', checkMenuToggle);
}

function updateSpaceDisplay() {
    const fill = document.getElementById('sidebar-space-fill');
    const text = document.getElementById('sidebar-space-text');
    const label = document.querySelector('.sidebar-space-label');
    let ds, dm;
    if (currentView === 'public') { ds = publicUsedSize; dm = publicMaxSize; if (label) label.textContent = '公共空间'; }
    else { ds = usedSize; dm = maxSize; if (label) label.textContent = '个人空间'; }
    fill.style.width = `${Math.min((ds / dm) * 100, 100)}%`;
    text.textContent = `${formatFileSize(ds)} / ${formatFileSize(dm)}`;
}

function sortFiles() {
    const v = document.getElementById('sort-select').value;
    const cf = currentView === 'personal' ? files : publicFiles;
    switch (v) {
        case 'date-desc': cf.sort((a, b) => b.date - a.date); break;
        case 'date-asc': cf.sort((a, b) => a.date - b.date); break;
        case 'size-desc': cf.sort((a, b) => b.size - a.size); break;
        case 'size-asc': cf.sort((a, b) => a.size - b.size); break;
        case 'name-asc': cf.sort((a, b) => a.name.localeCompare(b.name)); break;
        case 'name-desc': cf.sort((a, b) => b.name.localeCompare(a.name)); break;
    }
    renderFiles();
}

function searchFiles() {
    const t = document.getElementById('search-input').value.toLowerCase();
    if (!t) { renderFiles(currentView === 'personal' ? files : publicFiles); return; }
    const cf = currentView === 'personal' ? files : publicFiles;
    const ff = cf.filter(f => f.name.toLowerCase().includes(t));
    const df = (folders || []).filter(f => f.name.toLowerCase().includes(t));
    const of = folders; folders = df; renderFiles(ff); folders = of;
}

function renderFiles(filteredFiles = null) {
    const cf = filteredFiles || (currentView === 'personal' ? files : publicFiles);
    if (displayView === 'list') renderListView(cf);
    else renderGridView(cf);
}

// ========== 渲染列表视图 ==========
function renderListView(items) {
    fileListView.style.display = 'block';
    fileGridView.style.display = 'none';
    let allItems = [];
    if (currentView === 'personal') {
        if (currentPath) allItems.push({ isDir: true, isParent: true, name: '..', date: 0, size: 0 });
        (folders || []).forEach(f => allItems.push({ isDir: true, name: f.name, date: f.date || 0, size: 0, filename: f.name }));
    }
    (items || []).forEach(f => allItems.push(f));
    if (allItems.length === 0) {
        fileList.innerHTML = '<li class="empty-state"><i class="fas fa-folder-open"></i><h3>暂无文件</h3><p>上传文件开始使用 XX网盘</p></li>';
        return;
    }
    fileList.innerHTML = '';
    allItems.forEach(file => {
        if (file.isParent) {
            const li = document.createElement('li'); li.className = 'file-item'; li.style.cursor = 'pointer'; li.onclick = goToParentFolder;
            li.innerHTML = '<div class="file-thumb folder"><i class="fas fa-level-up-alt"></i></div><div class="file-info"><div class="file-name" style="color:var(--primary);font-weight:600">返回上级</div><div class="file-meta"><span>上级目录</span></div></div>';
            fileList.appendChild(li); return;
        }
        if (file.isDir) {
            const li = document.createElement('li'); li.className = 'file-item'; li.style.cursor = 'pointer'; li.onclick = () => navigateToFolder(file.name);
            li.innerHTML = `<div class="file-thumb folder"><i class="fas fa-folder"></i></div><div class="file-info"><div class="file-name">${escHtml(file.name)}</div><div class="file-meta"><span>文件夹</span></div></div><div class="file-actions"><button class="btn btn-text" onclick="event.stopPropagation();showRenameModal('${escHtml(file.name)}', true)" title="重命名"><i class="fas fa-pen"></i></button><button class="btn btn-text btn-danger-text" onclick="event.stopPropagation();deleteFile('${escHtml(file.name)}')" title="删除"><i class="fas fa-trash-alt"></i></button></div>`;
            fileList.appendChild(li); return;
        }
        const li = document.createElement('li'); li.className = 'file-item'; li.dataset.filename = file.filename;
        const ext = file.name.split('.').pop().toLowerCase();
        let fi = 'fas fa-file', tc = 'default';
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) { fi='fas fa-file-image'; tc='image'; }
        else if (ext==='pdf') { fi='fas fa-file-pdf'; tc='pdf'; }
        else if (['doc','docx'].includes(ext)) { fi='fas fa-file-word'; tc='doc'; }
        else if (['xls','xlsx'].includes(ext)) { fi='fas fa-file-excel'; tc='excel'; }
        else if (['ppt','pptx'].includes(ext)) { fi='fas fa-file-powerpoint'; tc='ppt'; }
        else if (['zip','rar','7z'].includes(ext)) { fi='fas fa-file-archive'; tc='zip'; }
        else if (['mp3','wav','flac'].includes(ext)) { fi='fas fa-file-audio'; tc='audio'; }
        else if (['mp4','avi','mov'].includes(ext)) { fi='fas fa-file-video'; tc='video'; }
        else if (['js','css','html','php'].includes(ext)) { fi='fas fa-file-code'; tc='code'; }
        else if (['txt','md'].includes(ext)) { fi='fas fa-file-alt'; tc='text'; }
        const fd = currentPath && currentView === 'personal' ? currentPath : '';
        let du = `download.php?file=${encodeURIComponent(file.filename)}`;
        if (fd) du += `&dir=${encodeURIComponent(fd)}`;
        if (file.userDir) du += `&userDir=${encodeURIComponent(file.userDir)}`;
        if (currentPassword) du += `&password=${encodeURIComponent(currentPassword)}`;
        li.innerHTML = `<input type="checkbox" class="file-checkbox" onchange="toggleFileSelection('${file.filename}')"><div class="file-thumb ${tc}"><i class="${fi}"></i></div><div class="file-info"><div class="file-name">${escHtml(file.name)}</div><div class="file-meta"><span>${formatFileSize(file.size)}</span><span>${formatDate(file.date)}</span>${file.userDir?'<span><i class="fas fa-user" style="font-size:10px"></i> '+file.userDir.substring(0,10)+'...</span>':''}${file.isPublic?'<span style="color:var(--info)"><i class="fas fa-globe"></i> 公共</span>':''}</div></div><div class="file-actions"><button class="btn btn-text" onclick="previewFile('${file.filename}', ${file.userDir?`'${file.userDir}'`:'null'})" title="预览"><i class="fas fa-eye"></i></button><button class="btn btn-text" onclick="shareFile('${file.filename}', ${file.userDir?`'${file.userDir}'`:'null'})" title="分享"><i class="fas fa-share-alt"></i></button><a href="${du}" class="btn btn-text" download="${escHtml(file.name)}" title="下载"><i class="fas fa-download"></i></a><button class="btn btn-text" onclick="showRenameModal('${escHtml(file.filename)}', false)" title="重命名"><i class="fas fa-pen"></i></button><button class="btn btn-text" onclick="showMoveModal('${escHtml(file.filename)}')" title="移动"><i class="fas fa-folder-open"></i></button><button class="btn btn-text btn-danger-text" onclick="deleteFile('${file.filename}', ${file.userDir?`'${file.userDir}'`:'null'})" title="删除"><i class="fas fa-trash-alt"></i></button></div>`;
        fileList.appendChild(li);
    });
}

// ========== 渲染图标视图 ==========
function renderGridView(items) {
    fileListView.style.display = 'none';
    fileGridView.style.display = 'grid';
    let allItems = [];
    if (currentView === 'personal') {
        if (currentPath) allItems.push({ isDir: true, isParent: true, name: '..', date: 0, size: 0 });
        (folders || []).forEach(f => allItems.push({ isDir: true, name: f.name, date: f.date || 0, size: 0, filename: f.name }));
    }
    (items || []).forEach(f => allItems.push(f));
    if (allItems.length === 0) {
        fileGridView.innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><h3>暂无文件</h3><p>上传文件开始使用 XX网盘</p></div>';
        return;
    }
    fileGridView.innerHTML = '';
    allItems.forEach(file => {
        if (file.isParent) {
            const div = document.createElement('div'); div.className = 'file-grid-item'; div.style.cursor = 'pointer'; div.onclick = goToParentFolder;
            div.innerHTML = '<div class="file-grid-thumb folder"><i class="fas fa-level-up-alt"></i></div><div class="file-grid-name" style="color:var(--primary);font-weight:600">返回上级</div><div class="file-grid-meta"><div>上级目录</div></div>';
            fileGridView.appendChild(div); return;
        }
        if (file.isDir) {
            const div = document.createElement('div'); div.className = 'file-grid-item'; div.style.cursor = 'pointer'; div.onclick = () => navigateToFolder(file.name);
            div.innerHTML = `<div class="file-grid-actions"><button class="btn" onclick="event.stopPropagation();showRenameModal('${escHtml(file.name)}', true)" title="重命名"><i class="fas fa-pen"></i></button><button class="btn" onclick="event.stopPropagation();deleteFile('${escHtml(file.name)}')" title="删除"><i class="fas fa-trash-alt"></i></button></div><div class="file-grid-thumb folder"><i class="fas fa-folder"></i></div><div class="file-grid-name">${escHtml(file.name)}</div><div class="file-grid-meta"><div>文件夹</div></div>`;
            fileGridView.appendChild(div); return;
        }
        const div = document.createElement('div'); div.className = 'file-grid-item'; div.dataset.filename = file.filename;
        const ext = file.name.split('.').pop().toLowerCase();
        let fi = 'fas fa-file', tc = 'default';
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) { fi='fas fa-file-image'; tc='image'; }
        else if (ext==='pdf') { fi='fas fa-file-pdf'; tc='pdf'; }
        else if (['doc','docx'].includes(ext)) { fi='fas fa-file-word'; tc='doc'; }
        else if (['xls','xlsx'].includes(ext)) { fi='fas fa-file-excel'; tc='excel'; }
        else if (['ppt','pptx'].includes(ext)) { fi='fas fa-file-powerpoint'; tc='ppt'; }
        else if (['zip','rar','7z'].includes(ext)) { fi='fas fa-file-archive'; tc='zip'; }
        else if (['mp3','wav','flac'].includes(ext)) { fi='fas fa-file-audio'; tc='audio'; }
        else if (['mp4','avi','mov'].includes(ext)) { fi='fas fa-file-video'; tc='video'; }
        else if (['js','css','html','php'].includes(ext)) { fi='fas fa-file-code'; tc='code'; }
        else if (['txt','md'].includes(ext)) { fi='fas fa-file-alt'; tc='text'; }
        const fd = currentPath && currentView === 'personal' ? currentPath : '';
        let du = `download.php?file=${encodeURIComponent(file.filename)}`;
        if (fd) du += `&dir=${encodeURIComponent(fd)}`;
        if (file.userDir) du += `&userDir=${encodeURIComponent(file.userDir)}`;
        if (currentPassword) du += `&password=${encodeURIComponent(currentPassword)}`;
        div.innerHTML = `<div class="file-grid-actions"><button class="btn" onclick="previewFile('${file.filename}', ${file.userDir?`'${file.userDir}'`:'null'})" title="预览"><i class="fas fa-eye"></i></button><button class="btn" onclick="shareFile('${file.filename}', ${file.userDir?`'${file.userDir}'`:'null'})" title="分享"><i class="fas fa-share-alt"></i></button><a href="${du}" class="btn" download="${escHtml(file.name)}" title="下载"><i class="fas fa-download"></i></a><button class="btn" onclick="showRenameModal('${escHtml(file.filename)}', false)" title="重命名"><i class="fas fa-pen"></i></button><button class="btn" onclick="showMoveModal('${escHtml(file.filename)}')" title="移动"><i class="fas fa-folder-open"></i></button><button class="btn" onclick="deleteFile('${file.filename}', ${file.userDir?`'${file.userDir}'`:'null'})" title="删除"><i class="fas fa-trash-alt"></i></button></div><div class="file-grid-thumb ${tc}"><i class="${fi}"></i></div><div class="file-grid-name" title="${escHtml(file.name)}">${escHtml(file.name)}</div><div class="file-grid-meta"><div>${formatFileSize(file.size)}</div><div>${formatDate(file.date)}</div></div>`;
        fileGridView.appendChild(div);
    });
}

// ========== 选择操作 ==========
function toggleFileSelection(filename) {
    const i = selectedFiles.indexOf(filename);
    if (i === -1) selectedFiles.push(filename); else selectedFiles.splice(i, 1);
    updateSelectionUI();
}
function updateSelectionUI() {
    const el = document.getElementById('selected-count');
    if (selectedFiles.length > 0) { el.textContent = `已选 ${selectedFiles.length} 项`; el.style.display = 'inline-block'; }
    else { el.style.display = 'none'; }
}
function selectAllFiles() {
    const cf = currentView === 'personal' ? files : publicFiles;
    const allFilenames = cf.map(f => f.filename);
    // 如果已经全选了，则取消全选；否则全选
    const isAllSelected = allFilenames.length > 0 && allFilenames.every(f => selectedFiles.includes(f));
    if (isAllSelected) {
        selectedFiles = [];
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.file-grid-item').forEach(el => el.classList.remove('selected'));
    } else {
        selectedFiles = [...allFilenames];
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = true);
        document.querySelectorAll('.file-grid-item').forEach(el => el.classList.add('selected'));
    }
    updateSelectionUI();
}

// ========== ZIP 打包下载 ==========
function downloadSelectedAsZip() {
    if (selectedFiles.length === 0) { showToast('请先选择要下载的文件', true); return; }
    let url = 'zip.php?files=' + encodeURIComponent(JSON.stringify(selectedFiles));
    if (currentPassword) url += '&password=' + encodeURIComponent(currentPassword);
    if (currentPath && currentView === 'personal') url += '&dir=' + encodeURIComponent(currentPath);
    window.location.href = url;
}

function deleteSelectedFiles() {
    if (selectedFiles.length === 0) { alert('请先选择要删除的文件'); return; }
    if (!confirm(`确定要删除选中的 ${selectedFiles.length} 个文件吗？`)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    if (currentPassword) fd.append('password', currentPassword);
    if (currentPath && currentView === 'personal') fd.append('dir', currentPath);
    if (currentView === 'public') fd.append('is_public', 'true');
    selectedFiles.forEach(f => fd.append('files[]', f));
    fetch('upload.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if (data.success) { refreshFileList(); showToast(data.message || '删除成功'); selectedFiles = []; }
            else { alert('删除失败：' + data.message); }
        }).catch(error => { console.error('删除失败:', error); alert('删除失败，请重试'); });
}

function deleteFile(filename, userDir = null) {
    if (!confirm('确定要删除这个文件吗？')) return;
    let body = `action=delete&file=${encodeURIComponent(filename)}`;
    if (currentPassword) body += `&password=${encodeURIComponent(currentPassword)}`;
    if (isAdmin && userDir) body += `&userDir=${encodeURIComponent(userDir)}`;
    if (currentPath && currentView === 'personal') body += `&dir=${encodeURIComponent(currentPath)}`;
    if (currentView === 'public') body += '&is_public=true';
    fetch('upload.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(r => r.json()).then(data => {
            if (data.success) { refreshFileList(); if (data.usedSize !== undefined) updateSpaceUsage(data); showToast('文件删除成功'); }
            else { alert('删除失败：' + data.message); }
        }).catch(error => { console.error('删除失败:', error); alert('删除失败，请重试'); });
}

// ========== 点击行/格选中 ==========
document.addEventListener('click', function(e) {
    // 列表视图
    const item = e.target.closest('.file-item');
    if (item && !e.target.closest('button') && !e.target.closest('a') && !e.target.closest('.file-checkbox')) {
        const cb = item.querySelector('.file-checkbox');
        if (cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); }
        return;
    }
    // 网格视图（仅文件可选中，文件夹点击导航）
    const gridItem = e.target.closest('.file-grid-item');
    if (gridItem && !e.target.closest('button') && !e.target.closest('a')) {
        const filename = gridItem.dataset.filename;
        if (!filename) return; // 文件夹或返回上级没有 filename
        const isSel = gridItem.classList.toggle('selected');
        const idx = selectedFiles.indexOf(filename);
        if (isSel && idx === -1) selectedFiles.push(filename);
        else if (!isSel && idx >= 0) selectedFiles.splice(idx, 1);
        updateSelectionUI();
    }
});

// ========== 右键菜单 ==========
document.addEventListener('contextmenu', function(e) {
    const item = e.target.closest('.file-item');
    const gridItem = e.target.closest('.file-grid-item');
    const target = item || gridItem;
    if (!target) return;
    
    // 如果是返回上级或文件夹的右键，只显示有限操作
    const isFolder = target.querySelector('.file-thumb.folder') || target.querySelector('.file-grid-thumb.folder');
    const isParent = target.querySelector('.fa-level-up-alt');
    if (isParent) return; // 返回上级不显示菜单
    
    e.preventDefault();
    
    const filename = target.dataset.filename;
    if (!filename) return;
    
    // 构建菜单项
    let html = '';
    html += '<div class="context-menu-item" onclick="closeContextMenu();previewFile(\''+filename+'\')"><i class="fas fa-eye"></i> 预览</div>';
    html += '<div class="context-menu-item" onclick="closeContextMenu();shareFile(\''+filename+'\')"><i class="fas fa-share-alt"></i> 分享</div>';
    if (!isFolder) {
        const du = target.querySelector('a[download]');
        if (du) html += '<div class="context-menu-item" onclick="closeContextMenu();window.location.href=\''+du.getAttribute('href')+'\'"><i class="fas fa-download"></i> 下载</div>';
    }
    html += '<div class="context-menu-sep"></div>';
    html += '<div class="context-menu-item" onclick="closeContextMenu();showRenameModal(\''+filename+'\', '+isFolder+')"><i class="fas fa-pen"></i> 重命名</div>';
    html += '<div class="context-menu-item" onclick="closeContextMenu();showMoveModal(\''+filename+'\')"><i class="fas fa-folder-open"></i> 移动</div>';
    html += '<div class="context-menu-sep"></div>';
    html += '<div class="context-menu-item danger" onclick="closeContextMenu();deleteFile(\''+filename+'\')"><i class="fas fa-trash-alt"></i> 删除</div>';

    const menu = document.getElementById('context-menu');
    menu.innerHTML = html;
    menu.style.display = 'block';
    
    // 防止超出视口
    const x = Math.min(e.clientX, window.innerWidth - menu.offsetWidth - 10);
    const y = Math.min(e.clientY, window.innerHeight - menu.offsetHeight - 10);
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
    menu.classList.add('show');
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.context-menu')) closeContextMenu();
});

function closeContextMenu() {
    const menu = document.getElementById('context-menu');
    menu.classList.remove('show');
    menu.style.display = 'none';
}

// ========== 骨架屏 ==========
function showSkeleton() {
    const list = document.getElementById('file-list');
    let html = '';
    for (let i = 0; i < 8; i++) {
        html += '<div class="skeleton-row"><div class="skeleton skeleton-icon"></div><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line-sm"></div></div>';
    }
    list.innerHTML = html;
}

// ========== 图片灯箱 ==========
let lightboxImages = [];
let lightboxIndex = 0;

function openLightbox(files, index) {
    lightboxImages = files;
    lightboxIndex = index;
    document.getElementById('lightbox').classList.add('show');
    updateLightbox();
}

function updateLightbox() {
    const img = document.getElementById('lightbox-img');
    const f = lightboxImages[lightboxIndex];
    if (!f) return;
    let url = `preview.php?file=${encodeURIComponent(f.filename)}`;
    if (currentPassword) url += `&password=${encodeURIComponent(currentPassword)}`;
    if (f.userDir) url += `&userDir=${encodeURIComponent(f.userDir)}`;
    img.src = url;
    img.classList.remove('zoomed');
    const counter = document.getElementById('lightbox-counter');
    counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
    lightboxImages = [];
}

function prevLightbox() {
    if (lightboxIndex > 0) { lightboxIndex--; updateLightbox(); }
}
function nextLightbox() {
    if (lightboxIndex < lightboxImages.length - 1) { lightboxIndex++; updateLightbox(); }
}
function toggleLightboxZoom() {
    document.getElementById('lightbox-img').classList.toggle('zoomed');
}

// 键盘导航
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('lightbox').classList.contains('show')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') prevLightbox();
    if (e.key === 'ArrowRight') nextLightbox();
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (confirm('确定要删除这张图片吗？')) {
            const f = lightboxImages[lightboxIndex];
            if (f) { deleteFile(f.filename); closeLightbox(); }
        }
    }
});

// ========== 工具函数 ==========
function escHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024, s = ['B','KB','MB','GB','TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + s[i];
}
function formatDate(ts) {
    const d = new Date(ts * 1000), n = new Date();
    const diff = n - d, oneDay = 86400000;
    if (diff < oneDay) return '今天 ' + d.toLocaleTimeString('zh-CN', { hour:'2-digit', minute:'2-digit' });
    if (diff < 2*oneDay) return '昨天 ' + d.toLocaleTimeString('zh-CN', { hour:'2-digit', minute:'2-digit' });
    return d.toLocaleString('zh-CN', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}
function showToast(msg, isError = false) {
    const existing = document.querySelector('.toast'); if (existing) existing.remove();
    const t = document.createElement('div'); t.className = 'toast';
    t.innerHTML = `<i class="fas ${isError?'fa-exclamation-circle':'fa-check-circle'}"></i> ${escHtml(msg)}`;
    if (isError) t.style.background = 'var(--danger)';
    document.body.appendChild(t);
    setTimeout(() => { t.style.transition = 'all 0.3s ease'; t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(-20px)'; setTimeout(() => { if (t.parentNode) t.remove(); }, 300); }, 3000);
}

function startNetworkPing() {
    function ping() {
        const st = performance.now();
        fetch('upload.php?action=ping').then(() => {
            const t = Math.round(performance.now() - st);
            pingValue.textContent = `${t} ms`;
            networkStatus.className = 'network-status';
            if (t <= 100) networkStatus.classList.add('good');
            else if (t <= 300) networkStatus.classList.add('warning');
            else networkStatus.classList.add('bad');
        }).catch(() => { pingValue.textContent = '-- ms'; networkStatus.className = 'network-status bad'; });
    }
    ping(); setInterval(ping, 3000);
}

// ========== 预览 ==========
function previewFile(filename, userDir = null) {
    const pm = document.getElementById('preview-modal');
    const pc = document.getElementById('preview-content');
    let pu = `preview.php?file=${encodeURIComponent(filename)}`;
    if (userDir) pu += `&userDir=${encodeURIComponent(userDir)}`;
    if (currentPath && currentView === 'personal') pu += `&dir=${encodeURIComponent(currentPath)}`;
    if (currentPassword) pu += `&password=${encodeURIComponent(currentPassword)}`;
    const ext = filename.split('.').pop().toLowerCase();
    const imageExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
    const videoExts = ['mp4','webm','avi','mov'];
    const audioExts = ['mp3','wav','flac','ogg','aac'];
    const textExts = ['txt','md','html','css','js','json','xml','php','log','yaml','yml','conf','ini'];
    const officeExts = ['doc','docx','xls','xlsx','ppt','pptx'];
    pc.innerHTML = '<div style="text-align:center;padding:40px"><i class="fas fa-spinner fa-pulse" style="font-size:32px;color:var(--text-muted)"></i><p style="margin-top:12px;color:var(--text-muted)">加载中...</p></div>';
    pm.classList.add('show');
    if (imageExts.includes(ext)) {
        // 收集当前视图所有图片，用于灯箱翻页
        const currentItems = currentView === 'personal' ? files : publicFiles;
        const imgFiles = currentItems.filter(f => {
            const e = f.name.split('.').pop().toLowerCase();
            return imageExts.includes(e);
        });
        const idx = imgFiles.findIndex(f => f.filename === filename);
        if (idx >= 0) {
            openLightbox(imgFiles, idx);
            return;
        }
        pc.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;min-height:200px"><img src="${pu}" style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:8px;"></div>`;
    } else if (videoExts.includes(ext)) {
        pc.innerHTML = `<video src="${pu}" controls style="max-width:100%;max-height:70vh;border-radius:8px;"></video>`;
    } else if (audioExts.includes(ext)) {
        pc.innerHTML = `<div style="padding:40px;text-align:center"><i class="fas fa-file-audio" style="font-size:64px;color:var(--primary);margin-bottom:20px"></i><br><audio src="${pu}" controls style="width:100%"></audio></div>`;
    } else if (ext === 'pdf') {
        pc.innerHTML = `<iframe src="${pu}" style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>`;
    } else if (officeExts.includes(ext)) {
        const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/');
        pc.innerHTML = `<iframe src="https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(baseUrl + pu)}" style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>`;
    } else if (textExts.includes(ext)) {
        fetch(pu).then(r => { if (!r.ok) throw Error(); return r.text(); })
            .then(text => { pc.innerHTML = `<pre style="white-space:pre-wrap;word-wrap:break-word;max-height:70vh;overflow:auto;padding:20px;background:var(--bg);border-radius:8px;font-size:13px;line-height:1.7;margin:0"><code>${escHtml(text)}</code></pre>`; })
            .catch(() => { pc.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-exclamation-circle" style="font-size:48px;color:var(--warning);margin-bottom:12px"></i><p>预览失败，请下载查看</p></div>'; });
    } else {
        pc.innerHTML = '<div style="text-align:center;padding:60px 20px;color:var(--text-muted)"><i class="fas fa-file" style="font-size:56px;margin-bottom:16px;color:var(--border)"></i><p>该文件类型暂不支持在线预览</p><p style="font-size:13px;margin-top:8px">请下载后使用本地应用查看</p></div>';
    }
}
function closePreviewModal() { document.getElementById('preview-modal').classList.remove('show'); document.getElementById('preview-content').innerHTML = ''; }

// ========== 分享 ==========
function shareFile(filename, userDir = null) {
    document.getElementById('share-file-name').value = filename;
    document.getElementById('share-user-dir').value = userDir || '';
    document.getElementById('share-url').value = '';
    document.getElementById('share-file-name').dataset.dir = currentPath && currentView === 'personal' ? currentPath : '';
    document.getElementById('share-modal').classList.add('show');
}
function closeShareModal() { document.getElementById('share-modal').classList.remove('show'); }
function handleShareFile(event) {
    event.preventDefault();
    const fn = document.getElementById('share-file-name').value;
    const fd = document.getElementById('share-file-name').dataset.dir || '';
    const ud = document.getElementById('share-user-dir').value;
    const ex = document.getElementById('share-expiry').value;
    let body = `action=create_share&file=${encodeURIComponent(fn)}&expiry=${ex}&userDir=${encodeURIComponent(ud)}`;
    if (fd) body += `&dir=${encodeURIComponent(fd)}`;
    if (currentPassword) body += `&password=${encodeURIComponent(currentPassword)}`;
    fetch('share.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(r => r.json()).then(data => {
            if (data.success) { document.getElementById('share-url').value = data.shareUrl; showToast('分享链接生成成功'); }
            else { alert('分享失败：' + data.message); }
        }).catch(error => { console.error('分享失败:', error); alert('分享失败，请重试'); });
}
function copyShareUrl() {
    const el = document.getElementById('share-url');
    if (!el.value) { showToast('请先生成分享链接', true); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(() => showToast('链接已复制到剪贴板')).catch(() => fallbackCopy());
    } else { fallbackCopy(); }
}
function fallbackCopy() { const el = document.getElementById('share-url'); el.select(); document.execCommand('copy'); showToast('链接已复制到剪贴板'); }

// 启动
init()}
