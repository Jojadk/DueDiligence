<div class="card max-w-2xl mx-auto">
    <div class="card-header">
        <h2>Create Custom Field</h2>
    </div>
    <div class="card-body">
        <form action="?module=CustomField&action=store" method="POST" onsubmit="prepareOptions()">

            <!-- Label -->
            <div class="form-group mb-4">
                <label class="block mb-1 font-bold">Field Label</label>
                <input type="text" name="label" class="form-input w-full" required placeholder="e.g. Warranty Date">
            </div>

            <!-- Type -->
            <div class="form-group mb-4">
                <label class="block mb-1 font-bold">Field Type</label>
                <select name="field_type" id="fieldType" class="form-input w-full" onchange="toggleOptionsBuilder()">
                    <option value="text">Text Input</option>
                    <option value="number">Number</option>
                    <option value="date">Date Picker</option>
                    <option value="select">Dropdown (Select)</option>
                    <option value="checkbox">Checkbox</option>
                    <option value="textarea">Text Area</option>
                </select>
            </div>

            <!-- Options Builder (Hidden by default) -->
            <div id="optionsBuilder" class="form-group mb-4 p-4 border rounded bg-gray-50" style="display:none;">
                <label class="block mb-1 font-bold">Dropdown Options</label>
                <div id="optionsList">
                    <!-- Dynamic inputs go here -->
                </div>
                <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addOptionInput()">+ Add
                    Option</button>
                <input type="hidden" name="options" id="optionsJson">
            </div>

            <!-- Scope -->
            <div class="form-group mb-4">
                <label class="block mb-1 font-bold">Scope</label>
                <select name="scope" id="scopeSelect" class="form-input w-full" onchange="toggleProjectSelect()">
                    <option value="global">Global (All Projects)</option>
                    <option value="project">Project Specific</option>
                </select>
            </div>

            <!-- Project Selector -->
            <div id="projectSelect" class="form-group mb-4" style="display:none;">
                <label class="block mb-1 font-bold">Select Project</label>
                <!-- Searchable datalist -->
                <input list="projectsList" id="projectInput" class="form-input w-full"
                    placeholder="Type to search project..." onchange="updateProjectId()">
                <datalist id="projectsList">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= htmlspecialchars($p['name']) ?> [ID:<?= $p['id'] ?>]"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="project_id" id="realProjectId">
                <small class="text-gray-500">Search by name or code.</small>
            </div>

            <!-- Entity Type (Hidden/Fixed for now as per request building_element) -->
            <input type="hidden" name="entity_type" value="building_element">

            <div class="form-actions flex justify-end gap-2 mt-6">
                <a href="?module=Admin&action=customfields" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Field</button>
            </div>
        </form>
    </div>
</div>

<style>
    .option-row {
        display: flex;
        gap: 10px;
        margin-bottom: 5px;
    }

    .option-row input {
        flex: 1;
    }
</style>

<script>
    function toggleOptionsBuilder() {
        const type = document.getElementById('fieldType').value;
        const builder = document.getElementById('optionsBuilder');
        if (type === 'select' || type === 'checkbox') {
            builder.style.display = 'block';
            if (document.querySelectorAll('.option-input').length === 0) {
                addOptionInput();
                addOptionInput();
            }
        } else {
            builder.style.display = 'none';
        }
    }

    function addOptionInput(val = '') {
        const div = document.createElement('div');
        div.className = 'option-row';
        div.innerHTML = `
            <input type="text" class="form-input option-input" placeholder="Option Value" value="${val}">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">X</button>
        `;
        document.getElementById('optionsList').appendChild(div);
    }

    function toggleProjectSelect() {
        const scope = document.getElementById('scopeSelect').value;
        document.getElementById('projectSelect').style.display = scope === 'project' ? 'block' : 'none';
    }

    function updateProjectId() {
        const val = document.getElementById('projectInput').value;
        const match = val.match(/\[ID:(\d+)\]/);
        if (match) {
            document.getElementById('realProjectId').value = match[1];
        } else {
            // If user typed exact name but didn't pick option, or ID missing, 
            // try to resolve? Or just clear if invalid?
            // For now, clear if structure doesn't match
            document.getElementById('realProjectId').value = '';
        }
    }

    function prepareOptions() {
        // Handle Options
        const inputs = document.querySelectorAll('.option-input');
        const values = Array.from(inputs).map(i => i.value).filter(v => v.trim() !== '');
        document.getElementById('optionsJson').value = JSON.stringify(values);

        // Ensure Project ID is set (in case onchange didn't fire or form submitted via enter)
        updateProjectId();
    }
</script>