<?php
// Shared Project Form Template (Wizard Style)
$project = $project ?? null;
$heating_options = $heating_options ?? ['Fjernvarme', 'Gas', 'Varmepumpe', 'El', 'Olie'];
$usage_options = $usage_options ?? ['Bolig', 'Erhverv', 'Sommerhus', 'Institution', 'Andet'];

$isEdit = !empty($project['id']);
?>

<div class="card max-w-lg project-form-container" style="max-width:800px; margin: 0 auto;">
    <div class="card-header">
        <h2>
            <?= $project ? 'Edit Project' : 'New Project' ?>
        </h2>
    </div>
    <div class="card-body" style="padding:10px; height:100%; display:flex; flex-direction:column;">
        <!-- Tabs use data-tab for delegation -->
        <div class="tabs" style="display:flex; margin-bottom:15px; border-bottom:1px solid #ccc;">
            <div class="tab-item active" data-tab-target="tab-general"
                onclick="ProjectModule.switchTab(this, 'tab-general')"
                style="padding:8px 15px; cursor:pointer; border-bottom:2px solid #007bff; font-weight:bold;">General
            </div>
            <div class="tab-item" data-tab-target="tab-details" onclick="ProjectModule.switchTab(this, 'tab-details')"
                style="padding:8px 15px; cursor:pointer; border-bottom:2px solid transparent;">Details</div>
            <div class="tab-item" data-tab-target="tab-team" onclick="ProjectModule.switchTab(this, 'tab-team')"
                style="padding:8px 15px; cursor:pointer; border-bottom:2px solid transparent;">Team</div>
            <?php if ($isEdit): ?>
                <div class="tab-item" data-tab-target="tab-versions" onclick="ProjectModule.switchTab(this, 'tab-versions')"
                    style="padding:8px 15px; cursor:pointer; border-bottom:2px solid transparent;">Versions</div>
                <div class="tab-item" data-tab-target="tab-report" onclick="ProjectModule.switchTab(this, 'tab-report')"
                    style="padding:8px 15px; cursor:pointer; border-bottom:2px solid transparent;">Report</div>
            <?php endif; ?>
        </div>

        <form class="project-form" id="project-form" enctype="multipart/form-data" method="POST"
            action="?module=Project&action=update" onsubmit="event.preventDefault(); ProjectModule.saveProject(event)"
            style="flex:1; overflow-y:auto; display:flex; flex-direction:column;">
            <input type="hidden" name="ajax" value="1">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $project['id'] ?>">
            <?php endif; ?>

            <!-- Tab 1: General -->
            <div id="tab-general" class="tab-content" style="display:block;">
                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="project.name">Project Name</label>
                    <input type="text" name="name" class="form-input w-full"
                        value="<?= \Core\Utility::e($project['name'] ?? '') ?>"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="project.client">Client Name</label>
                    <div style="position:relative;">
                        <input type="text" name="client_name" class="form-input w-full" id="client-search-input"
                            value="<?= \Core\Utility::e($project['client_name'] ?? '') ?>"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"
                            autocomplete="off" placeholder="Type to search customers..."
                            data-i18n-placeholder="common.search">
                        <input type="hidden" name="client_id" value="<?= $project['client_id'] ?? '' ?>">
                        <div class="client-results"
                            style="position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ccc; z-index:10; display:none; max-height:200px; overflow-y:auto;">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="project.status">Status</label>
                    <select name="status" class="form-input w-full"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                        <?php $s = $project['status'] ?? 'planning'; ?>
                        <option value="planning" <?= $s == 'planning' ? 'selected' : '' ?>>Planning</option>
                        <option value="active" <?= $s == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $s == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="archived" <?= $s == 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold" data-i18n="project.created">Start Date</label>
                    <input type="date" name="start_date" class="form-input w-full"
                        value="<?= \Core\Utility::e($project['start_date'] ?? date('Y-m-d')) ?>"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold">Cover Image</label>
                    <?php if (!empty($project['cover_image'])): ?>
                        <div style="margin-bottom:5px; position:relative; display:inline-block;">
                            <img src="/assets/uploads/<?= \Core\Utility::e($project['cover_image']) ?>?t=<?= time() ?>"
                                style="height:100px; border-radius:4px; border:1px solid #ddd;">
                            <button type="button" class="btn btn-sm btn-light"
                                style="position:absolute; top:2px; right:2px; padding:2px 6px; font-size:11px; opacity:0.9;"
                                onclick="ProjectModule.editCoverImage(<?= $project['id'] ?>, '/assets/uploads/<?= \Core\Utility::e($project['cover_image']) ?>')"
                                title="Edit Image">
                                ✏️
                            </button>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="cover_image" class="form-input w-full" accept="image/*"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    <small style="color:#666;">Used for Report Cover.</small>
                </div>
            </div>

            <!-- Tab 2: Details -->
            <div id="tab-details" class="tab-content" style="display:none;">
                <div class="row" style="display:flex; gap:10px; margin-bottom:10px;">
                    <div class="form-group" style="flex:1;">
                        <label class="block mb-1 font-bold">BBR Number</label>
                        <input type="text" name="bbr_number" class="form-input w-full"
                            value="<?= \Core\Utility::e($project['bbr_number'] ?? '') ?>"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="block mb-1 font-bold">Area (m2)</label>
                        <input type="number" name="area_m2" class="form-input w-full"
                            value="<?= \Core\Utility::e($project['area_m2'] ?? '') ?>"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>

                <div class="row" style="display:flex; gap:10px; margin-bottom:10px;">
                    <div class="form-group" style="flex:1;">
                        <label class="block mb-1 font-bold">Heating Type</label>
                        <select name="heating_type" class="form-input w-full"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            <option value="">Select Heating...</option>
                            <?php foreach ($heating_options as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($project['heating_type'] ?? '') == $opt ? 'selected' : '' ?>>
                                    <?= $opt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="block mb-1 font-bold">Usage Type</label>
                        <select name="usage_type" class="form-input w-full"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            <option value="">Select Usage...</option>
                            <?php foreach ($usage_options as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($project['usage_type'] ?? '') == $opt ? 'selected' : '' ?>>
                                    <?= $opt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row" style="display:flex; gap:10px; margin-bottom:10px;">
                    <div class="form-group" style="flex:1;">
                        <label class="block mb-1 font-bold">Built Date</label>
                        <input type="date" name="construction_year" class="form-input w-full"
                            value="<?= \Core\Utility::e($project['construction_year'] ?? '') ?>"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="block mb-1 font-bold">Renovated Date</label>
                        <input type="date" name="renovation_year" class="form-input w-full"
                            value="<?= \Core\Utility::e($project['renovation_year'] ?? '') ?>"
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="block mb-1 font-bold">Inspection Date (Besigtigelse)</label>
                    <input type="date" name="inspection_date" class="form-input w-full"
                        value="<?= \Core\Utility::e($project['inspection_date'] ?? date('Y-m-d')) ?>"
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <!-- Custom Fields -->
                <?php if (!empty($customFields)): ?>
                    <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                        <h4 style="margin-bottom:10px; font-size:1.1rem; color:#2c3e50;">Custom Fields</h4>
                        <?php foreach ($customFields as $field):
                            $val = $customFieldValues[$field['id']] ?? $field['default_value'] ?? '';
                            ?>
                            <div class="form-group mb-3">
                                <label class="block mb-1 font-bold"><?= \Core\Utility::e($field['label']) ?></label>
                                <?php if ($field['type'] === 'textarea'): ?>
                                    <textarea name="custom_fields[<?= $field['id'] ?>]" class="form-input w-full" rows="3"
                                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= \Core\Utility::e($val) ?></textarea>
                                <?php elseif ($field['type'] === 'select'):
                                    $opts = explode(',', $field['options']);
                                    ?>
                                    <select name="custom_fields[<?= $field['id'] ?>]" class="form-input w-full"
                                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                        <option value="">-- Select --</option>
                                        <?php foreach ($opts as $opt):
                                            $opt = trim($opt); ?>
                                            <option value="<?= $opt ?>" <?= $val == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="custom_fields[<?= $field['id'] ?>]" class="form-input w-full"
                                        value="<?= \Core\Utility::e($val) ?>"
                                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab 3: Team -->
            <div id="tab-team" class="tab-content" style="display:none;">
                <div class="alert alert-info"
                    style="background:#e0f7fa; padding:10px; margin-bottom:15px; border-radius:4px;"><small>Add team
                        members to this project.</small></div>
                <div id="team-list" style="border:1px solid #eee; min-height:100px; padding:10px; margin-bottom:10px;">
                    <small style="color:#888;">Loading team...</small>
                </div>
                <div style="display:flex; gap:5px; position:relative;">
                    <input type="text" id="user-search-input" placeholder="Search user..."
                        style="flex:1; padding:6px; border:1px solid #ccc; border-radius:4px;"
                        data-i18n-placeholder="common.search">
                    <div class="search-results"
                        style="position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ccc; z-index:10; display:none;">
                    </div>
                </div>
            </div>

            <!-- Tab 4: Versions -->
            <?php if ($isEdit): ?>
                <div id="tab-versions" class="tab-content" style="display:none;">
                    <div style="margin-bottom:15px; display:flex; gap:10px;">
                        <button type="button" class="btn btn-primary btn-sm"
                            onclick="ProjectModule.createSnapshot(<?= $project['id'] ?>)">
                            📸 Opret Snapshot
                        </button>
                    </div>
                    <div id="snapshot-list"
                        style="border:1px solid #eee; min-height:100px; padding:10px; max-height:400px; overflow-y:auto;">
                        <small>Loading snapshots...</small>
                    </div>
                </div>
        </div>
    <?php endif; ?>

    <!-- Tab 5: Report Settings -->
    <div id="tab-report" class="tab-content" style="display:none;">
        <div class="form-group mb-3">
            <label class="block mb-1 font-bold">Disclaimer / Introduction</label>
            <textarea name="report_intro" class="form-input w-full" rows="6"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:monospace;"><?= \Core\Utility::e($project['report_intro'] ?? "On behalf of [Client] Sweco Denmark have conducted a Technical Due Diligence (TDD) Red flag & Finding's assessment of [Property].\nThe TDD has been carried out thru site visit and desktop survey, by performing a review of the relevant material in the project data room.") ?></textarea>
            <small>Displayed on the Introduction page.</small>
        </div>
        <div class="form-group mb-3">
            <label class="block mb-1 font-bold">Definitions</label>
            <textarea name="report_disclaimer" class="form-input w-full" rows="6"
                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:monospace;"><?= \Core\Utility::e($project['report_disclaimer'] ?? "The technical review of the property/project's building elements is based on Molio's price data classification of main areas within maintenance. For outdoor areas, indoor areas, building envelope, and technical installations.\nThe pricing for repairs and improvements (defined as CAPEX) is based on Molio's price data and Sweco's empirical figures.\n\nSymbols:\n🚩 Red flag: Technical observations that have already caused or will cause failure.\n🟨 Major Condition: Failure in long term.\n⬛ Not comprehensive to law.\n🟦 Further investigation needed.") ?></textarea>
            <small>Displayed on the Definitions page.</small>
        </div>
    </div>

    <div class="flex justify-end gap-2 mt-4"
        style="margin-top:auto; padding-top:15px; border-top:1px solid #eee; display:flex; justify-content:space-between;">
        <?php
        $canDelete = \Core\Auth::hasPermission('project_delete');
        if ($isEdit && !$canDelete && !empty($project['team_members_list'])) {
            $currentUserId = \Core\Auth::user()['id'];
            foreach ($project['team_members_list'] as $tm) {
                if ($tm['id'] == $currentUserId && ($tm['role'] ?? '') === 'owner') {
                    $canDelete = true;
                    break;
                }
            }
        }
        ?>
        <?php if ($isEdit && $canDelete): ?>
            <button type="button" class="btn btn-danger" id="btn-delete-project"
                onclick="deleteProject(<?= $project['id'] ?>, '<?= \Core\Utility::e($project['name']) ?>')"
                data-project-id="<?= $project['id'] ?>" data-project-name="<?= \Core\Utility::e($project['name']) ?>"
                style="background:#dc3545; color:white; border:none; padding:8px 12px; border-radius:4px;"
                data-i18n="common.delete">Delete</button>
        <?php else: ?>
            <div></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"
            style="background:#007bff; color:white; border:none; padding:8px 12px; border-radius:4px;"
            data-i18n="common.save">Save
            Project</button>
    </div>
    </form>
</div>
</div>

<script>
    // Load Project Module JS if not loaded
    App.loadScript('assets/js/modules/project.js?v=<?= time() ?>').then(() => {
        // Init Team List if needed
        <?php if (!empty($project['team_members_list'])): ?>
            ProjectModule.setTeamMembers(<?= json_encode($project['team_members_list']) ?>);
        <?php endif; ?>
    });
</script>