/**
 * Drag-and-Drop Sortering System
 * Med backend permission validation
 */

const DragDrop = {
    /**
     * Initialize sortable list
     */
    init(containerId, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Drag-drop container not found:', containerId);
            return;
        }

        const {
            handle = '.drag-handle',
            onUpdate = null,
            buildingId = null
        } = options;

        this.makeSortable(container, handle, async (items) => {
            // Send updated order to backend
            const itemIds = items.map(item => item.dataset.id);

            try {
                const formData = new FormData();
                formData.append('action', 'update_order');
                formData.append('items', JSON.stringify(itemIds));
                if (buildingId) {
                    formData.append('building_id', buildingId);
                }

                const response = await API.post('/api.php', formData, true);

                if (response.success) {
                    Toast.success('Rækkefølge opdateret');
                    if (onUpdate) onUpdate(itemIds);
                } else {
                    Toast.error(response.error || 'Kunne ikke opdatere rækkefølge');
                    // Revert order
                    this.revertOrder(container);
                }
            } catch (error) {
                console.error('Order update error:', error);
                Toast.error('Fejl ved opdatering af rækkefølge');
                this.revertOrder(container);
            }
        });
    },

    /**
     * Make container sortable
     */
    makeSortable(container, handleSelector, onUpdate) {
        let draggedItem = null;
        let originalOrder = null;

        const items = Array.from(container.querySelectorAll('[data-id]'));
        
        items.forEach(item => {
            const handle = item.querySelector(handleSelector) || item;
            handle.setAttribute('draggable', 'true');
            handle.style.cursor = 'move';

            handle.addEventListener('dragstart', (e) => {
                draggedItem = item;
                originalOrder = items.map(i => i.dataset.id);
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', (e) => {
                item.classList.remove('dragging');
                
                // Check if order changed
                const currentOrder = Array.from(container.querySelectorAll('[data-id]'));
                const newOrder = currentOrder.map(i => i.dataset.id);
                
                if (JSON.stringify(originalOrder) !== JSON.stringify(newOrder)) {
                    if (onUpdate) onUpdate(currentOrder);
                }
                
                draggedItem = null;
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                
                if (draggedItem === item) return;

                const afterElement = this.getDragAfterElement(container, e.clientY);
                
                if (afterElement == null) {
                    container.appendChild(draggedItem);
                } else {
                    container.insertBefore(draggedItem, afterElement);
                }
            });
        });

        // Store original order for revert
        container.dataset.originalOrder = JSON.stringify(items.map(i => i.dataset.id));
    },

    /**
     * Get element after drag position
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('[data-id]:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },

    /**
     * Revert to original order
     */
    revertOrder(container) {
        const originalOrder = JSON.parse(container.dataset.originalOrder || '[]');
        const items = container.querySelectorAll('[data-id]');
        
        // Create a map of items by ID
        const itemMap = {};
        items.forEach(item => {
            itemMap[item.dataset.id] = item;
        });

        // Reorder based on original order
        originalOrder.forEach(id => {
            if (itemMap[id]) {
                container.appendChild(itemMap[id]);
            }
        });
    }
};

// Export globally
window.DragDrop = DragDrop;
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

        preview.innerHTML = '';
        preview.style.display = 'grid';

        Array.from(files).forEach((file, index) => {
            if (!file.type.startsWith('image/')) {
                this.showError(`${file.name} er ikke et billede`);
                return;
            }

            // Check file size (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                this.showError(`${file.name} er for stor (max 10MB)`);
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="${escapeHtml(file.name)}">
                    <div class="preview-info">
                        <div class="preview-name">${escapeHtml(file.name)}</div>
                        <div class="preview-size">${this.formatFileSize(file.size)}</div>
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
                container.innerHTML = `<div class="error-state">${escapeHtml(response.error)}</div>`;
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
                    <img src="${escapeHtml(img.thumbnail_path || img.file_path)}"
                         alt="${escapeHtml(img.description || '')}"
                         onclick="ImageUpload.viewImage('${escapeHtml(img.file_path)}', '${escapeHtml(img.description || '')}')">
                    <div class="gallery-actions">
                        <button class="btn-icon" onclick="ImageUpload.viewImage('${escapeHtml(img.file_path)}', '${escapeHtml(img.description || '')}')" title="Vis">
                            ${this.getIcon('eye', 16)}
                        </button>
                        <button class="btn-icon btn-icon-danger" onclick="ImageUpload.deleteImage(${img.id}, '${entityType}', ${entityId}, '.${container.className}')" title="Slet">
                            ${this.getIcon('trash', 16)}
                        </button>
                    </div>
                    ${img.description ? `<div class="gallery-caption">${escapeHtml(img.description)}</div>` : ''}
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
                <img src="${escapeHtml(imagePath)}" alt="${escapeHtml(description)}">
                ${description ? `<p class="image-description">${escapeHtml(description)}</p>` : ''}
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
            errorsEl.innerHTML = `<div class="alert alert-error">${escapeHtml(message)}</div>`;
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
/**
 * Project Snapshot Component
 * Handles creating, restoring, and managing project snapshots
 * Backend API handles all data processing and permissions
 */

const ProjectSnapshot = {
    /**
     * Show snapshots manager modal for a project
     * @param {number} projectId - Project ID
     */
    async showManager(projectId) {
        const modalContent = `
            <div class="snapshot-manager">
                <div class="modal-body">
                    <div id="snapshotErrors"></div>

                    <div class="snapshot-actions">
                        <button class="btn btn-primary" onclick="ProjectSnapshot.openCreateModal(${projectId})">
                            ${this.getIcon('save', 18)} Opret nyt snapshot
                        </button>
                    </div>

                    <div id="snapshotsList" class="snapshots-list">
                        <div class="loading-state">
                            <div class="spinner-small"></div>
                            Indlæser snapshots...
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="Modal.close()">Luk</button>
                </div>
            </div>
        `;

        Modal.open(modalContent, {
            size: 'large',
            title: 'Project Snapshots'
        });

        // Load snapshots list
        setTimeout(() => {
            this.loadSnapshots(projectId);
        }, 100);
    },

    /**
     * Load snapshots list from API
     */
    async loadSnapshots(projectId) {
        const container = document.getElementById('snapshotsList');
        if (!container) return;

        try {
            const response = await API.get('/api.php', {
                action: 'list_snapshots',
                project_id: projectId
            });

            if (response.success) {
                this.renderSnapshots(response.snapshots, projectId);
            } else {
                container.innerHTML = `<div class="error-state">${escapeHtml(response.error)}</div>`;
            }
        } catch (error) {
            console.error('Snapshots load error:', error);
            container.innerHTML = '<div class="error-state">Kunne ikke indlæse snapshots</div>';
        }
    },

    /**
     * Render snapshots list
     */
    renderSnapshots(snapshots, projectId) {
        const container = document.getElementById('snapshotsList');
        if (!container) return;

        if (!snapshots || snapshots.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    ${this.getIcon('archive', 48)}
                    <h3>Ingen snapshots</h3>
                    <p>Opret et snapshot for at gemme projektets nuværende tilstand</p>
                    <button class="btn btn-primary" onclick="ProjectSnapshot.openCreateModal(${projectId})">
                        ${this.getIcon('save', 18)} Opret snapshot
                    </button>
                </div>
            `;
            return;
        }

        let html = '<div class="snapshots-grid">';

        snapshots.forEach(snapshot => {
            const date = new Date(snapshot.created_at);
            const formattedDate = date.toLocaleString('da-DK', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            html += `
                <div class="snapshot-card">
                    <div class="snapshot-header">
                        <div class="snapshot-icon">${this.getIcon('archive', 24)}</div>
                        <div class="snapshot-info">
                            <h4>${escapeHtml(snapshot.name || 'Unavngivet snapshot')}</h4>
                            <div class="snapshot-meta">
                                <span class="snapshot-date">${formattedDate}</span>
                                <span class="snapshot-user">af ${escapeHtml(snapshot.created_by_name || 'Ukendt')}</span>
                            </div>
                        </div>
                    </div>

                    ${snapshot.description ? `
                        <div class="snapshot-description">
                            ${escapeHtml(snapshot.description)}
                        </div>
                    ` : ''}

                    <div class="snapshot-stats">
                        <div class="stat-item">
                            <span class="stat-label">Bygninger:</span>
                            <span class="stat-value">${snapshot.stats?.buildings || 0}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Elementer:</span>
                            <span class="stat-value">${snapshot.stats?.elements || 0}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Filer:</span>
                            <span class="stat-value">${snapshot.stats?.files || 0}</span>
                        </div>
                    </div>

                    <div class="snapshot-actions">
                        <button class="btn btn-sm btn-primary"
                                onclick="ProjectSnapshot.confirmRestore(${snapshot.id}, ${projectId}, '${escapeHtml(snapshot.name || 'Unavngivet')}')">
                            ${this.getIcon('refresh-cw', 16)} Gendan
                        </button>
                        <button class="btn btn-sm btn-secondary"
                                onclick="ProjectSnapshot.viewDetails(${snapshot.id})">
                            ${this.getIcon('eye', 16)} Detaljer
                        </button>
                        <button class="btn-icon btn-icon-danger"
                                onclick="ProjectSnapshot.deleteSnapshot(${snapshot.id}, ${projectId})"
                                title="Slet snapshot">
                            ${this.getIcon('trash', 16)}
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    },

    /**
     * Open create snapshot modal
     */
    openCreateModal(projectId) {
        const createContent = `
            <form id="createSnapshotForm" onsubmit="return ProjectSnapshot.handleCreate(event, ${projectId});">
                <div class="modal-body">
                    <div id="createErrors"></div>

                    <div class="form-group">
                        <label class="required">Snapshot navn</label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               placeholder="F.eks. 'Før renovering'"
                               required>
                    </div>

                    <div class="form-group">
                        <label>Beskrivelse</label>
                        <textarea name="description"
                                  rows="4"
                                  class="form-control"
                                  placeholder="Beskriv hvad dette snapshot indeholder..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        ${this.getIcon('info', 18)}
                        <div>
                            <strong>Info:</strong> Snapshot vil gemme hele projektets nuværende tilstand,
                            inklusiv alle bygninger, elementer, og filer.
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.close()">Annuller</button>
                    <button type="submit" class="btn btn-primary" id="createBtn">
                        ${this.getIcon('save', 18)} Opret snapshot
                    </button>
                </div>
            </form>
        `;

        Modal.open(createContent, {
            size: 'medium',
            title: 'Opret nyt snapshot'
        });
    },

    /**
     * Handle snapshot creation
     */
    async handleCreate(event, projectId) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'create_snapshot');
        formData.append('project_id', projectId);

        const createBtn = document.getElementById('createBtn');
        createBtn.disabled = true;
        createBtn.innerHTML = `${this.getIcon('loader', 18)} Opretter...`;

        try {
            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Snapshot oprettet');
                Modal.close();

                // Reload snapshots manager
                setTimeout(() => {
                    this.showManager(projectId);
                }, 300);
            } else {
                this.showError(response.error || 'Kunne ikke oprette snapshot', 'createErrors');
                createBtn.disabled = false;
                createBtn.innerHTML = `${this.getIcon('save', 18)} Opret snapshot`;
            }
        } catch (error) {
            console.error('Snapshot creation error:', error);
            this.showError('Netværksfejl ved oprettelse', 'createErrors');
            createBtn.disabled = false;
            createBtn.innerHTML = `${this.getIcon('save', 18)} Opret snapshot`;
        }

        return false;
    },

    /**
     * Confirm snapshot restore
     */
    async confirmRestore(snapshotId, projectId, snapshotName) {
        const confirmed = await Modal.confirm(
            `Er du sikker på at du vil gendanne projektet til snapshot "${snapshotName}"?\n\n` +
            `⚠️ ADVARSEL: Den nuværende tilstand vil blive overskrevet. Overvej at oprette et snapshot af den nuværende tilstand først.`,
            {
                title: 'Bekræft gendannelse',
                confirmText: 'Gendan',
                confirmClass: 'btn-warning'
            }
        );

        if (!confirmed) return;

        App.showLoading('Gendanner snapshot...');

        try {
            const formData = new FormData();
            formData.append('action', 'restore_snapshot');
            formData.append('snapshot_id', snapshotId);

            const response = await API.post('/api.php', formData, true);

            App.hideLoading();

            if (response.success) {
                Toast.success('Snapshot gendannet succesfuldt');
                Modal.close();

                // Reload project page
                setTimeout(() => {
                    Router.reload();
                }, 500);
            } else {
                Toast.error(response.error || 'Kunne ikke gendanne snapshot');
            }
        } catch (error) {
            console.error('Snapshot restore error:', error);
            App.hideLoading();
            Toast.error('Netværksfejl ved gendannelse');
        }
    },

    /**
     * View snapshot details
     */
    async viewDetails(snapshotId) {
        // TODO: Implement detailed view of snapshot contents
        Toast.info('Snapshot detaljer kommer snart');
    },

    /**
     * Delete snapshot
     */
    async deleteSnapshot(snapshotId, projectId) {
        if (!await Modal.confirm('Slet dette snapshot permanent?', {
            title: 'Bekræft sletning',
            confirmText: 'Slet',
            confirmClass: 'btn-danger'
        })) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_snapshot');
            formData.append('snapshot_id', snapshotId);

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Snapshot slettet');
                this.loadSnapshots(projectId);
            } else {
                Toast.error(response.error || 'Kunne ikke slette snapshot');
            }
        } catch (error) {
            console.error('Snapshot delete error:', error);
            Toast.error('Netværksfejl ved sletning');
        }
    },

    /**
     * Show error message
     */
    showError(message, containerId = 'snapshotErrors') {
        const errorsEl = document.getElementById(containerId);
        if (errorsEl) {
            errorsEl.innerHTML = `<div class="alert alert-error">${escapeHtml(message)}</div>`;
            errorsEl.style.display = 'block';
        }
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Export globally
window.ProjectSnapshot = ProjectSnapshot;
/**
 * Report Tree View Component
 * Hierarchical tree structure for buildings and elements with drag-drop sorting
 * Automatic cost summation up the hierarchy
 */

const ReportTree = {
    currentProjectId: null,
    treeData: null,
    expandedNodes: new Set(),

    /**
     * Initialize tree view for a project
     * @param {number} projectId - Project ID
     * @param {string} containerSelector - Container element selector
     */
    async init(projectId, containerSelector) {
        this.currentProjectId = projectId;
        const container = document.querySelector(containerSelector);

        if (!container) {
            console.error('Report tree container not found:', containerSelector);
            return;
        }

        container.innerHTML = '<div class="tree-loading"><div class="spinner-small"></div> Indlæser træstruktur...</div>';

        try {
            // Load hierarchical data from API
            const response = await API.get('/api.php', {
                action: 'get_project_tree',
                project_id: projectId
            });

            if (response.success) {
                this.treeData = response.tree;
                this.render(container);
            } else {
                container.innerHTML = `<div class="tree-error">${escapeHtml(response.error)}</div>`;
            }
        } catch (error) {
            console.error('Tree load error:', error);
            container.innerHTML = '<div class="tree-error">Kunne ikke indlæse træstruktur</div>';
        }
    },

    /**
     * Render tree structure
     */
    render(container) {
        if (!this.treeData || !this.treeData.buildings || this.treeData.buildings.length === 0) {
            container.innerHTML = `
                <div class="tree-empty">
                    ${this.getIcon('building', 48)}
                    <p>Ingen bygninger i projektet</p>
                </div>
            `;
            return;
        }

        let html = '<div class="report-tree">';

        // Project header
        html += `
            <div class="tree-header">
                <h3>${escapeHtml(this.treeData.project_name)}</h3>
                <div class="tree-total">
                    <span class="total-label">Total CAPEX:</span>
                    <span class="total-value">${this.formatMoney(this.treeData.total_capex)}</span>
                </div>
            </div>
        `;

        // Buildings and their elements
        html += '<div class="tree-nodes" id="treeNodes">';

        this.treeData.buildings.forEach(building => {
            html += this.renderBuildingNode(building);
        });

        html += '</div>';
        html += '</div>';

        container.innerHTML = html;

        // Initialize drag-and-drop for sortable elements
        this.initDragDrop();
    },

    /**
     * Render building node
     */
    renderBuildingNode(building) {
        const isExpanded = this.expandedNodes.has(`building-${building.id}`) !== false;
        const hasElements = building.elements && building.elements.length > 0;

        let html = `
            <div class="tree-node building-node" data-type="building" data-id="${building.id}">
                <div class="node-header">
                    ${hasElements ? `
                        <button class="node-toggle ${isExpanded ? 'expanded' : ''}"
                                onclick="ReportTree.toggleNode('building-${building.id}')">
                            ${this.getIcon('chevron-right', 16)}
                        </button>
                    ` : '<span class="node-spacer"></span>'}

                    <span class="node-icon building-icon">${this.getIcon('building', 18)}</span>
                    <span class="node-label">${escapeHtml(building.name)}</span>

                    <div class="node-stats">
                        <span class="stat-elements">${building.element_count || 0} elementer</span>
                        <span class="stat-capex">${this.formatMoney(building.total_capex)}</span>
                    </div>
                </div>

                ${hasElements && isExpanded ? `
                    <div class="node-children building-elements" data-building-id="${building.id}">
                        ${this.renderElements(building.elements, 1)}
                    </div>
                ` : ''}
            </div>
        `;

        return html;
    },

    /**
     * Render elements recursively
     */
    renderElements(elements, level) {
        let html = '';

        elements.forEach(element => {
            html += this.renderElementNode(element, level);
        });

        return html;
    },

    /**
     * Render single element node
     */
    renderElementNode(element, level) {
        const isExpanded = this.expandedNodes.has(`element-${element.id}`) !== false;
        const hasChildren = element.children && element.children.length > 0;
        const indent = level * 20;

        let html = `
            <div class="tree-node element-node"
                 data-type="element"
                 data-id="${element.id}"
                 data-parent-id="${element.parent_id || ''}"
                 data-level="${level}"
                 style="padding-left: ${indent}px;">

                <div class="node-header">
                    <span class="drag-handle" title="Træk for at flytte">${this.getIcon('menu', 14)}</span>

                    ${hasChildren ? `
                        <button class="node-toggle ${isExpanded ? 'expanded' : ''}"
                                onclick="ReportTree.toggleNode('element-${element.id}')">
                            ${this.getIcon('chevron-right', 14)}
                        </button>
                    ` : '<span class="node-spacer"></span>'}

                    <span class="node-icon element-icon">${this.getIcon('box', 16)}</span>
                    <span class="node-label">${escapeHtml(element.name)}</span>

                    <div class="node-stats">
                        ${element.urgency ? `<span class="urgency-badge urgency-${element.urgency}">${element.urgency}</span>` : ''}
                        ${element.unit ? `<span class="stat-unit">${escapeHtml(element.unit)}</span>` : ''}
                        ${element.quantity ? `<span class="stat-quantity">${element.quantity}</span>` : ''}
                        <span class="stat-capex">${this.formatMoney(element.total_capex || element.capex)}</span>
                    </div>

                    <div class="node-actions">
                        <button class="btn-icon-mini" onclick="ReportTree.editElement(${element.id})" title="Rediger">
                            ${this.getIcon('edit', 14)}
                        </button>
                    </div>
                </div>

                ${hasChildren && isExpanded ? `
                    <div class="node-children element-children">
                        ${this.renderElements(element.children, level + 1)}
                    </div>
                ` : ''}
            </div>
        `;

        return html;
    },

    /**
     * Toggle node expansion
     */
    toggleNode(nodeId) {
        if (this.expandedNodes.has(nodeId)) {
            this.expandedNodes.delete(nodeId);
        } else {
            this.expandedNodes.add(nodeId);
        }

        // Re-render tree
        const container = document.querySelector('.report-tree-container');
        if (container) {
            this.render(container);
        }
    },

    /**
     * Initialize drag-and-drop for tree nodes
     */
    initDragDrop() {
        const elementNodes = document.querySelectorAll('.element-node');
        let draggedElement = null;
        let originalParent = null;
        let originalNextSibling = null;

        elementNodes.forEach(node => {
            const dragHandle = node.querySelector('.drag-handle');
            if (!dragHandle) return;

            node.draggable = true;

            node.addEventListener('dragstart', (e) => {
                draggedElement = node;
                originalParent = node.parentElement;
                originalNextSibling = node.nextElementSibling;
                node.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            node.addEventListener('dragend', (e) => {
                node.classList.remove('dragging');

                // Check if position changed
                const newParent = draggedElement.parentElement;
                if (newParent !== originalParent || draggedElement.nextElementSibling !== originalNextSibling) {
                    this.handleDrop(draggedElement);
                }

                draggedElement = null;
            });

            node.addEventListener('dragover', (e) => {
                e.preventDefault();

                if (!draggedElement || draggedElement === node) return;

                const parent = node.parentElement;
                const afterElement = this.getDragAfterElement(parent, e.clientY);

                if (afterElement == null) {
                    parent.appendChild(draggedElement);
                } else {
                    parent.insertBefore(draggedElement, afterElement);
                }
            });
        });
    },

    /**
     * Get element after drag position
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.element-node:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },

    /**
     * Handle drop - save new order and hierarchy
     */
    async handleDrop(element) {
        const elementId = element.dataset.id;
        const newParent = element.parentElement;
        const newParentId = newParent.dataset.buildingId || newParent.closest('.element-node')?.dataset.id || null;

        // Get new order within parent
        const siblings = [...newParent.querySelectorAll('.element-node')];
        const newOrder = siblings.map(node => node.dataset.id);

        try {
            const formData = new FormData();
            formData.append('action', 'update_element_hierarchy');
            formData.append('element_id', elementId);
            formData.append('parent_id', newParentId || '');
            formData.append('order', JSON.stringify(newOrder));

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Hierarki opdateret');
                // Reload tree to get updated totals
                this.init(this.currentProjectId, '.report-tree-container');
            } else {
                Toast.error(response.error || 'Kunne ikke opdatere hierarki');
                // Revert
                this.init(this.currentProjectId, '.report-tree-container');
            }
        } catch (error) {
            console.error('Hierarchy update error:', error);
            Toast.error('Netværksfejl ved opdatering');
            // Revert
            this.init(this.currentProjectId, '.report-tree-container');
        }
    },

    /**
     * Edit element
     */
    editElement(elementId) {
        // Navigate to building element module with edit action
        navigate('building_element', { action: 'get', id: elementId });
    },

    /**
     * Format money
     */
    formatMoney(amount) {
        if (!amount || amount === 0) return '-';
        return new Intl.NumberFormat('da-DK', {
            style: 'currency',
            currency: 'DKK',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Export globally
window.ReportTree = ReportTree;

/**
 * Budget Modal System
 * For CAPEX, OPEX, and Reinstatement cost line-by-line entry
 * with template and price catalog lookup
 */
const BudgetModal = {
    elementId: null,
    budgetType: 'capex',
    lines: [],

    /**
     * Open budget modal for an element
     */
    async open(elementId, budgetType = 'capex') {
        this.elementId = elementId;
        this.budgetType = budgetType;

        // Load existing budget lines
        await this.loadBudgetLines();

        // Show modal
        this.render();
    },

    /**
     * Load existing budget lines from backend
     */
    async loadBudgetLines() {
        try {
            const response = await fetch(`/api.php?action=get_budget_lines&element_id=${this.elementId}&budget_type=${this.budgetType}`);
            const data = await response.json();

            if (data.success) {
                this.lines = data.lines || [];
            } else {
                this.lines = [];
            }
        } catch (error) {
            console.error('Failed to load budget lines:', error);
            this.lines = [];
        }
    },

    /**
     * Render budget modal
     */
    render() {
        const typeLabels = {
            'capex': 'CAPEX',
            'opex': 'OPEX',
            'reinstatement': 'Genoprettelsesomkostninger'
        };

        const modalHTML = `
            <div class="modal-overlay" id="budgetModal" onclick="if(event.target===this) BudgetModal.close()">
                <div class="modal-large" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h2>Budget & ${typeLabels[this.budgetType]}</h2>
                        <button class="btn-close" onclick="BudgetModal.close()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Toolbar -->
                        <div class="budget-toolbar">
                            <button class="btn-secondary btn-sm" onclick="BudgetModal.addLine()">
                                ${this.getIcon('plus', 16)} Tilføj linje
                            </button>
                            <button class="btn-secondary btn-sm" onclick="BudgetModal.showPriceCatalog()">
                                ${this.getIcon('search', 16)} Søg priser
                            </button>
                            <button class="btn-secondary btn-sm" onclick="BudgetModal.showTemplates()">
                                ${this.getIcon('file-text', 16)} Indlæs template
                            </button>
                        </div>

                        <!-- Budget Lines Table -->
                        <div class="budget-table-container">
                            <table class="budget-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="width: 250px;">Beskrivelse</th>
                                        <th style="width: 80px;">Mængde</th>
                                        <th style="width: 80px;">Enhed</th>
                                        <th style="width: 100px;">Pris DKK</th>
                                        <th style="width: 90px;">&lt; 1 år</th>
                                        <th style="width: 90px;">1-2 år</th>
                                        <th style="width: 90px;">3-5 år</th>
                                        <th style="width: 90px;">5-10 år</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="budgetLinesBody">
                                    ${this.renderLines()}
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="4"><strong>Total ${typeLabels[this.budgetType]}</strong></td>
                                        <td id="totalCapex"><strong>0,00</strong></td>
                                        <td id="totalYear01">0,00</td>
                                        <td id="totalYear12">0,00</td>
                                        <td id="totalYear35">0,00</td>
                                        <td id="totalYear510">0,00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary" onclick="BudgetModal.close()">Annuller</button>
                        <button class="btn-primary" onclick="BudgetModal.save()">
                            ${this.getIcon('save', 16)} Gem Budget
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existing = document.getElementById('budgetModal');
        if (existing) existing.remove();

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Calculate and update totals
        this.updateTotals();

        // Add input event listeners for live updates
        setTimeout(() => this.attachEventListeners(), 100);
    },

    /**
     * Render budget lines
     */
    renderLines() {
        if (this.lines.length === 0) {
            return `
                <tr>
                    <td colspan="10" class="text-center text-muted">
                        Ingen budgetlinjer endnu. Klik "Tilføj linje" for at komme i gang.
                    </td>
                </tr>
            `;
        }

        return this.lines.map((line, index) => `
            <tr data-line-index="${index}">
                <td>${index + 1}</td>
                <td>
                    <input type="text"
                           class="form-control form-control-sm"
                           data-field="description"
                           value="${this.escapeHtml(line.description || '')}"
                           placeholder="Beskrivelse...">
                </td>
                <td>
                    <input type="number"
                           step="0.001"
                           class="form-control form-control-sm text-right"
                           data-field="quantity"
                           value="${line.quantity || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <select class="form-control form-control-sm" data-field="unit">
                        ${this.renderUnitOptions(line.unit || 'stk')}
                    </select>
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right"
                           data-field="price_per_unit"
                           value="${line.price_per_unit || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_0_1"
                           value="${line.year_0_1 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_1_2"
                           value="${line.year_1_2 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_3_5"
                           value="${line.year_3_5 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_5_10"
                           value="${line.year_5_10 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <button class="btn-icon btn-danger btn-sm"
                            onclick="BudgetModal.deleteLine(${index})"
                            title="Slet linje">
                        ${this.getIcon('trash', 14)}
                    </button>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Render unit select options
     */
    renderUnitOptions(selectedUnit) {
        const units = ['stk', 'm2', 'm', 'm3', 'kg', 'ton', 'l', 'time', 'pauschalt'];
        return units.map(unit =>
            `<option value="${unit}" ${unit === selectedUnit ? 'selected' : ''}>${unit}</option>`
        ).join('');
    },

    /**
     * Add a new empty line
     */
    addLine() {
        this.lines.push({
            description: '',
            quantity: 0,
            unit: 'stk',
            price_per_unit: 0,
            year_0_1: 0,
            year_1_2: 0,
            year_3_5: 0,
            year_5_10: 0,
            year_10_plus: 0
        });

        this.refreshLines();
    },

    /**
     * Delete a line
     */
    deleteLine(index) {
        if (confirm('Er du sikker på at du vil slette denne linje?')) {
            this.lines.splice(index, 1);
            this.refreshLines();
        }
    },

    /**
     * Refresh lines display
     */
    refreshLines() {
        const tbody = document.getElementById('budgetLinesBody');
        if (tbody) {
            tbody.innerHTML = this.renderLines();
            this.attachEventListeners();
            this.updateTotals();
        }
    },

    /**
     * Attach event listeners to inputs
     */
    attachEventListeners() {
        const rows = document.querySelectorAll('#budgetLinesBody tr[data-line-index]');

        rows.forEach((row, index) => {
            const inputs = row.querySelectorAll('input, select');

            inputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    const field = e.target.dataset.field;
                    let value = e.target.value;

                    // Convert numeric fields
                    if (['quantity', 'price_per_unit', 'year_0_1', 'year_1_2', 'year_3_5', 'year_5_10', 'year_10_plus'].includes(field)) {
                        value = parseFloat(value) || 0;
                    }

                    this.lines[index][field] = value;
                });
            });
        });
    },

    /**
     * Update totals
     */
    updateTotals() {
        let totalCapex = 0;
        let totalYear01 = 0;
        let totalYear12 = 0;
        let totalYear35 = 0;
        let totalYear510 = 0;

        this.lines.forEach(line => {
            const qty = parseFloat(line.quantity) || 0;
            const price = parseFloat(line.price_per_unit) || 0;
            totalCapex += qty * price;

            totalYear01 += parseFloat(line.year_0_1) || 0;
            totalYear12 += parseFloat(line.year_1_2) || 0;
            totalYear35 += parseFloat(line.year_3_5) || 0;
            totalYear510 += parseFloat(line.year_5_10) || 0;
        });

        // Update footer
        const formatNumber = (num) => new Intl.NumberFormat('da-DK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);

        document.getElementById('totalCapex').innerHTML = `<strong>${formatNumber(totalCapex)}</strong>`;
        document.getElementById('totalYear01').textContent = formatNumber(totalYear01);
        document.getElementById('totalYear12').textContent = formatNumber(totalYear12);
        document.getElementById('totalYear35').textContent = formatNumber(totalYear35);
        document.getElementById('totalYear510').textContent = formatNumber(totalYear510);
    },

    /**
     * Show price catalog search modal
     */
    async showPriceCatalog() {
        const searchHTML = `
            <div class="price-catalog-search">
                <input type="text"
                       id="priceSearchInput"
                       class="form-control mb-3"
                       placeholder="Søg efter priser..."
                       onkeyup="BudgetModal.searchPrices(this.value)">
                <div id="priceSearchResults" style="max-height: 400px; overflow-y: auto;">
                    Indtast søgeord for at finde priser...
                </div>
            </div>
        `;

        Modal.show('Søg i priskatalog', searchHTML, {
            width: '600px'
        });
    },

    /**
     * Search prices in catalog
     */
    async searchPrices(query) {
        if (query.length < 2) {
            document.getElementById('priceSearchResults').innerHTML = 'Indtast mindst 2 tegn...';
            return;
        }

        try {
            const response = await fetch(`/api.php?action=search_price_catalog&q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.items.length > 0) {
                const html = `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Navn</th>
                                <th>Enhed</th>
                                <th>Pris</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.items.map(item => `
                                <tr>
                                    <td>
                                        <strong>${this.escapeHtml(item.name)}</strong><br>
                                        <small class="text-muted">${this.escapeHtml(item.description || '')}</small>
                                    </td>
                                    <td>${this.escapeHtml(item.unit)}</td>
                                    <td>${parseFloat(item.price_per_unit).toFixed(2)} kr</td>
                                    <td>
                                        <button class="btn-primary btn-sm" onclick="BudgetModal.addPriceToLine(${item.id}, '${this.escapeHtml(item.name)}', '${item.unit}', ${item.price_per_unit})">
                                            Tilføj
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
                document.getElementById('priceSearchResults').innerHTML = html;
            } else {
                document.getElementById('priceSearchResults').innerHTML = '<p class="text-muted">Ingen resultater fundet</p>';
            }
        } catch (error) {
            console.error('Price search error:', error);
            document.getElementById('priceSearchResults').innerHTML = '<p class="text-danger">Fejl ved søgning</p>';
        }
    },

    /**
     * Add price catalog item to budget
     */
    addPriceToLine(catalogId, name, unit, price) {
        this.lines.push({
            description: name,
            quantity: 1,
            unit: unit,
            price_per_unit: price,
            price_catalog_id: catalogId,
            year_0_1: 0,
            year_1_2: 0,
            year_3_5: 0,
            year_5_10: 0,
            year_10_plus: 0
        });

        Modal.close();
        this.refreshLines();
        Toast.success('Pris tilføjet til budget');
    },

    /**
     * Show templates modal
     */
    async showTemplates() {
        try {
            const response = await fetch(`/api.php?action=get_budget_templates`);
            const data = await response.json();

            if (data.success && data.templates.length > 0) {
                const html = `
                    <div class="templates-list">
                        ${data.templates.map(template => `
                            <div class="template-card" style="border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
                                <h4>${this.escapeHtml(template.name)}</h4>
                                <p class="text-muted">${this.escapeHtml(template.description || '')}</p>
                                <div class="mt-2">
                                    <span class="badge">${this.escapeHtml(template.category || 'General')}</span>
                                    ${template.is_public ? '<span class="badge badge-success">Offentlig</span>' : '<span class="badge badge-secondary">Privat</span>'}
                                </div>
                                <button class="btn-primary btn-sm mt-2" onclick="BudgetModal.loadTemplate(${template.id})">
                                    Indlæs template
                                </button>
                            </div>
                        `).join('')}
                    </div>
                `;

                Modal.show('Vælg budget template', html, {
                    width: '600px'
                });
            } else {
                Modal.show('Vælg budget template', '<p class="text-muted">Ingen templates tilgængelige</p>');
            }
        } catch (error) {
            console.error('Template load error:', error);
            Toast.error('Kunne ikke indlæse templates');
        }
    },

    /**
     * Load template into budget
     */
    async loadTemplate(templateId) {
        try {
            const formData = new FormData();
            formData.append('action', 'load_budget_template');
            formData.append('template_id', templateId);
            formData.append('element_id', this.elementId);
            formData.append('budget_type', this.budgetType);

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success(response.message || 'Template indlæst');
                Modal.close();

                // Reload budget lines
                await this.loadBudgetLines();
                this.refreshLines();
            } else {
                Toast.error(response.error || 'Kunne ikke indlæse template');
            }
        } catch (error) {
            console.error('Template load error:', error);
            Toast.error('Fejl ved indlæsning af template');
        }
    },

    /**
     * Save budget to backend
     */
    async save() {
        try {
            const formData = new FormData();
            formData.append('action', 'save_budget_lines');
            formData.append('element_id', this.elementId);
            formData.append('budget_type', this.budgetType);
            formData.append('lines', JSON.stringify(this.lines));

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Budget gemt');
                this.close();

                // Reload page to reflect updated totals
                window.location.reload();
            } else {
                Toast.error(response.error || 'Kunne ikke gemme budget');
            }
        } catch (error) {
            console.error('Budget save error:', error);
            Toast.error('Fejl ved gemning af budget');
        }
    },

    /**
     * Close modal
     */
    close() {
        const modal = document.getElementById('budgetModal');
        if (modal) {
            modal.remove();
        }
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Export globally
window.BudgetModal = BudgetModal;
