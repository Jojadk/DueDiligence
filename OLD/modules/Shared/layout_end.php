</div> <!-- End Main Content -->
</div> <!-- End App Container -->

<!-- Common Modals -->
<div id="media-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content"
        style="width: 90%; max-width: 1000px; height: 90vh; padding:0; display:flex; flex-direction:column;">
        <div
            style="padding:1rem; border-bottom:1px solid #ccc; display:flex; justify-content:space-between; align-items:center;">
            <h3>Canvas Editor</h3>
            <div>
                <button onclick="window.saveCanvas(window.currentMediaId)" class="btn btn-primary">Save</button>
                <button onclick="closeModal()" class="btn btn-secondary">Close</button>
            </div>
        </div>
        <div style="flex:1; background:#333; position:relative; overflow:hidden;" id="canvas-wrapper">
            <canvas id="editor-canvas"></canvas>
        </div>
        <div style="padding:1rem; background:#f0f0f0; display:flex; gap:1rem;">
            <button onclick="canvasEngine.setMode('rect')">Rectangle</button>
            <button onclick="canvasEngine.setMode('circle')">Circle</button>
            <button onclick="canvasEngine.setMode('text')">Text</button>
            <button onclick="canvasEngine.clear()">Clear</button>
        </div>
    </div>
</div>

<!-- Dock -->
<div id="wm-dock"></div>

<!-- Generic Modal Template (Required by App.modal in core.js) -->
<template id="modal-template">
    <div id="dynamic-modal" class="modal-overlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100vh; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center;">
        <div class="modal-window"
            style="background:white; border-radius:8px; width:600px; max-width:90%; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:flex; flex-direction:column; max-height:90vh;">
            <div class="wm-header">
                <div class="wm-controls">
                    <button class="wm-btn wm-close" onclick="App.closeModal()"></button>
                </div>
                <span class="wm-title modal-title"></span>
            </div>
            <div class="modal-body" style="padding:20px; overflow-y:auto; flex:1;"></div>
            <div class="modal-footer"
                style="padding:15px 20px; border-top:1px solid #eee; text-align:right; display:flex; justify-content:flex-end; gap:10px; background:#f9f9f9; border-radius:0 0 8px 8px;">
            </div>
        </div>
    </div>
</template>

<!-- Scripts are loaded in layout_start.php -->
</body>

</html>