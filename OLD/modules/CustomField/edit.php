<div class="card max-w-2xl mx-auto">
    <div class="card-header">
        <h2>Edit Custom Field:
            <?= htmlspecialchars($field['label']) ?>
        </h2>
    </div>
    <div class="card-body">
        <form action="?module=CustomField&action=update" method="POST" onsubmit="prepareOptions()">
            <input type="hidden" name="id" value="<?= $field['id'] ?>">

            <!-- Label -->
            <div class="form-group mb-4">
                <label class="block mb-1 font-bold">Field Label</label>
                <input type="text" name="label" class="form-input w-full" required
                    value="<?= htmlspecialchars($field['label']) ?>">
            </div>

            <!-- Type -->
            <div class="form-group mb-4">
                <label class="block mb-1 font-bold">Field Type</label>
                <select name="field_type" id="fieldType" class="form-input w-full" onchange="toggleOptionsBuilder()">
                    <option value="text" <?= $field['field_type'] == 'text' ? 'selected' : '' ?>>Text Input</option>
                    <option value="number" <?= $field['field_type'] == 'number' ? 'selected' : '' ?>>Number</option>
                    <option value="date" <?= $field['field_type'] == 'date' ? 'selected' : '' ?>>Date Picker</option>
                    <option value="select" <?= $field['field_type'] == 'select' ? 'selected' : '' ?>>Dropdown (Select)
                    </option>
                    <option value="checkbox" <?= $field['field_type'] == 'checkbox' ? 'selected' : '' ?>>Checkbox</option>
                    <option value="textarea" <?= $field['field_type'] == 'textarea' ? 'selected' : '' ?>>Text Area</option>
                </select>
            </div>

            <!-- Options Builder -->
            <div id="optionsBuilder" class="form-group mb-4 p-4 border rounded bg-gray-50" style="display:none;">
                <label class="block mb-1 font-bold">Dropdown Options</label>
                <div id="optionsList">
                    <!-- Dynamic inputs go here -->
                </div>
                <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addOptionInput()">+ Add
                    Option</button>
                <input type="hidden" name="options" id="optionsJson"
                    value="<?= htmlspecialchars($field['options'] ?? '') ?>">
            </div>

            <!-- Scope -->
            <div class="form-group mb-4">
                <label class="block mb-1 font-bold">Scope</label>
                <select name="scope" id="scopeSelect" class="form-input w-full" onchange="toggleProjectSelect()">
                    <option value="global" <?= $field['scope'] == 'global' ? 'selected' : '' ?>>Global (All Projects)
                    </option>
                    <option value="project" <?= $field['scope'] == 'project' ? 'selected' : '' ?>>Project Specific</option>
                </select>
            </div>

            <!-- Project Selector -->
            <div id="projectSelect" class="form-group mb-4" style="display:none;">
                <label class="block mb-1 font-bold">Select Project</label>
                <?php
                $currentProjectName = '';
                if ($field['project_id']) {
                    foreach ($projects as $p) {
                        if ($p['id'] == $field['project_id']) {
                            $currentProjectName = $p['name'] . ' [ID:' . $p['id'] . ']';
                            break;
                        }
                    }
                }
                ?>
                <input list="projectsList" id="projectInput" class="form-input w-full"
                    placeholder="Type to search project..." onchange="updateProjectId()"
                    value="<?= htmlspecialchars($currentProjectName) ?>">
                <datalist id="projectsList">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= htmlspecialchars($p['name']) ?> [ID:<?= $p['id'] ?>]"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="project_id" id="realProjectId" value="<?= $field['project_id'] ?>">
                <small class="text-gray-500">Search by name or code.</small>
            </div>

            <div class="form-actions flex justify-end gap-2 mt-6">
                <a href="?module=Admin&action=customfields" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Field</button>
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
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Options
        const existingOptions = document.getElementById('optionsJson').value;
        if (existingOptions) {
            try {
                const opts = JSON.parse(existingOptions);
                opts.forEach(val => addOptionInput(val));
            } catch (e) { console.error('Error parsing options JSON'); }
        }

        toggleOptionsBuilder();
        toggleProjectSelect();
    });

    function toggleOptionsBuilder() {
        const type = document.getElementById('fieldType').value;
        const builder = document.getElementById('optionsBuilder');
        if (type === 'select' || type === 'checkbox') {
            builder.style.display = 'block';
            // Don't auto-add inputs in Edit if existing, handled by init
            if (document.querySelectorAll('.option-input').length === 0 && !document.getElementById('optionsJson').value) {
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
            <input type="text" class="form-input option-input" placeholder="Option Value" value="${val.replace(/"/g, '&quot;')}">
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
            document.getElementById('realProjectId').value = '';
        }
    }

    function prepareOptions() {
        const inputs = document.querySelectorAll('.option-input');
        const values = Array.from(inputs).map(i => i.value).filter(v => v.trim() !== '');
        document.getElementById('optionsJson').value = JSON.stringify(values);
        updateProjectId();
    }
</script>