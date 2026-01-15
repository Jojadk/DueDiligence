<?php
// Version Save Form
$projectId = $_GET['project_id'] ?? '';
$currentUser = \Core\Auth::user()['username'] ?? 'System';
?>
<div class="card" style="height:100%; display:flex; flex-direction:column; border:none; box-shadow:none;">
    <div class="card-body" style="flex:1; overflow-y:auto; padding:15px;">

        <!-- Versioning Guide (Collapsible) -->
        <div style="background:#f0f8ff; border:1px solid #b3d9ff; border-radius:6px; padding:12px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;"
                onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
                <strong style="color:#0066cc;"><i class="fas fa-info-circle"></i> Versioneringsvejledning</strong>
                <i class="fas fa-chevron-down" style="color:#0066cc;"></i>
            </div>
            <div style="display:none; margin-top:10px; font-size:0.9em; color:#333; line-height:1.6;">
                <p style="margin:5px 0;"><strong>Format: X.Y</strong></p>
                <ul style="margin:5px 0; padding-left:20px;">
                    <li><strong>Første ciffer (X.0)</strong> = Godkendelsesstadier:
                        <ul style="margin:3px 0; padding-left:20px; font-size:0.95em;">
                            <li>1.0 = Første kladde</li>
                            <li>2.0 = Andet udkast</li>
                            <li>3.0 = Endelig version</li>
                        </ul>
                    </li>
                    <li><strong>Andet ciffer (X.Y)</strong> = Interne milepæle mellem godkendelser:
                        <ul style="margin:3px 0; padding-left:20px; font-size:0.95em;">
                            <li>1.1 = Struktur opbygget</li>
                            <li>1.2 = Redflags sat</li>
                            <li>1.3 = Økonomi indsat</li>
                            <li>1.4 = Tekster skrevet</li>
                        </ul>
                    </li>
                </ul>
                <p
                    style="margin:8px 0 0 0; padding:8px; background:#fff6e5; border-left:3px solid #ffa500; font-style:italic;">
                    <strong>Eksempel:</strong> Version 1.3 kunne være "Første kladde med økonomi indsat",
                    mens version 2.0 ville være "Andet udkast til godkendelse"
                </p>
            </div>
        </div>

        <!-- Quick Version Type Selector -->
        <div class="form-group" style="margin-bottom:15px;">
            <label style="font-weight:600;">Hurtigvalg af versiontype</label>
            <select id="version_type_select" class="form-control" onchange="fillVersionFromType(this.value)"
                style="width:100%; padding:8px; margin-top:5px;">
                <option value="">-- Vælg versiontype --</option>
                <optgroup label="Godkendelsesstadier">
                    <option value="1.0|Første kladde">1.0 - Første kladde</option>
                    <option value="2.0|Andet udkast">2.0 - Andet udkast</option>
                    <option value="3.0|Endelig version">3.0 - Endelig version</option>
                </optgroup>
                <optgroup label="Interne milepæle (under 1.x)">
                    <option value="1.1|Struktur opbygget">1.1 - Struktur opbygget</option>
                    <option value="1.2|Redflags sat">1.2 - Redflags sat</option>
                    <option value="1.3|Økonomi indsat">1.3 - Økonomi indsat</option>
                    <option value="1.4|Tekster skrevet">1.4 - Tekster skrevet</option>
                </optgroup>
                <optgroup label="Interne milepæle (under 2.x)">
                    <option value="2.1|Andet udkast - struktur justeret">2.1 - Struktur justeret</option>
                    <option value="2.2|Andet udkast - feedback implementeret">2.2 - Feedback implementeret</option>
                </optgroup>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:15px;">
            <label style="font-weight:600;">Version Nummer</label>
            <input type="text" id="version_number" class="form-control" value="1.0" placeholder="f.eks. 1.2 eller 2.0"
                style="width:100%; padding:8px; margin-top:5px;">
        </div>
        <div class="form-group" style="margin-bottom:15px;">
            <label style="font-weight:600;">Version Note / Beskrivelse</label>
            <textarea id="version_note" class="form-control" placeholder="Hvad er opdateret i denne version..."
                style="width:100%; padding:8px; margin-top:5px; min-height:80px;"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:15px;">
            <label style="font-weight:600;">Intern Bemærkning</label>
            <textarea id="internal_remark" class="form-control" placeholder="Interne noter til teamet..."
                style="width:100%; padding:8px; margin-top:5px; min-height:60px;"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:15px;">
            <label style="font-weight:600;">Gemt af (Navn)</label>
            <input type="text" id="created_by_name" class="form-control" value="<?= htmlspecialchars($currentUser) ?>"
                style="width:100%; padding:8px; margin-top:5px;">
        </div>

        <div class="form-actions"
            style="margin-top:20px; padding-top:15px; border-top:1px solid #eee; text-align:right;">
            <button onclick="wm.closeWindow('win-version-save')" class="btn btn-secondary"
                style="margin-right:10px;">Annuller</button>
            <button onclick="window.saveProjectVersion()" class="btn btn-primary">
                <i class="fas fa-save"></i> Gem Version
            </button>
        </div>
    </div>
</div>

<script>
    // Local scripts for this view
    // (fillVersionFromType and saveProjectVersion should be globally available or redefined here)
    // They are defined in index.php globally currently.
    // I should probably move them to building_element.js but let's assume they work if global.
    // If wm loads this via innerHTML, global functions are fine.
    // If wm loads via script execution? wm inserts HTML. Scripts inside HTML might not run safely if not distinct.
    // But basic onclick attributes work finding global functions.
</script>