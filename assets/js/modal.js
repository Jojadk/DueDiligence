/**
 * Modal Management System
 * - Unique modal instance IDs
 * - Automatic DOM event cleanup
 * - Integration with ModalManager for collaboration system
 */

const Modal = {
    activeModals: [],
    modalIdCounter: 0,

    /**
     * Generate unique modal instance ID
     */
    generateInstanceId(size) {
        this.modalIdCounter++;
        return `modal_${size}_${Date.now()}_${this.modalIdCounter}`;
    },

    /**
     * Open a modal
     */
    open(content, options = {}) {
        if (!content) return null;

        const {
            size = 'medium',
            title = '',
            closeButton = true,
            backdrop = true,
            keyboard = true,
            onClose = null
        } = options;

        let modalId, contentId;
        switch (size) {
            case 'small':
                modalId = 'smallModal';
                contentId = 'smallModalContent';
                break;
            case 'large':
                modalId = 'largeModal';
                contentId = 'largeModalContent';
                break;
            default:
                modalId = 'globalModal';
                contentId = 'modalContent';
        }

        const modalEl = document.getElementById(modalId);
        const contentEl = document.getElementById(contentId);

        if (!modalEl || !contentEl) {
            if (window.logError) {
                window.logError(new Error('Modal element not found'), { modalId, contentId });
            }
            return null;
        }

        this.resetModalPosition(modalEl, contentEl);

        let modalHtml = '';

        if (title) {
            modalHtml += `
                <div class="modal-header">
                    <h2>${escapeHtml(title)}</h2>
                    ${closeButton ? `
                        <button type="button" class="btn-close" data-modal-close>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    ` : ''}
                </div>
            `;
        }

        modalHtml += content;

        contentEl.innerHTML = modalHtml;

        const instanceId = this.generateInstanceId(size);
        modalEl.dataset.instanceId = instanceId;

        modalEl.classList.add('active');
        document.body.classList.add('modal-open');

        if (typeof ModalManager !== 'undefined' && ModalManager.open) {
            ModalManager.open(instanceId);
        }

        if (backdrop) {
            const backdropEl = document.getElementById('backdrop');
            if (backdropEl) {
                backdropEl.classList.add('active');
                const backdropHandler = () => this.close(modalId);
                backdropEl.onclick = backdropHandler;
                modalEl.dataset.backdropHandler = 'attached';
            }
        }

        if (keyboard) {
            const keyHandler = (e) => {
                if (e.key === 'Escape') {
                    this.close(modalId);
                }
            };
            document.addEventListener('keydown', keyHandler);
            modalEl._keyHandler = keyHandler;
        }

        const closeButtons = contentEl.querySelectorAll('[data-modal-close]');
        if (closeButtons && closeButtons.length > 0) {
            closeButtons.forEach(btn => {
                if (btn) {
                    btn.onclick = () => this.close(modalId);
                }
            });
        }

        this.activeModals.push({
            id: modalId,
            instanceId: instanceId,
            onClose: onClose
        });

        return modalId;
    },

    close(modalId = null) {
        if (!modalId && this.activeModals.length > 0) {
            const lastModal = this.activeModals[this.activeModals.length - 1];
            modalId = lastModal.id;
        }

        if (!modalId) return;

        const modalEl = document.getElementById(modalId);
        if (!modalEl) return;

        const instanceId = modalEl.dataset.instanceId;

        // Remove keyboard handler
        if (modalEl._keyHandler) {
            document.removeEventListener('keydown', modalEl._keyHandler);
            delete modalEl._keyHandler;
        }

        modalEl.classList.remove('active');

        const modalInfo = this.activeModals.find(m => m.id === modalId);
        if (modalInfo && modalInfo.onClose) {
            modalInfo.onClose();
        }

        this.activeModals = this.activeModals.filter(m => m.id !== modalId);

        if (typeof ModalManager !== 'undefined' && ModalManager.close) {
            ModalManager.close(instanceId);
        }

        if (this.activeModals.length === 0) {
            const backdropEl = document.getElementById('backdrop');
            if (backdropEl) {
                backdropEl.classList.remove('active');
                backdropEl.onclick = null;
                delete backdropEl.dataset.backdropHandler;
            }
            document.body.classList.remove('modal-open');
        }

        setTimeout(() => {
            const contentId = modalId.replace('Modal', 'ModalContent');
            const contentEl = document.getElementById(contentId);
            if (contentEl) {
                contentEl.innerHTML = '';
            }
            delete modalEl.dataset.instanceId;
        }, 300);
    },

    closeAll() {
        while (this.activeModals.length > 0) {
            this.close();
        }
    },

    confirm(message, options = {}) {
        return new Promise((resolve) => {
            const {
                title = 'Bekræft',
                confirmText = 'Bekræft',
                cancelText = 'Annuller',
                confirmClass = 'btn-primary',
                cancelClass = 'btn-secondary'
            } = options;

            const content = `
                <div class="modal-body">
                    <p>${escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn ${cancelClass}" data-action="cancel">
                        ${escapeHtml(cancelText)}
                    </button>
                    <button type="button" class="btn ${confirmClass}" data-action="confirm">
                        ${escapeHtml(confirmText)}
                    </button>
                </div>
            `;

            const modalId = this.open(content, {
                size: 'small',
                title: title,
                closeButton: true,
                backdrop: true,
                keyboard: true,
                onClose: () => resolve(false)
            });

            const contentEl = document.getElementById('smallModalContent');
            if (!contentEl) {
                if (window.logError) {
                    window.logError(new Error('Content element not found for confirm dialog'));
                }
                resolve(false);
                return;
            }

            const confirmBtn = contentEl.querySelector('[data-action="confirm"]');
            const cancelBtn = contentEl.querySelector('[data-action="cancel"]');

            if (confirmBtn) {
                confirmBtn.onclick = () => {
                    this.close(modalId);
                    resolve(true);
                };
            }

            if (cancelBtn) {
                cancelBtn.onclick = () => {
                    this.close(modalId);
                    resolve(false);
                };
            }
        });
    },

    alert(message, options = {}) {
        return new Promise((resolve) => {
            const {
                title = 'Besked',
                buttonText = 'OK',
                buttonClass = 'btn-primary'
            } = options;

            const content = `
                <div class="modal-body">
                    <p>${escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn ${buttonClass}" data-action="ok">
                        ${escapeHtml(buttonText)}
                    </button>
                </div>
            `;

            const modalId = this.open(content, {
                size: 'small',
                title: title,
                closeButton: true,
                backdrop: true,
                keyboard: true,
                onClose: () => resolve()
            });

            const contentEl = document.getElementById('smallModalContent');
            if (!contentEl) {
                if (window.logError) {
                    window.logError(new Error('Content element not found for alert dialog'));
                }
                resolve();
                return;
            }

            const okBtn = contentEl.querySelector('[data-action="ok"]');

            if (okBtn) {
                okBtn.onclick = () => {
                    this.close(modalId);
                    resolve();
                };
            }
        });
    },

    resetModalPosition(modalEl, contentEl) {
        if (!modalEl || !contentEl) return;

        if (contentEl.style) {
            contentEl.style.transform = '';
            contentEl.style.top = '';
            contentEl.style.left = '';
        }
    }
};

window.Modal = Modal;
