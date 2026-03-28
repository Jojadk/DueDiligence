/**
 * Core System Javascript
 * Handles Toast Notifications, AJAX helpers, and Modals
 */

const App = {
    // --- Dynamic Resource Loading ---
    loadCSS: function (href) {
        if (!document.querySelector(`link[href="${href}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            document.head.appendChild(link);
        }
    },

    loadScript: function (src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    },

    // --- Modal System (Template Based) ---
    modal: function (title, content, actions = []) {
        let modal = document.getElementById('dynamic-modal');
        if (!modal) {
            // Create from template if not exists in DOM
            const template = document.getElementById('modal-template');
            if (!template) { console.error('Modal template not found'); return; }
            const clone = template.content.cloneNode(true);
            document.body.appendChild(clone);
            modal = document.getElementById('dynamic-modal');
        }

        const titleEl = modal.querySelector('.modal-title');
        if (titleEl) titleEl.innerText = title;

        const bodyEl = modal.querySelector('.modal-body');
        if (bodyEl) bodyEl.innerHTML = content;

        const footer = modal.querySelector('.modal-footer');
        footer.innerHTML = '';

        // Add Close Button (default)
        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn btn-secondary';
        closeBtn.innerText = 'Luk';
        closeBtn.onclick = () => App.closeModal();
        footer.appendChild(closeBtn);

        actions.forEach(action => {
            const btn = document.createElement('button');
            btn.className = `btn btn-${action.type || 'primary'}`;
            btn.innerText = action.text;
            btn.onclick = action.onClick;
            footer.appendChild(btn);
        });

        modal.style.display = 'flex';

        // Make modal draggable by wm-header
        App.makeModalDraggable(modal);
    },

    makeModalDraggable: function (modal) {
        const header = modal.querySelector('.wm-header');
        const modalWindow = modal.querySelector('.modal-window');

        if (!header || !modalWindow) {
            console.warn('makeModalDraggable: header or modal-window not found');
            return;
        }

        if (header.dataset.draggableInitialized) return;

        header.dataset.draggableInitialized = 'true';
        header.style.cursor = 'move';

        let isDragging = false;
        let currentX, currentY, initialX, initialY;

        const dragStart = (e) => {
            // Don't drag if clicking on buttons
            if (e.target.closest('.wm-btn, button')) return;

            isDragging = true;
            header.style.cursor = 'grabbing';

            const rect = modalWindow.getBoundingClientRect();

            initialX = e.clientX - rect.left;
            initialY = e.clientY - rect.top;
        };

        const drag = (e) => {
            if (!isDragging) return;
            e.preventDefault();

            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;

            modalWindow.style.position = 'absolute';
            modalWindow.style.left = currentX + 'px';
            modalWindow.style.top = currentY + 'px';
            modalWindow.style.margin = '0';
        };

        const dragEnd = () => {
            isDragging = false;
            header.style.cursor = 'move';
        };

        header.addEventListener('mousedown', dragStart);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', dragEnd);
    },

    closeModal: function () {
        const modal = document.getElementById('dynamic-modal');
        if (modal) modal.style.display = 'none';
    },

    confirm: function (message, onConfirm) {
        App.modal('Bekræft handling', `<p>${message}</p>`, [
            { text: 'Bekræft', onClick: () => { onConfirm(); App.closeModal(); }, type: 'danger' }
        ]);
    },

    // Toast Notifications
    toast: function (message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) {
            const div = document.createElement('div');
            div.id = 'toast-container';
            div.style = 'position:fixed; bottom:20px; right:20px; z-index:99999; display:flex; flex-direction:column; gap:10px;';
            document.body.appendChild(div);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style = 'padding:15px; border-radius:8px; color:white; min-width:250px; box-shadow:0 4px 6px rgba(0,0,0,0.1); animation: slideIn 0.3s ease-out;';

        switch (type) {
            case 'success': toast.style.backgroundColor = '#2ecc71'; break;
            case 'error': toast.style.backgroundColor = '#e74c3c'; break;
            case 'warning': toast.style.backgroundColor = '#f1c40f'; toast.style.color = '#333'; break;
            default: toast.style.backgroundColor = '#3498db'; break;
        }

        toast.innerText = message;
        document.getElementById('toast-container').appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    // AJAX Helper
    api: async function (url, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {}
        };

        if (data) {
            if (data instanceof FormData) {
                // Let browser set Content-Type header with boundary
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }

        try {
            const response = await fetch(url, options);
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            console.error('API Error:', error);
            App.toast('Netværksfejl', 'error');
            throw error;
        }
    }
};

// Global Exposure for legacy parts
window.showToast = App.toast;
// Cache bust
