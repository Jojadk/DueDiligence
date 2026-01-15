class WindowManager {
    constructor(containerId = 'desktop-area', dockId = 'wm-dock') {
        this.container = document.body; // Default to body for full screen
        this.dock = document.getElementById(dockId);
        this.windows = {};
        this.zIndexCounter = 100;
        this.activeWindowId = null;

        // Ensure dock exists if passed ID not found?
        if (!this.dock) {
            this.createDock(dockId);
        }
    }

    createDock(id) {
        const dock = document.createElement('div');
        dock.id = id;
        document.body.appendChild(dock);
        this.dock = dock;
    }

    createWindow(options) {
        const id = options.id || 'win-' + Math.random().toString(36).substr(2, 9);

        // If window exists, bring to front
        if (this.windows[id]) {
            this.restore(id);
            this.focus(id);
            return;
        }

        const title = options.title || 'Window';
        const content = options.content || ''; // HTML or Iframe URL?
        const isUrl = options.url ? true : false;
        // Constraints: Max 2/3 screen size unless maximized
        const maxW = window.innerWidth * (2 / 3);
        const maxH = window.innerHeight * (2 / 3);
        const width = Math.min(options.width || 600, maxW);
        const height = Math.min(options.height || 400, maxH);

        let x = options.x;
        let y = options.y;

        if (x === 'center') x = Math.max(0, (window.innerWidth - width) / 2);
        if (y === 'center') y = Math.max(0, (window.innerHeight - height) / 2);

        if (x === undefined || x === null) x = 100 + (Object.keys(this.windows).length * 30);
        if (y === undefined || y === null) y = 100 + (Object.keys(this.windows).length * 30);

        // Window DOM
        const win = document.createElement('div');
        win.id = id;
        win.className = 'wm-window active';
        win.style.width = width + 'px';
        win.style.height = height + 'px';
        win.style.left = x + 'px';
        win.style.top = y + 'px';
        win.style.zIndex = ++this.zIndexCounter;

        // Header
        const header = document.createElement('div');
        header.className = 'wm-header';

        // Controls
        const controls = document.createElement('div');
        controls.className = 'wm-controls';

        const closeBtn = document.createElement('button');
        closeBtn.className = 'wm-btn wm-close';
        closeBtn.onclick = (e) => { e.stopPropagation(); this.close(id); };

        const minBtn = document.createElement('button');
        minBtn.className = 'wm-btn wm-min';
        minBtn.onclick = (e) => { e.stopPropagation(); this.minimize(id); };

        const maxBtn = document.createElement('button');
        maxBtn.className = 'wm-btn wm-max';
        maxBtn.onclick = (e) => { e.stopPropagation(); this.maximize(id); };

        controls.appendChild(closeBtn);
        controls.appendChild(minBtn);
        controls.appendChild(maxBtn);

        const titleSpan = document.createElement('span');
        titleSpan.className = 'wm-title';
        titleSpan.innerText = title;

        header.appendChild(controls);
        header.appendChild(titleSpan);

        // Body
        const body = document.createElement('div');
        body.className = 'wm-body';

        if (isUrl) {
            const iframe = document.createElement('iframe');
            iframe.src = options.url;
            body.appendChild(iframe);
            // Iframe steals mouse events, overlay needed for drag?
            // Advanced: Add transparent overlay on drag start
        } else {
            body.innerHTML = content;
        }

        win.appendChild(header);
        win.appendChild(body);

        // Add resize handle
        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'wm-resize-handle';
        resizeHandle.style.cssText = 'position:absolute; bottom:0; right:0; width:15px; height:15px; cursor:nwse-resize; background:linear-gradient(135deg, transparent 50%, #999 50%);';
        win.appendChild(resizeHandle);

        this.container.appendChild(win);
        this.windows[id] = { element: win, minimized: false, maximized: false, rect: null };

        // Interaction
        this.makeDraggable(win, header);
        this.makeResizable(win, resizeHandle);
        win.addEventListener('mousedown', () => this.focus(id));

        // Double-click header to maximize
        header.addEventListener('dblclick', (e) => {
            if (!e.target.classList.contains('wm-btn')) {
                this.maximize(id);
            }
        });

        // Dock Icon
        this.addDockIcon(id, title);

        this.focus(id);
    }

    close(id) {
        if (this.windows[id]) {
            this.windows[id].element.remove();
            this.removeDockIcon(id);
            delete this.windows[id];
        }
    }

    // Alias for backwards compatibility
    closeWindow(id) {
        return this.close(id);
    }


    minimize(id) {
        const win = this.windows[id];
        if (win) {
            win.rect = win.element.getBoundingClientRect(); // Save pos? Styles?
            win.element.style.display = 'none'; // Simple hide
            win.minimized = true;
            this.updateDockStatus(id);
        }
    }

    restore(id) {
        const win = this.windows[id];
        if (win && win.minimized) {
            win.element.style.display = 'flex';
            win.minimized = false;
            this.focus(id);
            this.updateDockStatus(id);
        }
    }

    maximize(id) {
        const win = this.windows[id];
        if (!win) return;

        if (win.maximized) {
            // Restore
            win.element.style.width = win.preMax.width;
            win.element.style.height = win.preMax.height;
            win.element.style.left = win.preMax.left;
            win.element.style.top = win.preMax.top;
            win.maximized = false;
        } else {
            // Maximize
            win.preMax = {
                width: win.element.style.width,
                height: win.element.style.height,
                left: win.element.style.left,
                top: win.element.style.top
            };
            win.element.style.width = '100%';
            win.element.style.height = 'calc(100% - 80px)'; // Leave space for dock
            win.element.style.left = '0';
            win.element.style.top = '0';
            win.maximized = true;
        }
    }

    focus(id) {
        this.activeWindowId = id;
        if (this.windows[id]) {
            this.windows[id].element.style.zIndex = ++this.zIndexCounter;
            // Also highlight dock?
        }
    }

    makeDraggable(element, handle) {
        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        handle.onmousedown = (e) => {
            if (e.target.tagName === 'BUTTON') return; // Don't drag on button click

            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            const rect = element.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            // iframe fix
            document.querySelectorAll('iframe').forEach(f => f.style.pointerEvents = 'none');

            document.onmousemove = (e) => {
                if (!isDragging) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                element.style.left = (initialLeft + dx) + 'px';
                element.style.top = Math.max(0, initialTop + dy) + 'px';
            };

            document.onmouseup = () => {
                isDragging = false;
                document.onmousemove = null;
                document.onmouseup = null;
                document.querySelectorAll('iframe').forEach(f => f.style.pointerEvents = 'auto');
            };
        };
    }

    makeResizable(element, handle) {
        let isResizing = false;
        let startX, startY, startWidth, startHeight;

        handle.onmousedown = (e) => {
            e.stopPropagation(); // Don't trigger window drag
            isResizing = true;
            startX = e.clientX;
            startY = e.clientY;
            startWidth = parseInt(element.style.width);
            startHeight = parseInt(element.style.height);

            // Disable iframe pointer events during resize
            document.querySelectorAll('iframe').forEach(f => f.style.pointerEvents = 'none');

            document.onmousemove = (e) => {
                if (!isResizing) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;

                // Apply size constraints (Min 300/200, Max 2/3 screen)
                const newWidth = Math.min(Math.max(300, startWidth + dx), window.innerWidth * (2 / 3));
                const newHeight = Math.min(Math.max(200, startHeight + dy), window.innerHeight * (2 / 3));

                element.style.width = newWidth + 'px';
                element.style.height = newHeight + 'px';
            };

            document.onmouseup = () => {
                isResizing = false;
                document.onmousemove = null;
                document.onmouseup = null;
                document.querySelectorAll('iframe').forEach(f => f.style.pointerEvents = 'auto');
            };
        };
    }


    addDockIcon(id, title) {
        // Truncate title
        const shortTitle = title.length > 10 ? title.substring(0, 10) + '...' : title;

        const icon = document.createElement('div');
        icon.className = 'dock-item';
        icon.title = title; // Full title on hover
        icon.id = 'dock-' + id;
        icon.onclick = () => {
            if (this.windows[id].minimized) {
                this.restore(id);
            } else if (this.activeWindowId === id) {
                this.minimize(id);
            } else {
                this.focus(id);
            }
        };

        // Placeholder Icon with shortened title
        const img = document.createElement('div');
        img.innerText = shortTitle;
        img.style.fontSize = '10px';
        img.style.fontWeight = 'bold';
        img.style.textAlign = 'center';
        img.style.lineHeight = '12px';
        img.style.overflow = 'hidden';

        icon.appendChild(img);
        this.dock.appendChild(icon);
        this.updateDockVisibility();
    }

    removeDockIcon(id) {
        const icon = document.getElementById('dock-' + id);
        if (icon) icon.remove();
        this.updateDockVisibility();
    }

    updateDockVisibility() {
        // Show dock only if there are minimized windows? 
        // Or if there are ANY windows?
        // User asked: "#wm-dock skal kun vises såfremt et modal er minimeret"

        const hasMinimized = Object.values(this.windows).some(w => w.minimized);

        if (hasMinimized) {
            this.dock.style.display = 'flex';
        } else {
            this.dock.style.display = 'none';
        }
    }

    updateDockStatus(id) {
        // Add class .minimized to dock item if minimized
        const icon = document.getElementById('dock-' + id);
        if (icon) {
            if (this.windows[id].minimized) icon.classList.add('minimized');
            else icon.classList.remove('minimized');
        }
        this.updateDockVisibility();
    }
}

// Initialize Global WM
let wm;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        wm = new WindowManager();
    });
} else {
    wm = new WindowManager();
}
// Cache bust
