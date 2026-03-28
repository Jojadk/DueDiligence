<div class="card h-100 flex-column">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2>Report Template Editor</h2>
        <div>
            <button onclick="previewReport()" class="btn btn-secondary">Preview</button>
            <button onclick="saveReport()" class="btn btn-primary">Save Template</button>
        </div>
    </div>
    <div class="card-body p-0 d-flex flex-column" style="flex:1;">
        <div style="position:relative; flex:1; display:flex;">
            <textarea id="template-editor" class="form-control"
                style="flex:1; height:100%; border:none; resize:none; padding:1rem; font-family:monospace;"
                placeholder="Write your report template here... Use {{ for variables."></textarea>

            <div id="autocomplete-list" class="autocomplete-items" style="display:none;"></div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="preview-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="width: 800px; height: 80vh;">
        <span class="close-modal" onclick="document.getElementById('preview-modal').style.display='none'">&times;</span>
        <div id="preview-content" style="padding:2rem; background:white;"></div>
    </div>
</div>

<style>
    .autocomplete-items {
        position: absolute;
        border: 1px solid #d4d4d4;
        border-bottom: none;
        border-top: none;
        z-index: 99;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 200px;
        overflow-y: auto;
        background: white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        width: 300px;
    }

    .autocomplete-items div {
        padding: 10px;
        cursor: pointer;
        background-color: #fff;
        border-bottom: 1px solid #d4d4d4;
    }

    .autocomplete-items div:hover {
        background-color: #e9e9e9;
    }

    .active-item {
        background-color: DodgerBlue !important;
        color: #ffffff;
    }
</style>

<script>
    const variables = [
        '{{project_name}}', '{{project_code}}', '{{client_name}}', '{{report_date}}',
        '{{company_name}}', '{{company_vat}}',
        '{{foreach elements}}', '{{endforeach}}',
        '{{element.name}}', '{{element.location}}', '{{element.quantity}}',
        '{{element.unit_price}}', '{{element.total_price}}',
        '{{TOC}}'
    ];

    const textarea = document.getElementById('template-editor');
    const list = document.getElementById('autocomplete-list');
    let cursorPos = 0;

    textarea.addEventListener('input', function (e) {
        const val = this.value;
        const cursorPos = this.selectionStart;
        const lastTwoChars = val.substring(cursorPos - 2, cursorPos);

        if (lastTwoChars === '{{') {
            showAutocomplete(cursorPos);
        } else {
            closeAutocomplete();
        }
    });

    function showAutocomplete(pos) {
        list.innerHTML = '';
        list.style.display = 'block';
        // Position it near cursor (simplified: fix to bottom left of textarea for now, or use complex caret lib)
        // For this prototype, we'll position at top left of container fixed.
        // Ideally we calculate caret coordinates.
        const rect = textarea.getBoundingClientRect();
        list.style.top = (rect.top + 40) + 'px';
        list.style.left = (rect.left + 20) + 'px';

        variables.forEach(item => {
            const b = document.createElement("DIV");
            b.innerHTML = "<strong>" + item.substr(0, 2) + "</strong>";
            b.innerHTML += item.substr(2);
            b.addEventListener("click", function (e) {
                insertAtCursor(item);
                closeAutocomplete();
            });
            list.appendChild(b);
        });
    }

    function closeAutocomplete() {
        list.style.display = 'none';
        list.innerHTML = '';
    }

    function insertAtCursor(myValue) {
        // Remove the '{{' that triggered it if we want, or just append
        // We detected '{{', so maybe we just append the rest?
        // Let's replace the last '{{' if simpler, or just insert.
        // Let's just insert the variable name minus '{{'?
        // No, cleaner: replace '{{' with full tag?
        // Just insert full tag for now.

        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;

        // Check if we just typed {{
        const textBefore = textarea.value.substring(0, startPos);
        if (textBefore.endsWith('{{')) {
            textarea.value = textBefore.slice(0, -2) + myValue + textarea.value.substring(endPos, textarea.value.length);
        } else {
            textarea.value = textarea.value.substring(0, startPos) + myValue + textarea.value.substring(endPos, textarea.value.length);
        }
        textarea.focus();
    }

    function previewReport() {
        const content = textarea.value;
        fetch('?module=Report&action=preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template: content })
        })
            .then(res => res.text())
            .then(html => {
                document.getElementById('preview-content').innerHTML = html;
                document.getElementById('preview-modal').style.display = 'flex';
            });
    }

    function saveReport(name = 'Default Template') {
        const content = textarea.value;
        const btn = event.target; // Simple event capturing
        if (btn) btn.innerText = 'Saving...';

        fetch('?module=Report&action=save_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                template: content
            })
        })
            .then(res => res.json())
            .then(data => {
                if (btn) btn.innerText = 'Save Template';
                if (data.status === 'success') {
                    showToast('Template Saved!');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(err => {
                if (btn) btn.innerText = 'Save Template';
                showToast('Network Error', 'error');
            });
    }

    // Helper for Toast (reused from other modules or simple implementation)
    function showToast(msg, type = 'success') {
        const div = document.createElement('div');
        div.style.position = 'fixed';
        div.style.top = '20px';
        div.style.right = '20px';
        div.style.padding = '10px 20px';
        div.style.background = type === 'success' ? '#10b981' : '#ef4444';
        div.style.color = 'white';
        div.style.borderRadius = '5px';
        div.style.zIndex = 9999;
        div.innerText = msg;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }
</script>