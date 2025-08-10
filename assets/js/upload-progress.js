// assets/js/upload-progress.js - แก้ไขใหม่ทั้งหมด
class UploadProgress {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        this.options = {
            progressBarId: 'uploadProgress',
            progressBarSelector: '.progress-bar',
            statusSelector: '#uploadStatus',
            submitButtonId: 'uploadBtn',
            ajaxUrl: '../ajax/upload_file.php',
            onStart: null,
            onProgress: null,
            onComplete: null,
            onError: null,
            ...options
        };
        
        this.uploadId = null;
        this.isUploading = false;
        
        this.init();
    }
    
    init() {
        if (!this.form) {
            console.warn('Upload form not found:', this.form);
            return;
        }
        
        // Don't prevent default here - let dashboard.php handle form validation first
        this.form.addEventListener('submit', (e) => {
            // Only handle if this is meant to be an AJAX upload
            if (this.form.dataset.ajaxUpload === 'true') {
                e.preventDefault();
                this.uploadFile();
            }
        });
        
        // Add file input change handler
        const fileInput = this.form.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                this.handleFileSelect(e);
            });
        }
    }
    
    handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            const validation = this.validateFile(file);
            const fileInput = event.target;
            
            // Remove previous validation classes
            fileInput.classList.remove('is-valid', 'is-invalid');
            
            if (validation.isValid) {
                fileInput.classList.add('is-valid');
                this.updateFileInfo(file, true);
            } else {
                fileInput.classList.add('is-invalid');
                this.updateFileInfo(file, false, validation.message);
                this.showToast('error', validation.message);
            }
        }
    }
    
    updateFileInfo(file, isValid, errorMessage = '') {
        const fileInput = this.form.querySelector('input[type="file"]');
        const helpText = fileInput ? fileInput.nextElementSibling : null;
        
        if (helpText && helpText.classList.contains('form-text')) {
            if (isValid) {
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                helpText.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>ไฟล์: ${file.name} (${fileSize} MB)`;
                helpText.className = 'form-text text-success';
            } else {
                helpText.innerHTML = `<i class="fas fa-exclamation-triangle text-danger me-1"></i>${errorMessage}`;
                helpText.className = 'form-text text-danger';
            }
        }
    }
    
    uploadFile() {
        if (this.isUploading) {
            this.showToast('warning', 'กำลังอัพโหลดไฟล์อยู่ กรุณารอสักครู่');
            return;
        }
        
        const formData = new FormData(this.form);
        const file = formData.get('upload_file');
        
        if (!file || file.size === 0) {
            this.showToast('error', 'กรุณาเลือกไฟล์');
            return;
        }
        
        // Validate file
        const validation = this.validateFile(file);
        if (!validation.isValid) {
            this.showToast('error', validation.message);
            return;
        }
        
        // Validate form fields
        if (!this.validateFormFields()) {
            return;
        }
        
        this.isUploading = true;
        this.uploadId = this.generateUploadId();
        
        this.showProgress();
        this.disableSubmitButton();
        
        const xhr = new XMLHttpRequest();
        
        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                this.updateProgress(percentComplete, e.loaded, e.total);
            }
        });
        
        // Handle completion
        xhr.addEventListener('load', () => {
            this.isUploading = false;
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    this.handleResponse(response);
                } catch (error) {
                    console.error('JSON parse error:', error);
                    this.showError('เกิดข้อผิดพลาดในการประมวลผลข้อมูล');
                }
            } else {
                this.showError(`เกิดข้อผิดพลาดของเซิร์ฟเวอร์ (${xhr.status})`);
            }
        });
        
        // Handle errors
        xhr.addEventListener('error', () => {
            this.isUploading = false;
            this.showError('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        });
        
        // Handle timeout
        xhr.addEventListener('timeout', () => {
            this.isUploading = false;
            this.showError('การอัพโหลดใช้เวลานานเกินไป กรุณาลองใหม่อีกครั้ง');
        });
        
        // Set timeout (15 minutes for large files)
        xhr.timeout = 15 * 60 * 1000;
        
        // Add upload ID to form data
        formData.append('upload_id', this.uploadId);
        
        // Send the request
        xhr.open('POST', this.options.ajaxUrl);
        xhr.send(formData);
        
        if (this.options.onStart) {
            this.options.onStart();
        }
    }
    
    validateFile(file) {
        const maxSize = 536870912; // 512MB
        const allowedExtensions = ['.zip', '.7z', '.rar'];
        
        // Check file size
        if (file.size > maxSize) {
            return {
                isValid: false,
                message: 'ไฟล์มีขนาดใหญ่เกิน 512MB'
            };
        }
        
        if (file.size === 0) {
            return {
                isValid: false,
                message: 'ไฟล์ว่างเปล่า'
            };
        }
        
        // Check file extension
        const fileName = file.name.toLowerCase();
        const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
        
        if (!hasValidExtension) {
            return {
                isValid: false,
                message: 'นามสกุลไฟล์ไม่ได้รับอนุญาต (อนุญาตเฉพาะ .zip, .7z, .rar)'
            };
        }
        
        // Additional file name validation
        if (fileName.length > 255) {
            return {
                isValid: false,
                message: 'ชื่อไฟล์ยาวเกินไป (สูงสุด 255 ตัวอักษร)'
            };
        }
        
        // Check for dangerous characters in filename
        const dangerousChars = /[<>:"/\\|?*\x00-\x1f]/;
        if (dangerousChars.test(file.name)) {
            return {
                isValid: false,
                message: 'ชื่อไฟล์มีตัวอักษรที่ไม่ได้รับอนุญาต'
            };
        }
        
        return { isValid: true };
    }
    
    validateFormFields() {
        let isValid = true;
        const requiredFields = this.form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            field.classList.remove('is-invalid');
            
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        // Validate upload date
        const uploadDateField = this.form.querySelector('[name="upload_date"]');
        if (uploadDateField && uploadDateField.value) {
            const selectedDate = new Date(uploadDateField.value);
            const today = new Date();
            today.setHours(23, 59, 59, 999);
            
            if (selectedDate > today) {
                uploadDateField.classList.add('is-invalid');
                this.showToast('error', 'วันที่ส่งข้อมูลไม่สามารถเป็นวันในอนาคตได้');
                isValid = false;
            }
        }
        
        if (!isValid) {
            this.showToast('error', 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
            
            // Focus on first invalid field
            const firstInvalid = this.form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        return isValid;
    }
    
    showProgress() {
        const progressElement = document.getElementById(this.options.progressBarId);
        if (progressElement) {
            progressElement.style.display = 'block';
        }
        
        // Show loading overlay if it exists
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'block';
        }
    }
    
    updateProgress(percent, loaded, total) {
        // Update main progress bar
        const progressBar = document.querySelector(this.options.progressBarSelector);
        if (progressBar) {
            progressBar.style.width = percent + '%';
            progressBar.setAttribute('aria-valuenow', percent);
            progressBar.textContent = percent + '%';
        }
        
        // Update overlay progress bar
        const overlayProgressBar = document.getElementById('uploadProgressBar');
        if (overlayProgressBar) {
            overlayProgressBar.style.width = percent + '%';
        }
        
        // Update status text
        const statusElement = document.querySelector(this.options.statusSelector);
        const overlayStatus = document.getElementById('uploadStatus');
        
        const loadedMB = (loaded / (1024 * 1024)).toFixed(1);
        const totalMB = (total / (1024 * 1024)).toFixed(1);
        const statusText = `กำลังอัพโหลด... ${percent}% (${loadedMB}MB / ${totalMB}MB)`;
        
        if (statusElement) {
            statusElement.textContent = statusText;
        }
        
        if (overlayStatus) {
            overlayStatus.textContent = statusText;
        }
        
        if (this.options.onProgress) {
            this.options.onProgress(percent, loaded, total);
        }
    }
    
    handleResponse(response) {
        if (response.success) {
            this.showSuccess(response.message);
            this.resetForm();
            
            // Refresh page after 2 seconds to show new file
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            this.showError(response.message);
        }
        
        this.enableSubmitButton();
        this.hideProgress();
        
        if (this.options.onComplete) {
            this.options.onComplete(response);
        }
    }
    
    showError(message) {
        this.showToast('error', message);
        this.enableSubmitButton();
        this.hideProgress();
        
        if (this.options.onError) {
            this.options.onError(message);
        }
    }
    
    showSuccess(message) {
        this.showToast('success', message);
    }
    
    showToast(type, message) {
        // Try to use existing toast function from dashboard
        if (typeof window.showToast === 'function') {
            window.showToast(type, message);
            return;
        }
        
        // Fallback to simple implementation
        const toastId = 'toast_' + Date.now();
        const iconClass = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-triangle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        }[type] || 'fa-info-circle';
        
        const bgClass = {
            'success': 'bg-success',
            'error': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-info';
        
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" style="min-width: 300px;">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas ${iconClass} me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '11';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        // Show toast
        const toastElement = document.getElementById(toastId);
        if (toastElement && typeof bootstrap !== 'undefined') {
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Remove from DOM after hidden
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        } else {
            // Fallback for when Bootstrap is not available
            setTimeout(() => {
                if (toastElement) {
                    toastElement.remove();
                }
            }, 5000);
        }
    }
    
    disableSubmitButton() {
        const submitButton = document.getElementById(this.options.submitButtonId);
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัพโหลด...';
        }
    }
    
    enableSubmitButton() {
        const submitButton = document.getElementById(this.options.submitButtonId);
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-upload me-2"></i>อัพโหลดไฟล์';
        }
    }
    
    hideProgress() {
        // Hide progress elements after delay
        setTimeout(() => {
            const progressElement = document.getElementById(this.options.progressBarId);
            if (progressElement) {
                progressElement.style.display = 'none';
            }
            
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
        }, 2000);
    }
    
    resetForm() {
        // Reset form fields
        this.form.reset();
        
        // Remove validation classes
        const formControls = this.form.querySelectorAll('.form-control, .form-select');
        formControls.forEach(control => {
            control.classList.remove('is-valid', 'is-invalid');
        });
        
        // Reset file input display text
        const fileInput = this.form.querySelector('input[type="file"]');
        if (fileInput) {
            const helpText = fileInput.nextElementSibling;
            if (helpText && helpText.classList.contains('form-text')) {
                helpText.innerHTML = '<i class="fas fa-info-circle me-1"></i>อนุญาตเฉพาะไฟล์ .zip, .7z, .rar ขนาดไม่เกิน 512MB';
                helpText.className = 'form-text';
            }
        }
        
        // Reset progress bars
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            bar.style.width = '0%';
            bar.setAttribute('aria-valuenow', '0');
            bar.textContent = '';
        });
    }
    
    generateUploadId() {
        return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    // Public method to manually trigger upload (for testing or external use)
    triggerUpload() {
        if (!this.isUploading) {
            this.uploadFile();
        }
    }
    
    // Public method to cancel upload
    cancelUpload() {
        if (this.isUploading) {
            this.isUploading = false;
            this.enableSubmitButton();
            this.hideProgress();
            this.showToast('info', 'การอัพโหลดถูกยกเลิก');
        }
    }
    
    // Public method to destroy the instance
    destroy() {
        if (this.form) {
            this.form.removeEventListener('submit', this.uploadFile);
        }
        
        const fileInput = this.form?.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.removeEventListener('change', this.handleFileSelect);
        }
    }
}

// Global instance for external access
window.UploadProgress = UploadProgress;

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        // Check if we want AJAX upload (can be controlled via data attribute)
        if (uploadForm.dataset.ajaxUpload !== 'false') {
            window.uploadProgressInstance = new UploadProgress('uploadForm');
        }
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UploadProgress;
}
