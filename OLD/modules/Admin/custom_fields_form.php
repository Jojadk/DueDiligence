<div class="card max-w-lg">
    <div class="card-header">
        <h2>Define Custom Field</h2>
    </div>
    <div class="card-body">
        <form action="?module=CustomField&action=store" method="POST">
            <div class="form-group">
                <label>Label</label>
                <input type="text" name="label" required>
            </div>

            <div class="form-group">
                <label>Entity Type</label>
                <select name="entity_type" required>
                    <option value="project">Project</option>
                    <option value="building_element">Building Element</option>
                </select>
            </div>

            <div class="form-group">
                <label>Scope</label>
                <select name="scope" onchange="toggleProject(this)">
                    <option value="global">Global</option>
                    <option value="project">Specific Project</option>
                </select>
            </div>

            <div class="form-group" id="project_select" style="display:none;">
                <label>Project ID</label>
                <input type="number" name="project_id" placeholder="ID">
            </div>

            <div class="form-group">
                <label>Field Type</label>
                <select name="field_type">
                    <option value="text">Text Input</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="dropdown">Dropdown</option>
                    <option value="checkbox">Checkbox</option>
                </select>
            </div>

            <div class="form-group">
                <label>Options (JSON for Dropdown, e.g. ["A","B"])</label>
                <textarea name="options" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="0">
            </div>

            <button type="submit" class="btn btn-primary">Save Field</button>
        </form>
    </div>
</div>

<script>
    function toggleProject(select) {
        document.getElementById('project_select').style.display = select.value === 'project' ? 'block' : 'none';
    }
</script>