<?php require MODULES_DIR . '/Shared/layout_start.php'; ?>

<div class="container-fluid p-4">
    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i> Generer Rapport</h3>
        </div>
        <div class="card-body">
            <form action="?module=Report&action=renderFromTemplate" method="GET" target="_blank">
                <input type="hidden" name="module" value="Report">
                <input type="hidden" name="action" value="renderFromTemplate">

                <div class="mb-4">
                    <label class="form-label font-bold">1. Vælg Projekt</label>
                    <select name="project_id" class="form-control form-select" required size="10"
                        style="height: 200px;">
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['name']) ?>
                                (Oprettet:
                                <?= date('d/m/Y', strtotime($p['created_at'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label font-bold">2. Vælg Skabelon</label>
                    <select name="template_id" class="form-control form-select" required>
                        <option value="">-- Vælg Skabelon --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid gap-2 text-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-play"></i> Generer Rapport
                    </button>
                </div>
            </form>
        </div>
        <div class="card-footer text-muted">
            <a href="?module=Report&action=editor" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-edit"></i> Administrer Skabeloner
            </a>
        </div>
    </div>
</div>

<?php require MODULES_DIR . '/Shared/layout_end.php'; ?>