// 全局变量
let currentPassword = '';
let files = [];
let publicFiles = [];
let currentView = 'personal';
let displayView = 'list';
let selectedFiles = [];
let usedSize = 0;
let maxSize = 2 * 1024 * 1024 * 1024; // 2GB
let globalUsedSize = 0;
let globalMaxSize = 10 * 1024 * 1024 * 1024; // 10GB
let publicUsedSize = 0;
let publicMaxSize = 5 * 1024 * 1024 * 1024; // 5GB
let isAdmin = false;
let fileCache = {}; // 文件列表缓存
let cacheExpiry = 5 * 60 * 1000; // 缓存过期时间（5分钟）
let isLoading = false; // 是否正在加载
let pageSize = 20; // 每页显示的文件数量
let currentPage = 1; // 当前页码

// DOM 元素
const loginContainer = document.getElementById('login-container');
const mainContainer = document.getElementById('main-container');
const fileList = document.getElementById('file-list');
const fileListView = document.getElementById('file-list-view');
const fileGridView = document.getElementById('file-grid-view');
const sectionTitle = document.getElementById('section-title');
const userID = document.getElementById('user-id');
const adminBadge = document.getElementById('admin-badge');

// 初始化
function init() {
    // 加载缓存
    loadCache();
    
    // 检查本地存储中的登录状态
    const savedPassword = localStorage.getItem('password');
    if (savedPassword) {
        currentPassword = savedPassword;
        checkLogin();
    }
    
    // 初始化上传区域
    initUploadArea();
    
    // 初始化视图切换
    initViewToggle();
    
    // 初始化侧边栏导航
    initSidebarNavigation();
    
    // 初始化主题切换
    initThemeToggle();
    
    // 初始化网络状态检测
    initNetworkStatus();
}

// 检查登录状态
function checkLogin() {
    fetch('upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=login&password=${encodeURIComponent(currentPassword)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMain();
            refreshFileList();
        } else {
            showLogin();
        }
    })
    .catch(error => {
        console.error('登录检查失败:', error);
        showLogin();
    });
}

// 处理登录
function handleLogin(event) {
    event.preventDefault();
    const password = document.getElementById('login-password').value;
    
    fetch('upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=login&password=${encodeURIComponent(password)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentPassword = password;
            localStorage.setItem('password', password);
            isAdmin = (password === 'admin');
            showMain();
            refreshFileList();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('登录失败:', error);
        alert('登录失败，请重试');
    });
}

// 显示注册弹窗
function showRegisterModal() {
    document.getElementById('register-modal').classList.add('show');
}

// 关闭注册弹窗
function closeRegisterModal() {
    document.getElementById('register-modal').classList.remove('show');
}

// 处理注册
function handleRegister(event) {
    event.preventDefault();
    const password = document.getElementById('register-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    
    if (password !== confirmPassword) {
        alert('两次输入的密码不一致');
        return;
    }
    
    fetch('upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=register&password=${encodeURIComponent(password)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('注册成功，请登录');
            closeRegisterModal();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('注册失败:', error);
        alert('注册失败，请重试');
    });
}

// 显示登录界面
function showLogin() {
    loginContainer.style.display = 'block';
    mainContainer.style.display = 'none';
    localStorage.removeItem('password');
    currentPassword = '';
}

// 显示主界面
function showMain() {
    loginContainer.style.display = 'none';
    mainContainer.style.display = 'block';
    userID.textContent = isAdmin ? '管理员' : '用户';
    adminBadge.style.display = isAdmin ? 'inline-block' : 'none';
}

// 显示修改密码弹窗
function showChangePassword() {
    document.getElementById('change-password-modal').classList.add('show');
}

// 关闭修改密码弹窗
function closeChangePassword() {
    document.getElementById('change-password-modal').classList.remove('show');
}

// 处理密码修改
function handleChangePassword(event) {
    event.preventDefault();
    const currentPass = document.getElementById('current-password').value;
    const newPass = document.getElementById('new-password').value;
    
    fetch('upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=change_password&current_password=${encodeURIComponent(currentPass)}&new_password=${encodeURIComponent(newPass)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('密码修改成功，请重新登录');
            closeChangePassword();
            showLogin();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('密码修改失败:', error);
        alert('密码修改失败，请重试');
    });
}

// 初始化上传区域
function initUploadArea() {
    console.log('initUploadArea called');
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    
    console.log('uploadArea:', uploadArea);
    console.log('fileInput:', fileInput);
    
    // 点击上传
    uploadArea.addEventListener('click', () => {
        console.log('uploadArea clicked');
        fileInput.click();
    });
    
    // 拖拽上传
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            uploadFiles(e.dataTransfer.files);
        }
    });
    
    // 文件选择
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            uploadFiles(e.target.files);
        }
    });
}

// 上传文件到公共空间
function uploadToPublic() {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.multiple = true;
    fileInput.onchange = (e) => {
        if (e.target.files.length > 0) {
            uploadFiles(e.target.files, true);
        }
    };
    fileInput.click();
}

// 全局上传任务管理
let uploadTasks = [];

// 上传文件
function uploadFiles(files, isPublic = false) {
    console.log('uploadFiles called with', files.length, 'files, isPublic:', isPublic);
    console.log('currentPassword:', currentPassword);
    
    const progressBar = document.getElementById('progress-bar');
    const progressFill = document.getElementById('progress-fill');
    
    // 显示进度条
    progressBar.style.display = 'block';
    progressFill.style.width = '0%';
    
    let totalSize = 0;
    let uploadedSize = 0;
    let startTime = Date.now();
    
    // 计算总文件大小
    for (let i = 0; i < files.length; i++) {
        totalSize += files[i].size;
    }
    
    // 处理每个文件
    Array.from(files).forEach((file, index) => {
        uploadFileWithResume(file, isPublic, index, files.length, totalSize, uploadedSize, startTime, progressFill, progressBar);
    });
}

// 带断点续传的文件上传
function uploadFileWithResume(file, isPublic, fileIndex, totalFiles, totalSize, uploadedSize, startTime, progressFill, progressBar) {
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB 分块
    let currentChunk = 0;
    let uploadedChunks = 0;
    let fileSize = file.size;
    let chunks = Math.ceil(fileSize / CHUNK_SIZE);
    let isPaused = false;
    let xhr = null;
    
    // 生成文件唯一标识
    const fileId = generateFileId(file);
    
    // 检查是否有已上传的分块
    checkUploadProgress(fileId, file, isPublic).then(resumeChunk => {
        currentChunk = resumeChunk || 0;
        uploadedChunks = currentChunk;
        
        // 创建上传任务
        const task = {
            id: fileId,
            file: file,
            isPublic: isPublic,
            currentChunk: currentChunk,
            totalChunks: chunks,
            isPaused: false,
            pause: function() {
                isPaused = true;
                if (xhr) {
                    xhr.abort();
                }
            },
            resume: function() {
                isPaused = false;
                uploadNextChunk();
            }
        };
        
        uploadTasks.push(task);
        
        // 开始上传
        uploadNextChunk();
        
        function uploadNextChunk() {
            if (isPaused || currentChunk >= chunks) {
                return;
            }
            
            const start = currentChunk * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, fileSize);
            const chunk = file.slice(start, end);
            
            const formData = new FormData();
            formData.append('file', chunk);
            formData.append('fileId', fileId);
            formData.append('chunk', currentChunk);
            formData.append('totalChunks', chunks);
            formData.append('filename', file.name);
            formData.append('password', currentPassword);
            formData.append('is_public', isPublic);
            formData.append('action', 'upload_chunk');
            
            xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const chunkProgress = (e.loaded / e.total) * 100;
                    const fileProgress = ((uploadedChunks * CHUNK_SIZE) + e.loaded) / fileSize * 100;
                    const totalProgress = ((uploadedSize + (uploadedChunks * CHUNK_SIZE) + e.loaded) / totalSize) * 100;
                    progressFill.style.width = `${totalProgress}%`;
                    
                    // 计算上传速度
                    const elapsedTime = (Date.now() - startTime) / 1000;
                    const speed = (uploadedSize + (uploadedChunks * CHUNK_SIZE) + e.loaded) / elapsedTime;
                    const speedText = formatFileSize(speed) + '/s';
                    progressFill.setAttribute('data-speed', speedText);
                }
            });
            
            xhr.addEventListener('load', function() {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        currentChunk++;
                        uploadedChunks++;
                        
                        if (currentChunk < chunks) {
                            uploadNextChunk();
                        } else {
                            // 所有分块上传完成，合并文件
                            mergeChunks(fileId, file.name, isPublic).then(mergeResult => {
                                if (mergeResult.success) {
                                    uploadedSize += file.size;
                                    if (fileIndex === totalFiles - 1) {
                                        refreshFileList();
                                        // 更新空间使用情况
                                        if (mergeResult.usedSize) {
                                            updateSpaceUsage(mergeResult);
                                        }
                                        // 显示成功提示
                                        showToast('文件上传成功');
                                        // 隐藏进度条
                                        progressBar.style.display = 'none';
                                    }
                                } else {
                                    alert('文件合并失败：' + mergeResult.message);
                                    if (fileIndex === totalFiles - 1) {
                                        progressBar.style.display = 'none';
                                    }
                                }
                            });
                        }
                    } else {
                        alert('上传失败：' + data.message);
                        if (fileIndex === totalFiles - 1) {
                            progressBar.style.display = 'none';
                        }
                    }
                } catch (error) {
                    alert('上传失败，请重试');
                    if (fileIndex === totalFiles - 1) {
                        progressBar.style.display = 'none';
                    }
                }
            });
            
            xhr.addEventListener('error', function() {
                alert('上传失败，请重试');
                if (fileIndex === totalFiles - 1) {
                    progressBar.style.display = 'none';
                }
            });
            
            xhr.open('POST', 'upload.php');
            xhr.send(formData);
        }
    });
}

// 生成文件唯一标识
function generateFileId(file) {
    return btoa(file.name + file.size + file.lastModified);
}

// 检查上传进度
function checkUploadProgress(fileId, file, isPublic) {
    return new Promise((resolve, reject) => {
        fetch('upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=check_progress&fileId=${fileId}&password=${encodeURIComponent(currentPassword)}&is_public=${isPublic}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resolve(data.currentChunk || 0);
            } else {
                resolve(0);
            }
        })
        .catch(error => {
            resolve(0);
        });
    });
}

// 合并分块
function mergeChunks(fileId, filename, isPublic) {
    return new Promise((resolve, reject) => {
        fetch('upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=merge_chunks&fileId=${fileId}&filename=${encodeURIComponent(filename)}&password=${encodeURIComponent(currentPassword)}&is_public=${isPublic}`
        })
        .then(response => response.json())
        .then(data => {
            // 清除缓存
            clearCache();
            // 重置分页
            resetPagination();
            resolve(data);
        })
        .catch(error => {
            resolve({ success: false, message: '合并失败' });
        });
    });
}

// 清除缓存
function clearCache() {
    fileCache = {};
    try {
        localStorage.removeItem('fileCache');
    } catch (e) {
        console.error('缓存清除失败:', e);
    }
}

// 暂停所有上传
function pauseAllUploads() {
    uploadTasks.forEach(task => {
        if (!task.isPaused) {
            task.pause();
            task.isPaused = true;
        }
    });
    showToast('所有上传已暂停');
}

// 恢复所有上传
function resumeAllUploads() {
    uploadTasks.forEach(task => {
        if (task.isPaused) {
            task.resume();
            task.isPaused = false;
        }
    });
    showToast('所有上传已恢复');
}

// 重命名文件
function renameFile(filename, oldName, userDir = null) {
    const newName = prompt('请输入新文件名:', oldName);
    if (newName && newName !== oldName) {
        let body = `action=rename&file=${filename}&new_name=${encodeURIComponent(newName)}&password=${encodeURIComponent(currentPassword)}`;
        if (isAdmin && userDir) {
            body += `&userDir=${userDir}`;
        }
        
        fetch('upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 清除缓存
                clearCache();
                // 重置分页
                resetPagination();
                refreshFileList();
                showToast('文件重命名成功');
            } else {
                alert('重命名失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('重命名失败:', error);
            alert('重命名失败，请重试');
        });
    }
}

// 创建文件夹
function createFolder() {
    const folderName = prompt('请输入文件夹名称:');
    if (folderName) {
        fetch('upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=create_folder&folder_name=${encodeURIComponent(folderName)}&password=${encodeURIComponent(currentPassword)}&is_public=${currentView === 'public'}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 清除缓存
                clearCache();
                // 重置分页
                resetPagination();
                refreshFileList();
                showToast('文件夹创建成功');
            } else {
                alert('创建失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('创建失败:', error);
            alert('创建失败，请重试');
        });
    }
}

// 移动文件
function moveFile(filename, userDir = null) {
    const targetFolder = prompt('请输入目标文件夹路径:');
    if (targetFolder) {
        let body = `action=move_file&file=${filename}&target_folder=${encodeURIComponent(targetFolder)}&password=${encodeURIComponent(currentPassword)}`;
        if (isAdmin && userDir) {
            body += `&userDir=${userDir}`;
        }
        
        fetch('upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 清除缓存
                clearCache();
                // 重置分页
                resetPagination();
                refreshFileList();
                showToast('文件移动成功');
            } else {
                alert('移动失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('移动失败:', error);
            alert('移动失败，请重试');
        });
    }
}

// 批量移动文件
function batchMoveFiles() {
    if (selectedFiles.length === 0) {
        alert('请先选择要移动的文件');
        return;
    }
    
    const targetFolder = prompt('请输入目标文件夹路径:');
    if (targetFolder) {
        const formData = new FormData();
        formData.append('action', 'batch_move');
        formData.append('password', currentPassword);
        formData.append('target_folder', targetFolder);
        
        // 添加所有选中的文件名
        selectedFiles.forEach(filename => {
            formData.append('files[]', filename);
        });
        
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 清除缓存
                clearCache();
                // 重置分页
                resetPagination();
                refreshFileList();
                showToast('文件移动成功');
                selectedFiles = [];
            } else {
                alert('移动失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('移动失败:', error);
            alert('移动失败，请重试');
        });
    }
}

// 批量重命名文件
function batchRenameFiles() {
    if (selectedFiles.length === 0) {
        alert('请先选择要重命名的文件');
        return;
    }
    
    const prefix = prompt('请输入文件名前缀:');
    if (prefix) {
        const formData = new FormData();
        formData.append('action', 'batch_rename');
        formData.append('password', currentPassword);
        formData.append('prefix', prefix);
        
        // 添加所有选中的文件名
        selectedFiles.forEach(filename => {
            formData.append('files[]', filename);
        });
        
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 清除缓存
                clearCache();
                // 重置分页
                resetPagination();
                refreshFileList();
                showToast('文件重命名成功');
                selectedFiles = [];
            } else {
                alert('重命名失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('重命名失败:', error);
            alert('重命名失败，请重试');
        });
    }
}

// 刷新文件列表
function refreshFileList(forceRefresh = false) {
    const cacheKey = `files_${currentPassword}_${currentView}`;
    const now = Date.now();
    const startTime = Date.now();
    
    // 检查缓存是否有效
    if (!forceRefresh && fileCache[cacheKey] && (now - fileCache[cacheKey].timestamp < cacheExpiry)) {
        const cachedData = fileCache[cacheKey].data;
        files = cachedData.files;
        publicFiles = cachedData.publicFiles || [];
        updateSpaceUsage(cachedData);
        renderFiles();
        // 更新网络状态
        updateNetworkStatus(startTime);
        return;
    }
    
    if (isLoading) return;
    
    isLoading = true;
    showToast('加载中...', 'info');
    
    fetch(`files.php?password=${encodeURIComponent(currentPassword)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                files = data.files;
                publicFiles = data.publicFiles || [];
                // 更新空间使用情况
                updateSpaceUsage(data);
                
                // 更新缓存
                fileCache[cacheKey] = {
                    data: data,
                    timestamp: now
                };
                
                // 保存缓存到localStorage
                try {
                    localStorage.setItem('fileCache', JSON.stringify(fileCache));
                } catch (e) {
                    console.error('缓存保存失败:', e);
                }
                
                renderFiles();
            } else {
                console.error('获取文件列表失败:', data.message);
                showToast('获取文件列表失败', 'error');
            }
        })
        .catch(error => {
            console.error('获取文件列表失败:', error);
            showToast('网络错误，请重试', 'error');
        })
        .finally(() => {
            // 更新网络状态
            updateNetworkStatus(startTime);
            isLoading = false;
        });
}

// 加载更多文件
function loadMoreFiles() {
    if (isLoading) return;
    
    currentPage++;
    renderFiles();
}

// 重置分页
function resetPagination() {
    currentPage = 1;
}

// 从localStorage加载缓存
function loadCache() {
    try {
        const cached = localStorage.getItem('fileCache');
        if (cached) {
            fileCache = JSON.parse(cached);
        }
    } catch (e) {
        console.error('缓存加载失败:', e);
        fileCache = {};
    }
}

// 更新空间使用情况
function updateSpaceUsage(data) {
    usedSize = data.usedSize || 0;
    maxSize = data.maxSize || 2 * 1024 * 1024 * 1024;
    globalUsedSize = data.globalUsedSize || 0;
    globalMaxSize = data.globalMaxSize || 10 * 1024 * 1024 * 1024;
    publicUsedSize = data.publicUsedSize || 0;
    publicMaxSize = data.publicMaxSize || 5 * 1024 * 1024 * 1024;

    // 更新个人空间
    const personalSpaceFill = document.getElementById('personal-space-fill');
    const personalSpaceValue = document.getElementById('personal-space-value');
    const personalPercentage = (usedSize / maxSize) * 100;
    personalSpaceFill.style.width = `${personalPercentage}%`;
    personalSpaceValue.textContent = `${formatFileSize(usedSize)} / ${formatFileSize(maxSize)}`;

    // 更新公共空间
    const publicSpaceFill = document.getElementById('public-space-fill');
    const publicSpaceValue = document.getElementById('public-space-value');
    const publicPercentage = (publicUsedSize / publicMaxSize) * 100;
    publicSpaceFill.style.width = `${publicPercentage}%`;
    publicSpaceValue.textContent = `${formatFileSize(publicUsedSize)} / ${formatFileSize(publicMaxSize)}`;

    // 更新全局空间
    const globalSpaceFill = document.getElementById('global-space-fill');
    const globalSpaceValue = document.getElementById('global-space-value');
    const globalPercentage = (globalUsedSize / globalMaxSize) * 100;
    globalSpaceFill.style.width = `${globalPercentage}%`;
    globalSpaceValue.textContent = `${formatFileSize(globalUsedSize)} / ${formatFileSize(globalMaxSize)}`;
}

// 初始化视图切换
function initViewToggle() {
    const viewToggleButtons = document.querySelectorAll('.view-toggle button');
    viewToggleButtons.forEach(button => {
        button.addEventListener('click', () => {
            viewToggleButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            displayView = button.dataset.view;
            renderFiles();
        });
    });
}

// 初始化侧边栏导航
function initSidebarNavigation() {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            sidebarLinks.forEach(lnk => lnk.classList.remove('active'));
            link.classList.add('active');
            currentView = link.dataset.view;
            document.getElementById('section-title').textContent = currentView === 'personal' ? '个人文件' : '公共空间';
            renderFiles();
        });
    });
}

// 排序文件
function sortFiles() {
    const sortValue = document.getElementById('sort-select').value;
    const currentFiles = currentView === 'personal' ? files : publicFiles;
    
    switch (sortValue) {
        case 'date-desc':
            currentFiles.sort((a, b) => b.date - a.date);
            break;
        case 'date-asc':
            currentFiles.sort((a, b) => a.date - b.date);
            break;
        case 'size-desc':
            currentFiles.sort((a, b) => b.size - a.size);
            break;
        case 'size-asc':
            currentFiles.sort((a, b) => a.size - b.size);
            break;
        case 'name-asc':
            currentFiles.sort((a, b) => a.name.localeCompare(b.name));
            break;
        case 'name-desc':
            currentFiles.sort((a, b) => b.name.localeCompare(a.name));
            break;
    }
    
    renderFiles();
}

// 搜索文件
function searchFiles() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const currentFiles = currentView === 'personal' ? files : publicFiles;
    const filteredFiles = currentFiles.filter(file => 
        file.name.toLowerCase().includes(searchTerm)
    );
    renderFiles(filteredFiles);
}

// 渲染文件
function renderFiles(filteredFiles = null) {
    const currentFiles = filteredFiles || (currentView === 'personal' ? files : publicFiles);
    
    // 计算分页
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = startIndex + pageSize;
    const paginatedFiles = currentFiles.slice(startIndex, endIndex);
    
    if (displayView === 'list') {
        renderListView(paginatedFiles, currentFiles.length);
    } else {
        renderGridView(paginatedFiles, currentFiles.length);
    }
}

// 渲染列表视图
function renderListView(files, totalFiles = 0) {
    fileListView.style.display = 'block';
    fileGridView.style.display = 'none';
    
    if (files.length === 0) {
        fileList.innerHTML = `
            <li class="empty-state">
                <i class="fas fa-folder"></i>
                <h3>暂无文件</h3>
                <p>上传文件开始使用网盘</p>
            </li>
        `;
        return;
    }

    fileList.innerHTML = '';
    
    files.forEach(file => {
        const li = document.createElement('li');
        li.className = 'file-item';
        li.dataset.filename = file.filename;
        
        // 根据文件扩展名设置图标
        let fileIcon = 'fas fa-file';
        if (file.name.endsWith('.jpg') || file.name.endsWith('.jpeg') || file.name.endsWith('.png') || file.name.endsWith('.gif')) {
            fileIcon = 'fas fa-file-image';
        } else if (file.name.endsWith('.pdf')) {
            fileIcon = 'fas fa-file-pdf';
        } else if (file.name.endsWith('.doc') || file.name.endsWith('.docx')) {
            fileIcon = 'fas fa-file-word';
        } else if (file.name.endsWith('.xls') || file.name.endsWith('.xlsx')) {
            fileIcon = 'fas fa-file-excel';
        } else if (file.name.endsWith('.ppt') || file.name.endsWith('.pptx')) {
            fileIcon = 'fas fa-file-powerpoint';
        } else if (file.name.endsWith('.zip') || file.name.endsWith('.rar') || file.name.endsWith('.7z')) {
            fileIcon = 'fas fa-file-archive';
        } else if (file.name.endsWith('.mp3') || file.name.endsWith('.wav') || file.name.endsWith('.flac')) {
            fileIcon = 'fas fa-file-audio';
        } else if (file.name.endsWith('.mp4') || file.name.endsWith('.avi') || file.name.endsWith('.mov')) {
            fileIcon = 'fas fa-file-video';
        } else if (file.name.endsWith('.txt') || file.name.endsWith('.md')) {
            fileIcon = 'fas fa-file-alt';
        } else if (file.name.endsWith('.js') || file.name.endsWith('.css') || file.name.endsWith('.html') || file.name.endsWith('.php')) {
            fileIcon = 'fas fa-file-code';
        }

        // 构建下载链接
        let downloadUrl = `download.php?file=${encodeURIComponent(file.filename)}&password=${encodeURIComponent(currentPassword)}`;
        if (file.userDir) {
            downloadUrl += `&userDir=${encodeURIComponent(file.userDir)}`;
        }

        li.innerHTML = `
            <input type="checkbox" class="file-checkbox" onchange="toggleFileSelection('${file.filename}')">
            <div class="file-info">
                <div class="file-icon"><i class="${fileIcon}"></i></div>
                <div class="file-details">
                    <h3>${file.name}</h3>
                    <div class="file-meta">
                        <span>${formatFileSize(file.size)}</span>
                        <span>${formatDate(file.date)}</span>
                        ${file.userDir ? `<span>用户: ${file.userDir.substring(0, 8)}...</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="file-actions">
                <button class="btn btn-sm" onclick="previewFile('${file.filename}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-eye"></i> 预览</button>
                <button class="btn btn-sm" onclick="shareFile('${file.filename}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-share-alt"></i> 分享</button>
                <button class="btn btn-sm" onclick="renameFile('${file.filename}', '${file.name}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-edit"></i> 重命名</button>
                <a href="${downloadUrl}" class="btn btn-sm" download="${file.name}"><i class="fas fa-download"></i> 下载</a>
                <button class="btn btn-sm btn-danger" onclick="deleteFile('${file.filename}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-trash"></i> 删除</button>
            </div>
        `;
        
        fileList.appendChild(li);
    });
    
    // 添加分页信息和加载更多按钮
    const totalPages = Math.ceil(totalFiles / pageSize);
    if (currentPage < totalPages) {
        const loadMoreLi = document.createElement('li');
        loadMoreLi.className = 'load-more';
        loadMoreLi.innerHTML = `
            <button class="btn" onclick="loadMoreFiles()">
                <i class="fas fa-spinner"></i> 加载更多
            </button>
        `;
        fileList.appendChild(loadMoreLi);
    }
    
    // 添加分页信息
    const paginationInfo = document.createElement('li');
    paginationInfo.className = 'pagination-info';
    paginationInfo.innerHTML = `
        <p>显示 ${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, totalFiles)} 共 ${totalFiles} 个文件</p>
    `;
    fileList.appendChild(paginationInfo);
}

// 渲染图标视图
function renderGridView(files, totalFiles = 0) {
    fileListView.style.display = 'none';
    fileGridView.style.display = 'grid';
    
    if (files.length === 0) {
        fileGridView.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-folder"></i>
                <h3>暂无文件</h3>
                <p>上传文件开始使用网盘</p>
            </div>
        `;
        return;
    }

    fileGridView.innerHTML = '';
    
    files.forEach(file => {
        const div = document.createElement('div');
        div.className = 'file-grid-item';
        div.dataset.filename = file.filename;
        
        // 根据文件扩展名设置图标
        let fileIcon = 'fas fa-file';
        if (file.name.endsWith('.jpg') || file.name.endsWith('.jpeg') || file.name.endsWith('.png') || file.name.endsWith('.gif')) {
            fileIcon = 'fas fa-file-image';
        } else if (file.name.endsWith('.pdf')) {
            fileIcon = 'fas fa-file-pdf';
        } else if (file.name.endsWith('.doc') || file.name.endsWith('.docx')) {
            fileIcon = 'fas fa-file-word';
        } else if (file.name.endsWith('.xls') || file.name.endsWith('.xlsx')) {
            fileIcon = 'fas fa-file-excel';
        } else if (file.name.endsWith('.ppt') || file.name.endsWith('.pptx')) {
            fileIcon = 'fas fa-file-powerpoint';
        } else if (file.name.endsWith('.zip') || file.name.endsWith('.rar') || file.name.endsWith('.7z')) {
            fileIcon = 'fas fa-file-archive';
        } else if (file.name.endsWith('.mp3') || file.name.endsWith('.wav') || file.name.endsWith('.flac')) {
            fileIcon = 'fas fa-file-audio';
        } else if (file.name.endsWith('.mp4') || file.name.endsWith('.avi') || file.name.endsWith('.mov')) {
            fileIcon = 'fas fa-file-video';
        } else if (file.name.endsWith('.txt') || file.name.endsWith('.md')) {
            fileIcon = 'fas fa-file-alt';
        } else if (file.name.endsWith('.js') || file.name.endsWith('.css') || file.name.endsWith('.html') || file.name.endsWith('.php')) {
            fileIcon = 'fas fa-file-code';
        }

        // 构建下载链接
        let downloadUrl = `download.php?file=${encodeURIComponent(file.filename)}&password=${encodeURIComponent(currentPassword)}`;
        if (file.userDir) {
            downloadUrl += `&userDir=${encodeURIComponent(file.userDir)}`;
        }

        div.innerHTML = `
            <div class="file-grid-actions">
                <input type="checkbox" class="file-checkbox" onchange="toggleFileSelection('${file.filename}')">
                <button class="btn btn-sm" onclick="previewFile('${file.filename}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm" onclick="shareFile('${file.filename}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-share-alt"></i>
                </button>
                <button class="btn btn-sm" onclick="renameFile('${file.filename}', '${file.name}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-edit"></i>
                </button>
                <a href="${downloadUrl}" class="btn btn-sm" download="${file.name}">
                    <i class="fas fa-download"></i>
                </a>
                <button class="btn btn-sm btn-danger" onclick="deleteFile('${file.filename}', ${file.userDir ? `'${file.userDir}'` : 'null'})"><i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="file-grid-icon"><i class="${fileIcon}"></i></div>
            <div class="file-grid-name">${file.name}</div>
            <div class="file-grid-meta">
                <div>${formatFileSize(file.size)}</div>
                <div>${formatDate(file.date)}</div>
            </div>
        `;
        
        fileGridView.appendChild(div);
    });
    
    // 添加分页信息和加载更多按钮
    const totalPages = Math.ceil(totalFiles / pageSize);
    if (currentPage < totalPages) {
        const loadMoreDiv = document.createElement('div');
        loadMoreDiv.className = 'load-more';
        loadMoreDiv.innerHTML = `
            <button class="btn" onclick="loadMoreFiles()">
                <i class="fas fa-spinner"></i> 加载更多
            </button>
        `;
        fileGridView.appendChild(loadMoreDiv);
    }
    
    // 添加分页信息
    const paginationInfo = document.createElement('div');
    paginationInfo.className = 'pagination-info';
    paginationInfo.innerHTML = `
        <p>显示 ${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, totalFiles)} 共 ${totalFiles} 个文件</p>
    `;
    fileGridView.appendChild(paginationInfo);
}

// 切换文件选择
function toggleFileSelection(filename) {
    const index = selectedFiles.indexOf(filename);
    if (index === -1) {
        selectedFiles.push(filename);
    } else {
        selectedFiles.splice(index, 1);
    }
}

// 全选文件
function selectAllFiles() {
    const currentFiles = currentView === 'personal' ? files : publicFiles;
    selectedFiles = currentFiles.map(file => file.filename);
    // 更新复选框状态
    document.querySelectorAll('.file-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

// 删除选中文件
function deleteSelectedFiles() {
    if (selectedFiles.length === 0) {
        alert('请先选择要删除的文件');
        return;
    }
    
    if (confirm(`确定要删除选中的 ${selectedFiles.length} 个文件吗？`)) {
        // 批量删除文件
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('password', currentPassword);
        
        // 添加所有选中的文件名
        selectedFiles.forEach(filename => {
            formData.append('files[]', filename);
        });
        
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                refreshFileList();
                showToast('文件删除成功');
                selectedFiles = [];
            } else {
                alert('删除失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('删除失败:', error);
            alert('删除失败，请重试');
        });
    }
}

// 删除文件
function deleteFile(filename, userDir = null) {
    if (confirm('确定要删除这个文件吗？')) {
        let body = `action=delete&file=${filename}&password=${encodeURIComponent(currentPassword)}`;
        if (isAdmin && userDir) {
            body += `&userDir=${userDir}`;
        }
        
        fetch('upload.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 清除缓存
                clearCache();
                // 重置分页
                resetPagination();
                refreshFileList();
                // 更新空间使用情况
                if (data.usedSize) {
                    updateSpaceUsage(data);
                }
                // 显示成功提示
                showToast('文件删除成功');
            } else {
                alert('删除失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('删除失败:', error);
            alert('删除失败，请重试');
        });
    }
}

// 预览文件
function previewFile(filename, userDir = null) {
    // 检查是否为可预览的图片文件
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    const fileExtension = filename.split('.').pop().toLowerCase();
    
    // 构建预览链接
    let previewUrl = `preview.php?file=${encodeURIComponent(filename)}&password=${encodeURIComponent(currentPassword)}`;
    if (userDir) {
        previewUrl += `&userDir=${encodeURIComponent(userDir)}`;
    }
    
    // 检查是否为图片文件
    if (imageExtensions.includes(fileExtension)) {
        // 显示图片预览
        const preview = document.createElement('div');
        preview.className = 'image-preview';
        preview.innerHTML = `
            <button class="image-preview-close" onclick="this.parentElement.remove()">&times;</button>
            <div class="image-preview-info">
                <span>${filename}</span>
                <span id="image-dimensions">加载中...</span>
            </div>
            <div class="image-preview-content">
                <img src="${previewUrl}" alt="预览图片" onload="updateImageInfo(this)">
                <div class="image-preview-actions">
                    <button onclick="rotateImage(this, 90)" title="顺时针旋转"><i class="fas fa-undo"></i></button>
                    <button onclick="rotateImage(this, -90)" title="逆时针旋转"><i class="fas fa-undo-alt"></i></button>
                    <button onclick="zoomImage(this, 1.2)" title="放大"><i class="fas fa-search-plus"></i></button>
                    <button onclick="zoomImage(this, 0.8)" title="缩小"><i class="fas fa-search-minus"></i></button>
                    <button onclick="resetImage(this)" title="重置"><i class="fas fa-sync-alt"></i></button>
                    <button onclick="toggleFullscreen(this)" title="全屏"><i class="fas fa-expand"></i></button>
                </div>
            </div>
            <div class="image-preview-help">
                <p>拖动图片可移动，滚轮可缩放，ESC键关闭</p>
            </div>
        `;
        document.body.appendChild(preview);
        
        // 添加拖拽功能
        const img = preview.querySelector('img');
        let isDragging = false;
        let startX, startY, offsetX, offsetY;
        
        img.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            const currentTransform = img.style.transform || 'none';
            const match = currentTransform.match(/translate\((-?\d+)px,\s*(-?\d+)px\)/);
            if (match) {
                offsetX = parseInt(match[1]);
                offsetY = parseInt(match[2]);
            } else {
                offsetX = 0;
                offsetY = 0;
            }
        });
        
        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                const currentTransform = img.style.transform || 'none';
                const rotateMatch = currentTransform.match(/rotate\(([-+]?\d+)deg\)/);
                const scaleMatch = currentTransform.match(/scale\(([0-9.]+)\)/);
                const rotate = rotateMatch ? rotateMatch[1] : 0;
                const scale = scaleMatch ? scaleMatch[1] : 1;
                img.style.transform = `translate(${offsetX + dx}px, ${offsetY + dy}px) rotate(${rotate}deg) scale(${scale})`;
            }
        });
        
        document.addEventListener('mouseup', () => {
            isDragging = false;
        });
        
        // 添加滚轮缩放功能
        img.addEventListener('wheel', (e) => {
            e.preventDefault();
            const scale = e.deltaY > 0 ? 0.9 : 1.1;
            zoomImage(null, scale, img);
        });
        
        // 添加键盘快捷键
        document.addEventListener('keydown', (e) => {
            if (preview.parentNode) {
                switch (e.key) {
                    case 'Escape':
                        preview.remove();
                        break;
                    case 'ArrowLeft':
                        rotateImage(null, -90, img);
                        break;
                    case 'ArrowRight':
                        rotateImage(null, 90, img);
                        break;
                    case '+' :
                        zoomImage(null, 1.2, img);
                        break;
                    case '-' :
                        zoomImage(null, 0.8, img);
                        break;
                    case '0':
                        resetImage(null, img);
                        break;
                }
            }
        });
    } else {
        // 非图片文件，提示用户并直接下载
        showToast('该文件类型不支持在线预览，已开始下载');
        // 延迟一下让用户看到提示
        setTimeout(() => {
            window.open(previewUrl, '_blank');
        }, 500);
    }
}

// 更新图片信息
function updateImageInfo(img) {
    const dimensions = document.getElementById('image-dimensions');
    if (dimensions) {
        dimensions.textContent = `${img.naturalWidth} × ${img.naturalHeight}`;
    }
}

// 旋转图片
function rotateImage(button, angle, img) {
    if (!img) {
        img = button.closest('.image-preview-content').querySelector('img');
    }
    const currentTransform = img.style.transform || 'none';
    const match = currentTransform.match(/rotate\(([-+]?\d+)deg\)/);
    let currentAngle = 0;
    if (match) {
        currentAngle = parseInt(match[1]);
    }
    const newAngle = currentAngle + angle;
    const translateMatch = currentTransform.match(/translate\((-?\d+)px,\s*(-?\d+)px\)/);
    const scaleMatch = currentTransform.match(/scale\(([0-9.]+)\)/);
    const translate = translateMatch ? `translate(${translateMatch[1]}px, ${translateMatch[2]}px)` : '';
    const scale = scaleMatch ? `scale(${scaleMatch[1]})` : '';
    img.style.transform = `${translate} rotate(${newAngle}deg) ${scale}`;
}

// 缩放图片
function zoomImage(button, scale, img) {
    if (!img) {
        img = button.closest('.image-preview-content').querySelector('img');
    }
    const currentTransform = img.style.transform || 'none';
    const match = currentTransform.match(/scale\(([0-9.]+)\)/);
    let currentScale = 1;
    if (match) {
        currentScale = parseFloat(match[1]);
    }
    const newScale = currentScale * scale;
    const translateMatch = currentTransform.match(/translate\((-?\d+)px,\s*(-?\d+)px\)/);
    const rotateMatch = currentTransform.match(/rotate\(([-+]?\d+)deg\)/);
    const translate = translateMatch ? `translate(${translateMatch[1]}px, ${translateMatch[2]}px)` : '';
    const rotate = rotateMatch ? `rotate(${rotateMatch[1]}deg)` : '';
    img.style.transform = `${translate} ${rotate} scale(${newScale})`;
}

// 重置图片
function resetImage(button, img) {
    if (!img) {
        img = button.closest('.image-preview-content').querySelector('img');
    }
    img.style.transform = 'none';
}

// 切换全屏
function toggleFullscreen(button) {
    const preview = button.closest('.image-preview');
    if (!document.fullscreenElement) {
        if (preview.requestFullscreen) {
            preview.requestFullscreen();
        } else if (preview.mozRequestFullScreen) {
            preview.mozRequestFullScreen();
        } else if (preview.webkitRequestFullscreen) {
            preview.webkitRequestFullscreen();
        } else if (preview.msRequestFullscreen) {
            preview.msRequestFullscreen();
        }
        button.innerHTML = '<i class="fas fa-compress"></i>';
        button.title = '退出全屏';
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
        button.innerHTML = '<i class="fas fa-expand"></i>';
        button.title = '全屏';
    }
}



// 分享文件
function shareFile(filename, userDir = null) {
    // 构建分享链接
    let shareUrl = `share.php?file=${encodeURIComponent(filename)}`;
    if (userDir) {
        shareUrl += `&userDir=${encodeURIComponent(userDir)}`;
    }
    
    // 复制到剪贴板
    navigator.clipboard.writeText(shareUrl)
        .then(() => {
            showToast('分享链接已复制到剪贴板');
        })
        .catch(err => {
            console.error('复制失败:', err);
            alert('复制失败，请手动复制链接');
        });
}

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// 格式化日期
function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    return date.toLocaleString('zh-CN');
}

// 显示提示
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    // 3秒后自动移除
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// 初始化主题切换
function initThemeToggle() {
    const themeToggle = document.createElement('button');
    themeToggle.className = 'theme-toggle';
    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    themeToggle.title = '切换主题';
    document.body.appendChild(themeToggle);
    
    // 检查本地存储中的主题设置
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    }
    
    // 主题切换事件
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
}

// 初始化网络状态检测
function initNetworkStatus() {
    const networkStatus = document.getElementById('network-status');
    const pingValue = document.getElementById('ping-value');
    
    // 网络状态检测现在合并到文件列表请求中
    // 初始状态
    pingValue.textContent = '-- ms';
    networkStatus.className = 'network-status';
}

// 检查网络状态并更新UI
function updateNetworkStatus(startTime) {
    const networkStatus = document.getElementById('network-status');
    const pingValue = document.getElementById('ping-value');
    const ping = Date.now() - startTime;
    pingValue.textContent = `${ping} ms`;
    
    // 更新状态样式
    networkStatus.className = 'network-status';
    if (ping < 100) {
        networkStatus.classList.add('good');
    } else if (ping < 300) {
        networkStatus.classList.add('warning');
    } else {
        networkStatus.classList.add('bad');
    }
}

// 初始化应用
window.addEventListener('DOMContentLoaded', init);