// TDD Construction Admin - Main Application Logic

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.DEV_MODE !== 'undefined' && window.DEV_MODE) {
        console.log('System initialized.');
    }

    // --- WebSocket-Ready Polling for Locks ---
    // Polls /?module=BuildingElement&action=heartbeat every 30s
    // Only if we are on a page that needs it (Building Elements)

    if (document.querySelector('.data-grid-locked')) { // Marker class
        setInterval(sendHeartbeat, 30000);
    }

    // Confirm Actions
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm(btn.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});

function sendHeartbeat() {
    // Collect active locks or just ping to say "I'm here" for this user
    // The backend clears locks > 60s. We just need to touch our active sessions.
    // For specific field locks, we should send { locked_ids: [...] }

    // We will look for locked inputs currently focused or owned by us
    const lockedInputs = document.querySelectorAll('.input-locked-by-me');
    const ids = Array.from(lockedInputs).map(el => el.dataset.lockId);

    if (ids.length > 0) {
        // Use fetch to send beacon-like request
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Original heartbeat fetch call
        fetch('?module=BuildingElement&action=heartbeat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify({ locks: ids })
        });
    }
}

// --- Drag & Drop Upload Handlers ---
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('tr[data-id]');

    rows.forEach(row => {
        row.addEventListener('dragover', (e) => {
            e.preventDefault();
            row.style.background = '#eff6ff'; // Highlight
        });

        row.addEventListener('dragleave', (e) => {
            e.preventDefault();
            row.style.background = '';
        });

        row.addEventListener('drop', (e) => {
            e.preventDefault();
            row.style.background = '';

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const elementId = row.dataset.id;
                uploadFiles(files, elementId);
            }
        });

        // Double Click to Open Canvas/Media Modal
        row.addEventListener('dblclick', (e) => {
            // Avoid triggering if clicked on input or link
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.tagName === 'SELECT') return;
            const elementId = row.dataset.id;
            openMediaModal(elementId);
        });
    });
});

// --- Canvas / Media Modal ---
let canvasEngine = null;

window.openMediaModal = function (elementId) {
    const modal = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');
    modal.style.display = 'flex';
    body.innerHTML = 'Loading...';

    // Fetch latest image for element
    // API endpoint needed to get media list
    fetch('?module=Canvas&action=get_media&element_id=' + elementId)
        .then(res => res.json())
        .then(data => {
            if (data && data.file_path) {
                body.innerHTML = `
                <h3>Image Editor</h3>
                <div class="toolbar">
                    <button onclick="canvasEngine.setTool('select')">Select</button>
                    <button onclick="canvasEngine.setTool('circle')">Circle</button>
                    <button onclick="canvasEngine.setTool('rect')">Rect</button>
                    <button onclick="canvasEngine.setTool('text')">Text</button>
                    <button onclick="saveCanvas(${data.id})" class="btn-primary">Save</button>
                </div>
                <div id="canvas-container" style="width:100%; border:1px solid #ccc; margin-top:10px;">
                    <canvas id="editorCanvas"></canvas>
                </div>
            `;
                // Init Engine
                const savedJson = data.canvas_json ? JSON.parse(data.canvas_json) : [];
                canvasEngine = new CanvasEngine('editorCanvas', data.file_path, savedJson);
            } else {
                body.innerHTML = '<p>No image found. Please drag and drop an image onto the row first.</p>';
            }
        });
}

window.closeModal = function () {
    document.getElementById('modal-overlay').style.display = 'none';
    canvasEngine = null;
}

// --- Notifications ---
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerText = message;

    container.appendChild(toast);

    // Auto remove
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// --- API Helpers ---
window.saveCanvas = function (mediaId) {
    if (!canvasEngine) return;
    const json = canvasEngine.save();

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    fetch('?module=Canvas&action=save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token
        },
        body: JSON.stringify({ media_id: mediaId, canvas_json: json })
    })
        .then(res => res.json())
        .then(data => {
            showToast('Canvas saved successfully!', 'success');
            closeModal();
        })
        .catch(err => showToast('Error saving canvas', 'error'));
}
// Cache bust
