<?php
// Building Element Form Template
$title = isset($element) ? 'Rediger Element' : 'Opret Nyt Afsnit';
$action = isset($element) ? '?module=BuildingElement&action=update' : '?module=BuildingElement&action=store';
$elementId = $element['id'] ?? '';
$name = $element['name'] ?? '';
$location = $element['location'] ?? '';
$condition = $element['condition_rating_text'] ?? 'Rimelig';
$quantity = $element['quantity'] ?? '1';
$priceId = $element['price_catalog_id'] ?? '';
?>

<div class="card" style="height:100%; display:flex; flex-direction:column; border:none; box-shadow:none;">
    <div class="card-body" style="flex:1; overflow-y:auto; padding:0;">
        <form action="<?= $action ?>" method="POST" id="element-form">
            <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
            <input type="hidden" name="id" value="<?= $elementId ?>">

            <!-- Type Selection (Only for New) -->
            <?php if (!$elementId): ?>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight:600;">Type</label>
                    <div style="display:flex; gap:15px; margin-top:5px;">
                        <label style="cursor:pointer; display:flex; align-items:center;">
                            <input type="radio" name="placement_type" value="main" checked
                                onchange="window.toggleParentSelect(false)">
                            <span style="margin-left:5px;">Hovedkategori</span>
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center;">
                            <input type="radio" name="placement_type" value="sub"
                                onchange="window.toggleParentSelect(true)">
                            <span style="margin-left:5px;">Underkategori</span>
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center;">
                            <input type="radio" name="placement_type" value="other"
                                onchange="window.toggleParentSelect(false)">
                            <span style="margin-left:5px;">Andet / Manuel</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="parent-select-group" style="margin-bottom: 15px; display:none;">
                    <label>Placer under (Forælder)</label>
                    <select name="parent_id" id="section_parent_id" class="form-control"
                        style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="">-- Vælg forælder --</option>
                        <!-- Options populated by JS or PHP? JS currently. I will rely on JS populating it if needed, or better, pass tree here? -->
                        <!-- JS toggleParentSelect populates it from DOM tree. That logic still holds if we open this in WM? -->
                        <!-- The DOM tree (.inspection-toc) is visible in background. JS can read it. -->
                    </select>
                    <small class="text-muted">Vælg det overordnede element dette skal ligge under.</small>
                </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 15px;">
                <label data-i18n="element.name" style="font-weight:600;">Overskrift (Navn)</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($name) ?>"
                    style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="Navn på afsnit...">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight:600;">Lokation / Placering</label>
                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($location) ?>"
                    style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="f.eks. Stueplan, Køkken...">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label data-i18n="element.condition" style="font-weight:600;">Tilstand</label>
                <select name="condition_rating" class="form-control"
                    style="width: 100%; padding: 8px; margin-top: 5px;">
                    <option value="God" <?= $condition == 'God' ? 'selected' : '' ?>>God</option>
                    <option value="Fornuftig" <?= $condition == 'Fornuftig' ? 'selected' : '' ?>>Fornuftig</option>
                    <option value="Rimelig" <?= $condition == 'Rimelig' ? 'selected' : '' ?>>Rimelig</option>
                    <option value="Dårlig" <?= $condition == 'Dårlig' ? 'selected' : '' ?>>Dårlig</option>
                </select>
            </div>

            <div class="form-row" style="display: flex; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="font-weight:600;">Mængde</label>
                    <input type="number" name="quantity" class="form-control"
                        style="width: 100%; padding: 8px; margin-top: 5px;" value="<?= htmlspecialchars($quantity) ?>">
                </div>
                <div style="flex: 1;">
                    <label style="font-weight:600;">Prissætning</label>
                    <select name="price_catalog_id" class="form-control"
                        style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="">Vælg...</option>
                        <?php if (isset($prices)):
                            foreach ($prices as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $priceId == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['description']) ?> (
                                    <?= $p['price'] ?> DKK)
                                </option>
                            <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>

            <!-- Custom Fields -->
            <?php if (!empty($customFields)): ?>
                <div id="modal-custom-fields" style="border-top:1px solid #eee; padding-top:10px; margin-top:10px;">
                    <h4 style="margin:0 0 10px 0; font-size:1rem; color:#666;">Yderligere Info</h4>
                    <?php foreach ($customFields as $field):
                        $val = '';
                        if (isset($element['custom_fields_data'][$field['id']])) {
                            $val = $element['custom_fields_data'][$field['id']];
                        }
                        ?>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="font-weight:600;">
                                <?= htmlspecialchars($field['label']) ?>
                            </label>
                            <?php
                            $fType = $field['type'] ?? 'text';
                            if ($fType === 'textarea'):
                                ?>
                                <textarea name="custom_fields[<?= $field['id'] ?>]" class="form-control" rows="2"
                                    style="width: 100%; padding: 8px; margin-top: 5px;"><?= htmlspecialchars($val) ?></textarea>
                            <?php elseif ($fType === 'select'):
                                $opts = explode(',', $field['options'] ?? '');
                                ?>
                                <select name="custom_fields[<?= $field['id'] ?>]" class="form-control"
                                    style="width: 100%; padding: 8px; margin-top: 5px;">
                                    <option value="">-- Vælg --</option>
                                    <?php foreach ($opts as $opt):
                                        $opt = trim($opt); ?>
                                        <option value="<?= htmlspecialchars($opt) ?>" <?= $val == $opt ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="custom_fields[<?= $field['id'] ?>]" class="form-control"
                                    style="width: 100%; padding: 8px; margin-top: 5px;" value="<?= htmlspecialchars($val) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Footer Buttons -->
            <div class="form-actions"
                style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="wm.closeWindow('win-element-form')"
                    data-i18n="common.cancel">Annuller</button>
                <button type="submit" class="btn btn-primary" data-i18n="common.save">Gem Element</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize parent select logic if present
    if (window.toggleParentSelect) {
        // Wait for DOM
        setTimeout(() => {
            if (document.querySelector('input[name=placement_type]:checked').value === 'sub') {
                window.toggleParentSelect(true);
            }
        }, 100);
    }

    // Intercept form submit
    document.getElementById('element-form').onsubmit = function (e) {
        e.preventDefault();
        const btn = this.querySelector('button[type=submit]');
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Gemmer...';

        const fd = new FormData(this);

        App.api(this.action, 'POST', fd).then(res => {
            if (res.status === 'success') {
                App.toast('Gemt korrekt!', 'success');
                wm.closeWindow('win-element-form');
                window.location.reload();
            } else {
                App.toast('Fejl: ' + res.message, 'error');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        }).catch(err => {
            App.toast('Fejl: ' + err, 'error');
            btn.disabled = false;
            btn.innerText = originalText;
        });
    };
</script>