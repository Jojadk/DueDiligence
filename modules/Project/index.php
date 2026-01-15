<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 data-i18n="nav.projects">Projects</h2>
        <?php if (\Core\Auth::hasPermission('project_create')): ?>
            <button id="btn-new-project" class="btn btn-primary" onclick="openProjectModal()" data-i18n="common.create">New
                Project</button>
        <?php endif; ?>
    </div>
    <!-- Filter Bar -->
    <div style="padding: 15px; border-bottom: 1px solid #eee; background: #f9f9f9;">
        <form method="GET" action="" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="module" value="Project">
            <input type="hidden" name="action" value="index">

            <input type="text" name="search" placeholder="Search projects..." data-i18n-placeholder="common.search"
                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                style="padding:8px; border:1px solid #ccc; border-radius:4px; min-width:200px;">

            <select name="status" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
                <option value="" data-i18n="project.status">All Statuses</option>
                <option value="planning" <?= ($_GET['status'] ?? '') == 'planning' ? 'selected' : '' ?>>Planning</option>
                <option value="active" <?= ($_GET['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= ($_GET['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed
                </option>
                <option value="archived" <?= ($_GET['status'] ?? '') == 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>

            <select name="heating" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
                <option value="">All Heating</option>
                <option value="Fjernvarme" <?= ($_GET['heating'] ?? '') == 'Fjernvarme' ? 'selected' : '' ?>>Fjernvarme
                </option>
                <option value="Gas" <?= ($_GET['heating'] ?? '') == 'Gas' ? 'selected' : '' ?>>Gas</option>
                <option value="Varmepumpe" <?= ($_GET['heating'] ?? '') == 'Varmepumpe' ? 'selected' : '' ?>>Varmepumpe
                </option>
            </select>

            <button type="submit" class="btn btn-secondary" data-i18n="common.search">Filter</button>
            <a href="?module=Project&action=index" style="color:#666; text-decoration:none; font-size:0.9rem;"
                data-i18n="common.cancel">Reset</a>
        </form>
    </div>

    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th data-i18n="project.name">Name</th>
                    <th data-i18n="project.client">Client</th>
                    <th data-i18n="project.status">Status</th>
                    <th data-i18n="project.created">Start Date</th>
                    <th data-i18n="common.edit">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                    <tr>
                        <td data-label="Name">
                            <?= htmlspecialchars($p['name']) ?>
                        </td>
                        <td data-label="Client">
                            <?= htmlspecialchars($p['client_name'] ?? '-') ?>
                        </td>
                        <td data-label="Status">
                            <span class="badge" style="background: 
                            <?= $p['status'] == 'active' ? '#dcfce7; color: #166534;' :
                                ($p['status'] == 'completed' ? '#dbeafe; color: #1e40af;' : '#f3f4f6') ?>">
                                <?= ucfirst($p['status']) ?>
                            </span>
                        </td>
                        <td data-label="Start Date">
                            <?= $p['start_date'] ?>
                        </td>
                        <td data-label="Actions">
                            <a href="?module=BuildingElement&action=index&project_id=<?= $p['id'] ?>" class="btn-sm"
                                style="background:var(--primary-color)">Elements</a>
                            <?php if (\Core\Auth::hasPermission('project_edit')): ?>
                                <a href="?module=Project&action=edit&id=<?= $p['id'] ?>" class="btn-sm">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Project Form Template (Hidden) -->
<div id="project-template" style="display:none">
    <?php
    $project = null;
    include 'modules/Project/ProjectForm.php';
    ?>
</div>

<script>
    // Load Project Module JS
    App.loadScript('assets/js/modules/utils.js');
    App.loadScript('assets/js/modules/project.js').then(() => {
        if (typeof ProjectModule !== 'undefined') {
            ProjectModule.init();
        }
    });

    // Open Create Modal
    window.openProjectModal = function () {
        const template = document.getElementById('project-template');
        const content = template.innerHTML;

        wm.createWindow({
            id: 'win-create-project',
            title: 'New Project',
            content: content,
            width: 650,
            height: 750
        });
        // No specific init needed as logic is delegated in ProjectModule
    };

    // Open Edit Modal
    window.openEditProjectModal = function (id) {
        const winId = 'win-edit-project-' + id;
        // Fetch via AJAX
        App.api('?module=Project&action=edit&ajax=1&id=' + id)
            .then(html => {
                wm.createWindow({
                    id: winId,
                    title: 'Edit Project #' + id,
                    content: html,
                    width: 650,
                    height: 750
                });
            });
    };
</script>