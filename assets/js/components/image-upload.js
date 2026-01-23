/**
 * Image Upload Component
 * Handles image upload, gallery display, and management
 * Backend handles all processing (resize, thumbnails, permissions)
 */

const ImageUpload = {
    /**
     * Open image upload modal for an entity
     * @param {string} entityType - 'project', 'building', 'building_element'
     * @param {number} entityId - ID of the entity
     * @param {function} onUploadComplete - Callback after successful upload
     */
    openUploadModal(entityType, entityId, onUploadComplete = null) {
        const modalContent = `
            <form id="imageUploadForm" onsubmit="return ImageUpload.handleUpload(event, '${entityType}', ${entityId}, ${onUploadComplete ? 'true' : 'false'});">
                <div class="modal-body">
                    <div id="uploadErrors"></div>

                    <div class="upload-zone" id="uploadZone">
                        <div class="upload-icon">${this.getIcon('image', 48)}</div>
                        <h3>Upload billeder</h3>
                        <p>Træk og slip billeder her, eller klik for at vælge</p>
                        <input type="file"
                               id="imageFiles"
                               name="images[]"
                               accept="image/jpeg,image/png,image/webp"
                               multiple
                               style="display: none;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('imageFiles').click()">
                            ${this.getIcon('folder', 18)} Vælg filer
                        </button>
                    </div>

                    <div id="filePreview" class="file-preview" style="display: none;"></div>

                    <div class="form-group">
                        <label>Beskrivelse (valgfrit)</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Tilføj en beskrivelse til billederne..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.close()">Annuller</button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                        ${this.getIcon('upload', 18)} Upload
                    </button>
                </div>
            </form>
        `;

        Modal.open(modalContent, {
            size: 'medium',
            title: 'Upload billeder',
            onClose: () => {
                if (onUploadComplete) onUploadComplete();
            }
        });

        // Setup drag-and-drop and file selection
        setTimeout(() => {
            this.setupDropZone();
            this.setupFileInput();
        }, 100);
    },

    /**
     * Setup drag-and-drop zone
     */
    setupDropZone() {
        const dropZone = document.getElementById('uploadZone');
        if (!dropZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('drag-over');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            document.getElementById('imageFiles').files = files;
            this.handleFileSelect(files);
        });

        dropZone.addEventListener('click', () => {
            document.getElementById('imageFiles').click();
        });
    },

    /**
     * Setup file input change handler
     */
    setupFileInput() {
        const fileInput = document.getElementById('imageFiles');
        if (!fileInput) return;

        fileInput.addEventListener('change', (e) => {
            this.handleFileSelect(e.target.files);
        });
    },

    /**
     * Handle file selection
     */
    handleFileSelect(files) {
        if (!files || files.length === 0) return;

        const preview = document.getElementById('filePreview');
        const uploadBtn = document.getElementById('uploadBtn');

        // Validate files using centralized Validation library
        const validation = Validation.files(files, 'image', { maxFiles: 50 });
        if (!validation.valid) {
            this.showError(validation.error);
            return;
        }

        preview.innerHTML = '';
        preview.style.display = 'grid';

        Array.from(files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="${Validation.Validation.escapeHtml(file.name)}">
                    <div class="preview-info">
                        <div class="preview-name">${Validation.Validation.escapeHtml(file.name)}</div>
                        <div class="preview-size">${Validation.formatFileSize(file.size)}</div>
                    </div>
                `;
                preview.appendChild(previewItem);
            };
            reader.readAsDataURL(file);
        });

        uploadBtn.disabled = false;
    },

    /**
     * Handle form upload
     */
    async handleUpload(event, entityType, entityId, hasCallback) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'upload_image');
        formData.append('entity_type', entityType);
        formData.append('entity_id', entityId);

        const uploadBtn = document.getElementById('uploadBtn');
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = `${this.getIcon('loader', 18)} Uploader...`;

        try {
            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success(`${response.uploaded || 0} billede(r) uploadet`);
                Modal.close();

                // Reload page or call callback
                if (hasCallback) {
                    setTimeout(() => Router.reload(), 500);
                }
            } else {
                this.showError(response.error || 'Upload fejlede');
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = `${this.getIcon('upload', 18)} Upload`;
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showError('Netværksfejl ved upload');
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = `${this.getIcon('upload', 18)} Upload`;
        }

        return false;
    },

    /**
     * Display image gallery for an entity
     */
    async showGallery(entityType, entityId, containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) {
            console.error('Gallery container not found:', containerSelector);
            return;
        }

        container.innerHTML = '<div class="loading-state"><div class="spinner-small"></div> Indlæser billeder...</div>';

        try {
            const response = await API.get('/api.php', {
                action: 'get_images',
                entity_type: entityType,
                entity_id: entityId
            });

            if (response.success) {
                this.renderGallery(response.images, container, entityType, entityId);
            } else {
                container.innerHTML = `<div class="error-state">${Validation.escapeHtml(response.error)}</div>`;
            }
        } catch (error) {
            console.error('Gallery load error:', error);
            container.innerHTML = '<div class="error-state">Kunne ikke indlæse billeder</div>';
        }
    },

    /**
     * Render gallery HTML
     */
    renderGallery(images, container, entityType, entityId) {
        if (!images || images.length === 0) {
            container.innerHTML = `
                <div class="empty-gallery">
                    ${this.getIcon('image', 48)}
                    <p>Ingen billeder uploadet endnu</p>
                    <button class="btn btn-primary" onclick="ImageUpload.openUploadModal('${entityType}', ${entityId}, () => ImageUpload.showGallery('${entityType}', ${entityId}, '${container.className}'))">
                        ${this.getIcon('upload', 18)} Upload billeder
                    </button>
                </div>
            `;
            return;
        }

        let html = '<div class="image-gallery" id="imageGallery-' + entityType + '-' + entityId + '">';

        images.forEach(img => {
            html += `
                <div class="gallery-item" data-image-id="${img.id}" draggable="true">
                    <div class="image-drag-handle" title="Træk for at ændre rækkefølge">
                        ${this.getIcon('menu', 14)}
                    </div>
                    <img src="${Validation.escapeHtml(img.thumbnail_path || img.file_path)}"
                         alt="${Validation.escapeHtml(img.description || '')}"
                         onclick="ImageUpload.viewImage('${Validation.escapeHtml(img.file_path)}', '${Validation.escapeHtml(img.description || '')}')">
                    <div class="gallery-actions">
                        <button class="btn-icon" onclick="ImageUpload.viewImage('${Validation.escapeHtml(img.file_path)}', '${Validation.escapeHtml(img.description || '')}')" title="Vis">
                            ${this.getIcon('eye', 16)}
                        </button>
                        <button class="btn-icon btn-icon-danger" onclick="ImageUpload.deleteImage(${img.id}, '${entityType}', ${entityId}, '.${container.className}')" title="Slet">
                            ${this.getIcon('trash', 16)}
                        </button>
                    </div>
                    ${img.description ? `<div class="gallery-caption">${Validation.escapeHtml(img.description)}</div>` : ''}
                </div>
            `;
        });

        html += '</div>';
        html += `
            <div class="gallery-footer">
                <button class="btn btn-secondary" onclick="ImageUpload.openUploadModal('${entityType}', ${entityId}, () => ImageUpload.showGallery('${entityType}', ${entityId}, '.${container.className}'))">
                    ${this.getIcon('upload', 18)} Upload flere
                </button>
            </div>
        `;

        container.innerHTML = html;

        // Initialize drag-and-drop for image sorting
        setTimeout(() => {
            this.initImageDragDrop(entityType, entityId);
        }, 100);
    },

    /**
     * Initialize drag-and-drop for image gallery
     */
    initImageDragDrop(entityType, entityId) {
        const gallery = document.getElementById('imageGallery-' + entityType + '-' + entityId);
        if (!gallery) return;

        const items = gallery.querySelectorAll('.gallery-item');
        let draggedItem = null;

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                draggedItem = item;
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', (e) => {
                item.classList.remove('dragging');
                this.saveImageOrder(entityType, entityId);
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                const afterElement = this.getDragAfterImageElement(gallery, e.clientX, e.clientY);
                if (afterElement == null) {
                    gallery.appendChild(draggedItem);
                } else {
                    gallery.insertBefore(draggedItem, afterElement);
                }
            });
        });
    },

    /**
     * Get element after drag position for grid layout
     */
    getDragAfterImageElement(container, x, y) {
        const draggableElements = [...container.querySelectorAll('.gallery-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offsetX = x - box.left - box.width / 2;
            const offsetY = y - box.top - box.height / 2;
            const offset = Math.sqrt(offsetX * offsetX + offsetY * offsetY);

            if (offset < closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.POSITIVE_INFINITY }).element;
    },

    /**
     * Save new image order to backend
     */
    async saveImageOrder(entityType, entityId) {
        const gallery = document.getElementById('imageGallery-' + entityType + '-' + entityId);
        if (!gallery) return;

        const imageIds = [...gallery.querySelectorAll('.gallery-item')].map(item => item.dataset.imageId);

        try {
            const formData = new FormData();
            formData.append('action', 'update_image_order');
            formData.append('entity_type', entityType);
            formData.append('entity_id', entityId);
            formData.append('image_ids', JSON.stringify(imageIds));

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Billede rækkefølge opdateret');
            } else {
                Toast.error(response.error || 'Kunne ikke opdatere rækkefølge');
            }
        } catch (error) {
            console.error('Image order save error:', error);
            Toast.error('Netværksfejl ved opdatering');
        }
    },

    /**
     * View image in modal
     */
    viewImage(imagePath, description) {
        const content = `
            <div class="image-viewer">
                <img src="${Validation.escapeHtml(imagePath)}" alt="${Validation.escapeHtml(description)}">
                ${description ? `<p class="image-description">${Validation.escapeHtml(description)}</p>` : ''}
            </div>
        `;

        Modal.open(content, { size: 'large', title: 'Vis billede' });
    },

    /**
     * Delete image
     */
    async deleteImage(imageId, entityType, entityId, containerSelector) {
        if (!await Modal.confirm('Slet dette billede?', {
            title: 'Bekræft sletning',
            confirmText: 'Slet',
            confirmClass: 'btn-danger'
        })) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_image');
            formData.append('image_id', imageId);

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Billede slettet');
                this.showGallery(entityType, entityId, containerSelector);
            } else {
                Toast.error(response.error || 'Kunne ikke slette billede');
            }
        } catch (error) {
            console.error('Delete error:', error);
            Toast.error('Netværksfejl ved sletning');
        }
    },

    /**
     * Show error message
     */
    showError(message) {
        const errorsEl = document.getElementById('uploadErrors');
        if (errorsEl) {
            errorsEl.innerHTML = `<div class="alert alert-error">${Validation.escapeHtml(message)}</div>`;
            errorsEl.style.display = 'block';
        }
    },

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Export globally
window.ImageUpload = ImageUpload;
