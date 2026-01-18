/**
 * Modal Manager - Event dispatcher for modal state
 *
 * Automatically dispatches modalOpen/modalClose events that are
 * listened to by LockManager and LiveUpdateManager for intelligent
 * polling interval adjustments.
 *
 * Usage:
 *   ModalManager.open();   // Dispatch modalOpen event
 *   ModalManager.close();  // Dispatch modalClose event
 *
 * Or integrate with your existing modal system:
 *   $('#myModal').on('show.bs.modal', () => ModalManager.open());
 *   $('#myModal').on('hide.bs.modal', () => ModalManager.close());
 */

class ModalManager {
    static modalCount = 0;

    /**
     * Notify that a modal has opened
     */
    static open() {
        this.modalCount++;

        // Dispatch event only on first modal
        if (this.modalCount === 1) {
            const event = new CustomEvent('modalOpen', {
                detail: { count: this.modalCount }
            });
            document.dispatchEvent(event);
            console.log('Modal opened - count:', this.modalCount);
        }
    }

    /**
     * Notify that a modal has closed
     */
    static close() {
        this.modalCount = Math.max(0, this.modalCount - 1);

        // Dispatch event only when all modals are closed
        if (this.modalCount === 0) {
            const event = new CustomEvent('modalClose');
            document.dispatchEvent(event);
            console.log('All modals closed');
        }
    }

    /**
     * Check if any modal is open
     */
    static isOpen() {
        return this.modalCount > 0;
    }

    /**
     * Reset counter (use sparingly, e.g., on page navigation)
     */
    static reset() {
        const wasOpen = this.modalCount > 0;
        this.modalCount = 0;

        if (wasOpen) {
            const event = new CustomEvent('modalClose');
            document.dispatchEvent(event);
        }
    }
}

// Auto-detect Bootstrap modals if available
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal !== 'undefined') {
    jQuery(document).on('show.bs.modal', '.modal', function() {
        ModalManager.open();
    });

    jQuery(document).on('hide.bs.modal', '.modal', function() {
        ModalManager.close();
    });

    console.log('ModalManager: Auto-detected Bootstrap modals');
}

// Auto-detect native dialog elements
document.addEventListener('DOMContentLoaded', () => {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.tagName === 'DIALOG' && node.open) {
                    ModalManager.open();
                }
            });
            mutation.removedNodes.forEach((node) => {
                if (node.tagName === 'DIALOG') {
                    ModalManager.close();
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Also listen for dialog open/close events
    document.addEventListener('click', (e) => {
        if (e.target.tagName === 'DIALOG') {
            if (e.target.open) {
                ModalManager.open();
            } else {
                ModalManager.close();
            }
        }
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModalManager;
}
