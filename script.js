new Vue({
    el: '#app',
    data: {
        selectedFiles: [],
        files: [],
        message: null
    },
    mounted() {
        this.fetchFiles();
    },
    methods: {
        handleFileChange(event) {
            this.selectedFiles = Array.from(event.target.files);
        },
        async uploadFiles() {
            if (this.selectedFiles.length === 0) return;
            
            try {
                const formData = new FormData();
                this.selectedFiles.forEach(file => {
                    formData.append('files', file);
                });
                
                const response = await fetch('http://localhost:8080/upload', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    this.showMessage('文件上传成功', 'success');
                    this.selectedFiles = [];
                    this.fetchFiles();
                } else {
                    this.showMessage('文件上传失败', 'error');
                }
            } catch (error) {
                this.showMessage('上传出错: ' + error.message, 'error');
            }
        },
        async fetchFiles() {
            try {
                const response = await fetch('http://localhost:8080/files');
                if (response.ok) {
                    this.files = await response.json();
                } else {
                    this.showMessage('获取文件列表失败', 'error');
                }
            } catch (error) {
                this.showMessage('获取文件列表出错: ' + error.message, 'error');
            }
        },
        downloadFile(filename) {
            window.open(`http://localhost:8080/download/${filename}`, '_blank');
        },
        async deleteFile(filename) {
            if (!confirm('确定要删除这个文件吗？')) return;
            
            try {
                const response = await fetch(`http://localhost:8080/delete/${filename}`, {
                    method: 'DELETE'
                });
                
                if (response.ok) {
                    this.showMessage('文件删除成功', 'success');
                    this.fetchFiles();
                } else {
                    this.showMessage('文件删除失败', 'error');
                }
            } catch (error) {
                this.showMessage('删除出错: ' + error.message, 'error');
            }
        },
        formatSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        showMessage(text, type) {
            this.message = { text, type };
            setTimeout(() => {
                this.message = null;
            }, 3000);
        }
    }
});