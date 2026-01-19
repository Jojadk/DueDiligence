/**
 * Drag & Drop Upload System
 * - Multiple file upload
 * - Progress bars with percentage
 * - Project folder targeting
 * - File validation
 * - Preview thumbnails
 */

class UploadSystem {
    constructor() {
        this.uploads = new Map();
        this.uploadId = 0;
        this.maxFileSize = 50 * 1024 * 1024; // 50MB
        this.allowedTypes = {
            image: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            document: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                      'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            archive: ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed']
        };
    }

    /**
     * Create upload zone
     */
    createUploadZone(containerId, options = {}) {
        const {
            uploadUrl = '/api.php?module=upload&action=upload',
            targetFolder = 'uploads',
            projectId = null,
            customerId = null,
            buildingId = null,
            allowedFileTypes = ['image', 'document', 'archive'],
            multiple = true,
            maxFiles = 10,
            onComplete = null,
            onError = null
        } = options;

        const container = document.getElementById(containerId);
        if (!container) {
            if (window.logError) {
                window.logError(new Error('Upload container not found'), { containerId });
            }
            return null;
        }

        const zoneId = `upload-zone-${++this.uploadId}`;
        const inputId = `upload-input-${this.uploadId}`;
        const previewId = `upload-preview-${this.uploadId}`;

        container.innerHTML = `
            <div class="upload-zone" id="${zoneId}">
                <div class="upload-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                </div>
                <h3>Upload filer</h3>
                <p>Træk og slip filer her, eller klik for at vælge</p>
                <p class="upload-hint">Maksimal filstørrelse: ${this.formatFileSize(this.maxFileSize)}</p>
                <input
                    type="file"
                    id="${inputId}"
                    style="display: none;"
                    ${multiple ? 'multiple' : ''}
                    accept="${this.getAcceptString(allowedFileTypes)}"
                >
            </div>
            <div class="upload-preview" id="${previewId}"></div>
        `;

        const zone = document.getElementById(zoneId);
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);

        if (!zone || !input || !preview) {
            if (window.logError) {
                window.logError(new Error('Upload zone elements not created'));
            }
            return null;
        }

        // Click to select
        zone.addEventListener('click', () => input.click());

        // File input change
        input.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                this.handleFiles(Array.from(e.target.files), {
                    uploadUrl,
                    targetFolder,
                    projectId,
                    customerId,
                    buildingId,
                    allowedFileTypes,
                    maxFiles,
                    preview,
                    onComplete,
                    onError
                });
            }
        });

        // Drag & drop
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');

            const files = Array.from(e.dataTransfer.files);
            if (files.length > 0) {
                this.handleFiles(files, {
                    uploadUrl,
                    targetFolder,
                    projectId,
                    customerId,
                    buildingId,
                    allowedFileTypes,
                    maxFiles,
                    preview,
                    onComplete,
                    onError
                });
            }
        });

        return {
            zoneId,
            inputId,
            previewId
        };
    }

    /**
     * Handle file upload
     */
    handleFiles(files, options) {
        const {
            uploadUrl,
            targetFolder,
            projectId,
            customerId,
            buildingId,
            allowedFileTypes,
            maxFiles,
            preview,
            onComplete,
            onError
        } = options;

        // Validate file count
        if (files.length > maxFiles) {
            if (window.NotificationSystem) {
                window.NotificationSystem.warning(`Du kan maksimalt uploade ${maxFiles} filer ad gangen`);
            }
            return;
        }

        // Validate and upload each file
        files.forEach((file, index) => {
            if (!this.validateFile(file, allowedFileTypes)) {
                if (window.NotificationSystem) {
                    window.NotificationSystem.error(`${file.name}: Ugyldig filtype eller størrelse`);
                }
                return;
            }

            this.uploadFile(file, {
                uploadUrl,
                targetFolder,
                projectId,
                customerId,
                buildingId,
                preview,
                onComplete: (result) => {
                    if (onComplete) onComplete(result, file);
                },
                onError: (error) => {
                    if (onError) onError(error, file);
                }
            });
        });
    }

    /**
     * Validate file
     */
    validateFile(file, allowedFileTypes) {
        // Check file size
        if (file.size > this.maxFileSize) {
            return false;
        }

        // Check file type
        let typeAllowed = false;
        allowedFileTypes.forEach(category => {
            if (this.allowedTypes[category]) {
                if (this.allowedTypes[category].includes(file.type)) {
                    typeAllowed = true;
                }
            }
        });

        return typeAllowed;
    }

    /**
     * Upload file with progress
     */
    uploadFile(file, options) {
        const {
            uploadUrl,
            targetFolder,
            projectId,
            customerId,
            buildingId,
            preview,
            onComplete,
            onError
        } = options;

        const uploadId = ++this.uploadId;
        const itemId = `upload-item-${uploadId}`;

        // Create preview item
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.id = itemId;
        item.innerHTML = `
            <div class="upload-item-icon">
                ${this.getFileIcon(file.type)}
            </div>
            <div class="upload-item-info">
                <div class="upload-item-name">${escapeHtml(file.name)}</div>
                <div class="upload-item-size">${this.formatFileSize(file.size)}</div>
                <div class="upload-item-progress-container">
                    <div class="upload-item-progress" id="progress-${uploadId}"></div>
                    <div class="upload-item-percentage" id="percentage-${uploadId}">0%</div>
                </div>
            </div>
            <button type="button" class="upload-item-cancel" id="cancel-${uploadId}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;

        if (preview) {
            preview.appendChild(item);
        }

        const progressBar = document.getElementById(`progress-${uploadId}`);
        const percentage = document.getElementById(`percentage-${uploadId}`);
        const cancelBtn = document.getElementById(`cancel-${uploadId}`);

        // Create FormData
        const formData = new FormData();
        formData.append('file', file);
        formData.append('target_folder', targetFolder);
        if (projectId) formData.append('project_id', projectId);
        if (customerId) formData.append('customer_id', customerId);
        if (buildingId) formData.append('building_id', buildingId);
        formData.append(window.CSRF_TOKEN_NAME, window.CSRF_TOKEN);

        // Create XHR for progress tracking
        const xhr = new XMLHttpRequest();

        // Upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                if (progressBar) {
                    progressBar.style.width = `${percentComplete}%`;
                }
                if (percentage) {
                    percentage.textContent = `${percentComplete}%`;
                }
            }
        });

        // Upload complete
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        item.classList.add('upload-success');
                        if (progressBar) progressBar.classList.add('success');
                        if (percentage) percentage.textContent = 'Færdig';

                        setTimeout(() => {
                            if (item && item.parentNode) {
                                item.remove();
                            }
                        }, 2000);

                        if (onComplete) onComplete(response);

                        if (window.NotificationSystem) {
                            window.NotificationSystem.success(`${file.name} uploadet`);
                        }
                    } else {
                        item.classList.add('upload-error');
                        if (percentage) percentage.textContent = 'Fejl';

                        if (onError) onError(new Error(response.error || 'Upload fejlede'));

                        if (window.NotificationSystem) {
                            window.NotificationSystem.error(response.error || 'Upload fejlede');
                        }
                    }
                } catch (error) {
                    item.classList.add('upload-error');
                    if (window.logError) {
                        window.logError(error, { method: 'uploadFile', file: file.name });
                    }
                    if (onError) onError(error);
                }
            } else {
                item.classList.add('upload-error');
                if (percentage) percentage.textContent = 'Fejl';

                if (window.NotificationSystem) {
                    window.NotificationSystem.error(`Fejl ved upload af ${file.name}`);
                }

                if (onError) onError(new Error(`HTTP ${xhr.status}`));
            }
        });

        // Upload error
        xhr.addEventListener('error', () => {
            item.classList.add('upload-error');
            if (percentage) percentage.textContent = 'Fejl';

            if (window.NotificationSystem) {
                window.NotificationSystem.error(`Netværksfejl ved upload af ${file.name}`);
            }

            if (onError) onError(new Error('Network error'));
        });

        // Cancel button
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                xhr.abort();
                if (item && item.parentNode) {
                    item.remove();
                }

                if (window.NotificationSystem) {
                    window.NotificationSystem.info('Upload annulleret');
                }
            });
        }

        // Store upload reference
        this.uploads.set(uploadId, { xhr, file, item });

        // Send request
        xhr.open('POST', uploadUrl);
        xhr.send(formData);
    }

    /**
     * Get file icon based on mime type
     */
    getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) {
            return `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
            </svg>`;
        } else if (mimeType === 'application/pdf') {
            return `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>`;
        } else {
            return `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                <polyline points="13 2 13 9 20 9"></polyline>
            </svg>`;
        }
    }

    /**
     * Get accept string for file input
     */
    getAcceptString(allowedFileTypes) {
        const types = [];
        allowedFileTypes.forEach(category => {
            if (this.allowedTypes[category]) {
                types.push(...this.allowedTypes[category]);
            }
        });
        return types.join(',');
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Cancel upload
     */
    cancelUpload(uploadId) {
        const upload = this.uploads.get(uploadId);
        if (upload) {
            upload.xhr.abort();
            if (upload.item && upload.item.parentNode) {
                upload.item.remove();
            }
            this.uploads.delete(uploadId);
        }
    }

    /**
     * Cancel all uploads
     */
    cancelAll() {
        this.uploads.forEach((upload, uploadId) => {
            this.cancelUpload(uploadId);
        });
    }
}

window.UploadSystem = new UploadSystem();
