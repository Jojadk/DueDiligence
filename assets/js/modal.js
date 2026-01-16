/**
 * Modal Management System
 */

const Modal = {
    activeModals: [],

    /**
     * Open a modal
     */
    open(content, options = {}) {
        const {
            size = 'medium', // small, medium, large
            title = '',
            closeButton = true,
            backdrop = true,
            keyboard = true,
            onClose = null
        } = options;

        // Get modal container based on size
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
            console.error('Modal element not found');
            return null;
        }

        // Reset modal position to center
        this.resetModalPosition(modalEl, contentEl);

        // Build modal content
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

        // Show modal
        modalEl.classList.add('active');
        document.body.classList.add('modal-open');

        // Show backdrop
        if (backdrop) {
            const backdropEl = document.getElementById('backdrop');
            if (backdropEl) {
                backdropEl.classList.add('active');
                backdropEl.onclick = () => this.close(modalId);
            }
        }

        // Add escape key handler
        if (keyboard) {
            const keyHandler = (e) => {
                if (e.key === 'Escape') {
                    this.close(modalId);
                    document.removeEventListener('keydown', keyHandler);
                }
            };
            document.addEventListener('keydown', keyHandler);
        }

        // Add close button handlers
        const closeButtons = contentEl.querySelectorAll('[data-modal-close]');
        closeButtons.forEach(btn => {
            btn.onclick = () => this.close(modalId);
        });

        // Store modal info
        this.activeModals.push({
            id: modalId,
            onClose: onClose
        });

        return modalId;
    },

    /**
     * Close a modal
     */
    close(modalId = null) {
        // If no ID specified, close the most recent modal
        if (!modalId && this.activeModals.length > 0) {
            const lastModal = this.activeModals[this.activeModals.length - 1];
            modalId = lastModal.id;
        }

        if (!modalId) return;

        const modalEl = document.getElementById(modalId);
        if (!modalEl) return;

        // Hide modal
        modalEl.classList.remove('active');

        // Call onClose callback
        const modalInfo = this.activeModals.find(m => m.id === modalId);
        if (modalInfo && modalInfo.onClose) {
            modalInfo.onClose();
        }

        // Remove from active modals
        this.activeModals = this.activeModals.filter(m => m.id !== modalId);

        // Hide backdrop if no more modals
        if (this.activeModals.length === 0) {
            const backdropEl = document.getElementById('backdrop');
            if (backdropEl) {
                backdropEl.classList.remove('active');
                backdropEl.onclick = null;
            }
            document.body.classList.remove('modal-open');
        }

        // Clear content after animation
        setTimeout(() => {
            const contentId = modalId.replace('Modal', 'ModalContent');
            const contentEl = document.getElementById(contentId);
            if (contentEl) {
                contentEl.innerHTML = '';
            }
        }, 300);
    },

    /**
     * Close all modals
     */
    closeAll() {
        while (this.activeModals.length > 0) {
            this.close();
        }
    },

    /**
     * Confirm dialog
     */
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

            // Add button handlers
            const contentEl = document.getElementById('smallModalContent');
            const confirmBtn = contentEl.querySelector('[data-action="confirm"]');
            const cancelBtn = contentEl.querySelector('[data-action="cancel"]');

            confirmBtn.onclick = () => {
                this.close(modalId);
                resolve(true);
            };

            cancelBtn.onclick = () => {
                this.close(modalId);
                resolve(false);
            };
        });
    },

    /**
     * Alert dialog
     */
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

            // Add button handler
            const contentEl = document.getElementById('smallModalContent');
            const okBtn = contentEl.querySelector('[data-action="ok"]');

            okBtn.onclick = () => {
                this.close(modalId);
                resolve();
            };
        });
    },

    /**
     * Prompt dialog
     */
    prompt(message, options = {}) {
        return new Promise((resolve) => {
            const {
                title = 'Indtast værdi',
                defaultValue = '',
                placeholder = '',
                confirmText = 'OK',
                cancelText = 'Annuller'
            } = options;

            const content = `
                <div class="modal-body">
                    <p>${escapeHtml(message)}</p>
                    <input
                        type="text"
                        class="form-control"
                        id="promptInput"
                        value="${escapeHtml(defaultValue)}"
                        placeholder="${escapeHtml(placeholder)}"
                    >
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-action="cancel">
                        ${escapeHtml(cancelText)}
                    </button>
                    <button type="button" class="btn btn-primary" data-action="confirm">
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
                onClose: () => resolve(null)
            });

            // Add button handlers
            const contentEl = document.getElementById('smallModalContent');
            const input = contentEl.querySelector('#promptInput');
            const confirmBtn = contentEl.querySelector('[data-action="confirm"]');
            const cancelBtn = contentEl.querySelector('[data-action="cancel"]');

            // Focus input
            setTimeout(() => input.focus(), 100);

            confirmBtn.onclick = () => {
                const value = input.value;
                this.close(modalId);
                resolve(value);
            };

            cancelBtn.onclick = () => {
                this.close(modalId);
                resolve(null);
            };

            // Handle enter key
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    confirmBtn.click();
                }
            });
        });
    },

    /**
     * Reset modal position to center
     */
    resetModalPosition(modalEl, contentEl) {
        // Remove any maximized state
        contentEl.classList.remove('modal-maximized');

        // Reset scroll position
        modalEl.scrollTop = 0;

        // Ensure modal content is centered
        // This happens automatically with CSS flexbox centering
        // But we can force a reflow to ensure positioning is recalculated
        void modalEl.offsetHeight;
    },

    /**
     * Maximize modal
     */
    maximize(modalId) {
        const contentId = modalId.replace('Modal', 'ModalContent');
        const contentEl = document.getElementById(contentId);

        if (contentEl) {
            contentEl.classList.add('modal-maximized');
        }
    },

    /**
     * Restore modal from maximized state
     */
    restore(modalId) {
        const modalEl = document.getElementById(modalId);
        const contentId = modalId.replace('Modal', 'ModalContent');
        const contentEl = document.getElementById(contentId);

        if (modalEl && contentEl) {
            // Reset to center when restoring
            this.resetModalPosition(modalEl, contentEl);
        }
    }
};

// Export Modal globally
window.Modal = Modal;
