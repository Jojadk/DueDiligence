<?php if ($activeElement): ?>
    <div class="content-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h2 style="margin:0;" data-i18n="<?= htmlspecialchars($activeElement['location']) ?>">
                <?= htmlspecialchars($activeElement['location']) ?>
            </h2>
            <h1 style="margin:5px 0 0 0;" data-i18n="<?= htmlspecialchars($activeElement['name']) ?>">
                <?= htmlspecialchars($activeElement['name']) ?>
            </h1>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-sm btn-secondary" onclick="window.saveAndReload(<?= $activeElement['id'] ?>)"
                title="Gemmes automatisk, men reload genindlæser data" data-i18n="common.save">
                <i class="fas fa-save"></i> Gem
            </button>
            <button class="btn btn-sm btn-primary btn-edit-element" data-element-id="<?= $activeElement['id'] ?>"
                data-i18n="element.edit">
                <i class="fas fa-pencil-alt"></i> Rediger
            </button>
        </div>
    </div>

    <!-- Observation -->
    <div class="observation-box">
        <label class="box-label">OBSERVATION</label>
        <textarea class="box-textarea" name="description" onfocus="acquireLock(<?= $activeElement['id'] ?>, 'description')"
            oninput="handleInputAutoSave(<?= $activeElement['id'] ?>, 'description', this)"
            onblur="updateElementField(<?= $activeElement['id'] ?>, 'description', this.value)"
            placeholder="Beskriv observationen..."><?= htmlspecialchars($activeElement['description'] ?? '') ?></textarea>
    </div>

    <!-- Recommendation -->
    <div class="recommendation-box">
        <label class="box-label">ANBEFALING</label>
        <textarea class="box-textarea" name="recommendation"
            onfocus="acquireLock(<?= $activeElement['id'] ?>, 'recommendation')"
            oninput="handleInputAutoSave(<?= $activeElement['id'] ?>, 'recommendation', this)"
            onblur="updateElementField(<?= $activeElement['id'] ?>, 'recommendation', this.value)"
            placeholder="Anbefalet handling..."><?= htmlspecialchars($activeElement['recommendation'] ?? '') ?></textarea>
    </div>

    <!-- Status Panel (Inline) -->
    <div class="status-panel"
        style="margin-top:20px; padding:15px; background:#343a40; border-radius:8px; display:flex; gap:20px; flex-wrap:wrap;">
        <div class="status-indicator"
            style="flex:1; margin-right:20px; border-right:1px solid rgba(255,255,255,0.1); padding-right:20px;">
            <label style="color:#d1d5db; font-size:0.8rem; display:block; margin-bottom:5px;">RISIKO</label>
            <div style="background:white; border-radius:4px; padding:2px; padding-left:2px;">
                <select class="form-control" name="risk_level"
                    style="background:white; color:#333; border:none; width:100%; height:32px; font-weight:500;"
                    onfocus="acquireLock(<?= $activeElement['id'] ?>, 'risk_level')"
                    onchange="updateElementField(<?= $activeElement['id'] ?>, 'risk_level', this.value)">
                    <option value="Ikke relevant" <?= ($activeElement['risk_level'] ?? 'Ikke relevant') == 'Ikke relevant' || empty($activeElement['risk_level']) ? 'selected' : '' ?>>⚪️ Ikke relevant</option>
                    <option value="Rød" <?= ($activeElement['risk_level'] ?? '') == 'Rød' ? 'selected' : '' ?>>🔴 RØD (Kritisk)
                    </option>
                    <option value="Gul" <?= ($activeElement['risk_level'] ?? '') == 'Gul' ? 'selected' : '' ?>>🟡 GUL
                        (Opmærksomhed)</option>
                    <option value="Grøn" <?= ($activeElement['risk_level'] ?? '') == 'Grøn' ? 'selected' : '' ?>>🟢 GRØN (God)
                    </option>
                    <option value="Sort" <?= ($activeElement['risk_level'] ?? '') == 'Sort' ? 'selected' : '' ?>>⚫️ SORT
                        (Skrot)</option>
                    <option value="Blå" <?= ($activeElement['risk_level'] ?? '') == 'Blå' ? 'selected' : '' ?>>🔵 BLÅ
                        (Undersøgelse)</option>
                </select>
            </div>
        </div>

        <div class="budget-breakdown-panel"
            style="flex:0 0 160px; display:flex; flex-direction:column; justify-content:center; gap:4px; margin-right:20px; border-right:1px solid rgba(255,255,255,0.1); padding-right:20px;">
            <!-- <1 år -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                <span style="color:#f5c6cb;">&lt;1 år:</span>
                <strong style="color:#fff;"
                    id="main-sum-t1"><?= number_format($activeElement['sum_0_1'] ?? 0, 2, ',', '.') ?></strong>
            </div>
            <!-- 1-2 år -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                <span style="color:#ffeeba;">1-2 år:</span>
                <strong style="color:#fff;"
                    id="main-sum-t2"><?= number_format($activeElement['sum_1_2'] ?? 0, 2, ',', '.') ?></strong>
            </div>
            <!-- 3-5 år -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                <span style="color:#c3e6cb;">3-5 år:</span>
                <strong style="color:#fff;"
                    id="main-sum-t3"><?= number_format($activeElement['sum_3_5'] ?? 0, 2, ',', '.') ?></strong>
            </div>
            <!-- 5-10 år -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                <span style="color:#bee5eb;">5-10 år:</span>
                <strong style="color:#fff;"
                    id="main-sum-t4"><?= number_format($activeElement['sum_5_10'] ?? 0, 2, ',', '.') ?></strong>
            </div>
        </div>

        <div class="meta-group" style="flex:1;">
            <label style="color:white; font-size:0.8rem; display:block; margin-bottom:5px;">CAPEX (DKK)</label>
            <div style="display:flex; gap:5px;">
                <input type="text" id="capex-display" class="dark-input" name="capex"
                    value="<?= number_format($activeElement['capex'] ?? 0, 2, ',', '.') ?>"
                    onfocus="acquireLock(<?= $activeElement['id'] ?>, 'capex')"
                    oninput="handleInputAutoSave(<?= $activeElement['id'] ?>, 'capex', this)"
                    onblur="updateElementField(<?= $activeElement['id'] ?>, 'capex', this.value)"
                    style="text-align:right; flex:1; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:white; padding:5px;">
                <button class="btn btn-sm btn-secondary" onclick="openBudgetModal(<?= $activeElement['id'] ?>)"><i
                        class="fas fa-calculator"></i></button>
            </div>
            <!-- BCL Checkbox -->
            <div style="margin-top:5px; text-align:right;">
                <label style="color:white; font-size:0.8rem; cursor:pointer; display:inline-flex; align-items:center;">
                    <input type="checkbox" name="is_bcl" style="margin-right:5px;" <?= ($activeElement['is_bcl'] ?? 0) ? 'checked' : '' ?>
                        onchange="updateElementField(<?= $activeElement['id'] ?>, 'is_bcl', this.checked ? 1 : 0)">
                    BCL
                </label>
            </div>
        </div>
    </div>

    <!-- Images -->
    <div class="image-grid" id="sortable-images"
        style="margin-top:20px; display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;"
        data-element-id="<?= $activeElement['id'] ?>" data-project-id="<?= $project['id'] ?>">
        <?php
        // Safety: Ensure $media is always an array
        if (!isset($media) || !is_array($media)) {
            $media = [];
        }

        // 1. Render Filled (Sortable) Items
        foreach ($media as $m):
            ?>
            <div class="media-item" draggable="true" data-media-id="<?= $m['id'] ?>" style="cursor:grab; transition: all 0.2s;">
                <div class="image-slot"
                    style="aspect-ratio:1; background:#eee; position:relative; overflow:hidden; border:2px solid transparent;">
                    <?php
                    $imagePath = $m['file_path'];
                    // Intelligent path handling
                    if (strpos($imagePath, 'assets/') === 0) {
                        // Path like "assets/uploads/img.jpg" -> "/assets/uploads/img.jpg"
                        $imageSrc = '/' . $imagePath;
                    } else if (strpos($imagePath, '/') === 0) {
                        // Already absolute path -> use as-is
                        $imageSrc = $imagePath;
                    } else {
                        // Relative path -> "/assets/uploads/filename.jpg"
                        $imageSrc = '/assets/uploads/' . $imagePath;
                    }
                    ?>
                    <img src="<?= $imageSrc ?>?t=<?= time() ?>" style="width:100%; height:100%; object-fit:cover;"
                        ondblclick="openCanvas(<?= $m['id'] ?>, '<?= $imageSrc ?>', <?= $activeElement['id'] ?>)">

                    <!-- Top-right buttons -->
                    <div style="position:absolute; top:5px; right:5px; display:flex; gap:3px;">
                        <button class="btn btn-sm btn-light"
                            style="padding:2px 8px; font-size:12px; opacity:0.9; background:rgba(255,255,255,0.95);"
                            onclick="openCanvas(<?= $m['id'] ?>, '<?= $imageSrc ?>', <?= $activeElement['id'] ?>)"
                            title="Rediger billede">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" type="button"
                            style="padding:2px 8px; font-size:12px; opacity:0.9; background:rgba(220,53,69,0.95);"
                            onclick="deleteImage(<?= $m['id'] ?>)" title="Slet billede">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                    <!-- Drag Handle - Centered at bottom -->
                    <div class="drag-handle"
                        style="position:absolute; bottom:5px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); color:white; padding:5px 12px; border-radius:15px; font-size:11px; cursor:grab; user-select:none;">
                        <i class="fas fa-grip-horizontal"></i> Træk for at flytte
                    </div>
                </div>
                <!-- Caption -->
                <div class="img-caption" style="margin-top:5px;">
                    <textarea placeholder="Billedetekst..."
                        style="width:100%; border:1px solid #ddd; font-size:0.8rem; padding:4px; border-radius:3px; resize:vertical; min-height:40px;"
                        onblur="updateMediaCaption(<?= $m['id'] ?>, this.value)"><?= htmlspecialchars($m['caption'] ?? '') ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>

        <?php
        // 2. Render Empty Slots to reach 4 total (with drag-drop upload)
        $filledCount = count($media);
        $emptySlots = max(0, 4 - $filledCount);
        for ($j = 0; $j < $emptySlots; $j++):
            ?>
            <div class="image-slot empty-slot"
                style="aspect-ratio:1; background:#f8f9fa; border:2px dashed #ddd; display:flex; align-items:center; justify-content:center; cursor:pointer; transition: all 0.2s;"
                onclick="document.getElementById('upload-input').click()">
                <div style="text-align:center; pointer-events:none;">
                    <span class="text-muted" style="font-size:2rem; color:#ddd;"><i class="fas fa-plus"></i></span>
                    <div style="margin-top:5px; font-size:0.8rem; color:#aaa;">Tilføj billede</div>
                    <div style="margin-top:3px; font-size:0.7rem; color:#bbb;">eller træk fil hertil</div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <!-- Hidden Upload -->
    <input type="file" id="upload-input" style="display:none" multiple
        onchange="uploadFiles(this.files, <?= $activeElement['id'] ?>, <?= $project['id'] ?>)">

    <!-- Internal Notes (Bottom of page) -->
    <div class="internal-notes" style="margin-top:30px; padding:20px; background:#f8f9fa; border-radius:8px;">
        <label style="font-size:1rem; font-weight:600; display:block; margin-bottom:10px; color:#333;">INTERNE NOTER</label>
        <textarea class="form-control" name="observation"
            style="width:100%; min-height:120px; padding:12px; border:1px solid #ddd; border-radius:4px; font-family: monospace; font-size:0.95rem;"
            onfocus="acquireLock(<?= $activeElement['id'] ?>, 'observation')"
            oninput="handleInputAutoSave(<?= $activeElement['id'] ?>, 'observation', this)"
            onblur="updateElementField(<?= $activeElement['id'] ?>, 'observation', this.value)"
            placeholder="Interne bemærkninger, noter, arbejdsgangskrav..."><?= htmlspecialchars($activeElement['observation'] ?? '') ?></textarea>
    </div>

    <!-- Delete Section (Bottom) -->
    <div style="margin-top:40px; border-top:1px solid #ddd; padding-top:20px; text-align:right;">
        <button class="btn btn-sm btn-danger"
            onclick="openDeleteModal(<?= $activeElement['id'] ?>, '<?= htmlspecialchars($activeElement['name'], ENT_QUOTES) ?>')">
            <i class="fas fa-trash"></i> Slet Element
        </button>
    </div>

    <script>
        // Refactored to use App.modal (matching Customer module style)
        window.openDeleteModal = function (id, name) {
            const content = `
                    <div style="padding:15px;">
                        <div class="alert alert-danger" style="background:#f8d7da; color:#721c24; padding:10px; border-radius:4px; margin-bottom:15px;">
                            <strong>ADVARSEL:</strong> Dette sletter elementet og al tilhørende data (billeder, budget, historik).
                        </div>
                        <p style="margin-bottom:10px;">Skriv navnet <strong>${name}</strong> for at slette:</p>
                        <input type="text" id="del-elem-input" class="form-control" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; margin-bottom:15px;" autocomplete="off">
                    </div>
                `;
            App.modal('Slet Element?', content, [{ text: 'Slet permanent', onClick: () => confirmDeleteElement(id), type: 'danger' }]);
            // Disable button initially & Setup Listener         setTimeout(() => {             const btns = document.querySelectorAll('#dynamic-modal .modal-footer button');             let delBtn = null;             // Find the danger button (last one usually)             btns.forEach(b => { if (b.classList.contains('btn-danger')) delBtn = b; });
            if (delBtn) { delBtn.disabled = true; delBtn.style.opacity = '0.5'; delBtn.id = 'btn-confirm-delete-elem'; }
            const input = document.getElementById('del-elem-input'); if (input) { input.focus(); input.addEventListener('input', function () { if (this.value.trim() === name.trim()) { if (delBtn) { delBtn.disabled = false; delBtn.style.opacity = '1'; } } else { if (delBtn) { delBtn.disabled = true; delBtn.style.opacity = '0.5'; } } }); }
        }, 50);     };
        window.confirmDeleteElement = function (id) { if (!id) return; App.api('?module=BuildingElement&action=delete', 'POST', { id: id }).then(res => { if (res.status === 'success') { App.toast('Element slettet', 'success'); App.closeModal(); setTimeout(() => window.location.href = window.location.pathname + '?module=BuildingElement&action=index&project_id=<?= $project['id'] ?>', 500); } else { App.toast('Fejl: ' + res.message, 'error'); } }).catch(e => App.toast('Fejl: ' + e, 'error')); };
    </script>

<?php else: ?>
    <div class="text-center mt-4" style="padding:40px; color:#999;">
        <h3>Vælg et element fra menuen for at se detaljer.</h3>
        <p>Du kan navigere i træstrukturen til venstre.</p>
    </div>
<?php endif; ?>