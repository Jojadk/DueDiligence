<?php
// Disable Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Translation Map (Should match i18n.js)
$translations = [
    'std.1' => 'Udendørsarealer (Terræn)',
    'std.1.1' => 'Belægninger (Vej, sti, fortov, pladser)',
    'std.1.2' => 'Grønne områder (Beplantning, græsarealer)',
    'std.1.3' => 'Hegn, porte og støttemure',
    'std.1.4' => 'Udvendig belysning',
    'std.1.5' => 'Afvanding af terræn (Brønde, render)',
    'std.1.6' => 'Skilte og inventar (Bænke, cykelstativer)',

    'std.2' => 'Bygningsskal (Klimaskærm)',
    'std.2.1' => 'Fundamenter og terrændæk',
    'std.2.2' => 'Facader (Murværk, beton, lette facader)',
    'std.2.3' => 'Vinduer og yderdøre',
    'std.2.4' => 'Tage (Tagdækning, ovenlys, tagbrønde)',
    'std.2.5' => 'Altaner og udvendige trapper',
    'std.2.6' => 'Porte og ramper (f.eks. til vareindlevering)',

    'std.3' => 'Indvendige bygningsdele',
    'std.3.1' => 'Indvendige vægge og skillevægge',
    'std.3.2' => 'Indvendige døre og partier',
    'std.3.3' => 'Gulve og gulvbelægninger',
    'std.3.4' => 'Lofter (Systemlofter, faste lofter)',
    'std.3.5' => 'Indvendige trapper',
    'std.3.6' => 'Inventar (Køkkener, toiletter, faste skabe)',

    'std.4' => 'Tekniske installationer',
    'std.4.1' => 'Vand (Brugsvand, sanitet)',
    'std.4.2' => 'Afløb (Spildevand, regnvand indvendigt)',
    'std.4.3' => 'Varme (Radiatorer, gulvvarme, fjernvarmeunits)',
    'std.4.4' => 'Køling (Køleflader, serverrumskøling)',
    'std.4.5' => 'Ventilation (Aggregater, kanaler, styring)',
    'std.4.6' => 'El-grundinstallationer (Tavler, føringsveje)',
    'std.4.7' => 'Belysning (Indvendig)',
    'std.4.8' => 'Elevatorer og løfteplatforme',
    'std.4.9' => 'Brandsikring (ABA, varsling, sprinkler, røgudluftning)',
    'std.4.10' => 'CTS / Bygningsautomation'
];

function translate($key, $map)
{
    // If key starts with "std.", remove "X. " prefix if present in the map text?
    // The map above has clean text.
    // However, the database might contain "1. Udendørsarealer" if it's old data.
    // Or if it's "std.1", we use the map.
    if (isset($map[$key])) {
        return $map[$key];
    }
    // Fallback: if data is already text
    return $key;
}
?>
<!DOCTYPE html>
<html lang="da">

<head>
    <meta charset="UTF-8">
    <title>Tilstandsrapport - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        /* BASE & RESET */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            margin: 0;
            padding: 20px 0 20px 300px;
            /* Left padding for Sidebar */
            background: #525659;
            /* Standard PDF viewer gray */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            /* Center pages in the remaining space */
        }

        /* SIDEBAR NAVIGATION */
        #nav-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 300px;
            background: #f8f9fa;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            padding: 20px;
            z-index: 100;
            font-size: 0.9rem;
        }

        #nav-sidebar h3 {
            font-size: 1.1rem;
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }

        .nav-group {
            margin-top: 15px;
        }

        .nav-group-title {
            font-weight: 600;
            color: #34495e;
            display: block;
            margin-bottom: 5px;
            text-decoration: none;
        }

        .nav-group-title:hover {
            color: #2980b9;
        }

        .nav-links {
            list-style: none;
            padding-left: 15px;
            margin: 0;
        }

        .nav-links li {
            margin-bottom: 4px;
        }

        .nav-links a {
            text-decoration: none;
            color: #666;
            font-size: 0.85rem;
            display: block;
        }

        .nav-links a:hover {
            color: #2980b9;
        }

        /* A4 PAGE CONTAINER */
        .page {
            width: 210mm;
            height: 297mm;
            /* Enforce strict A4 height */
            padding: 20mm;
            margin: 0 auto 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            /* Hide overflow - content should be paginated correctly */
            flex-shrink: 0;
        }

        /* CONTENT INNER */
        .page-content {
            height: 100%;
            position: relative;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
                display: block;
            }

            #nav-sidebar {
                display: none !important;
            }

            .page {
                margin: 0;
                border: none;
                box-shadow: none;
                width: 100%;
                height: auto;
                page-break-after: always;
                min-height: 297mm;
            }

            .page:last-child {
                page-break-after: auto;
            }

            .no-print {
                display: none !important;
            }

            #controls {
                display: none !important;
            }

            details:not([open]) {
                display: none !important;
            }
        }

        /* TYPOGRAPHY */
        h1 {
            color: #2c3e50;
            font-size: 24pt;
            margin-bottom: 20px;
            font-weight: 600;
        }

        h2 {
            color: #2c3e50;
            font-size: 18pt;
            margin-top: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
        }

        h3 {
            color: #34495e;
            font-size: 14pt;
            margin-top: 15px;
            font-weight: 500;
        }

        h4 {
            color: #7f8c8d;
            font-size: 11pt;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        p,
        li,
        td {
            font-size: 10pt;
            line-height: 1.5;
            color: #444;
            margin-bottom: 10px;
        }

        /* HEADER / FOOTER */
        .page-header {
            position: absolute;
            top: 10mm;
            left: 20mm;
            right: 20mm;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            font-size: 9pt;
            color: #999;
            display: flex;
            justify-content: space-between;
        }

        .page-footer {
            position: absolute;
            bottom: 10mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #999;
        }

        /* COMPONENT: TABLE OF CONTENTS */
        .toc-list {
            list-style: none;
            padding: 0;
        }

        .toc-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 6px;
        }

        .toc-link {
            text-decoration: none;
            color: inherit;
            width: 100%;
            display: flex;
        }

        .toc-name {
            font-weight: 500;
        }

        .toc-indent {
            margin-left: 20px;
            font-size: 0.95em;
            color: #555;
        }

        .toc-fill {
            flex: 1;
            border-bottom: 1px dotted #ccc;
            margin: 0 5px 4px 5px;
        }

        .toc-page {
            font-weight: 600;
            color: #555;
        }

        /* COMPONENT: REPORT ELEMENTS */
        .report-element {
            margin-bottom: 25px;
            break-inside: avoid;
        }

        .report-group-header {
            margin-bottom: 20px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        /* IMAGE GRID */
        .image-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }

        .report-img-container {
            break-inside: avoid;
        }

        .report-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #eee;
            border: 1px solid #ddd;
        }

        .img-caption {
            font-size: 8pt;
            color: #777;
            margin-top: 3px;
        }

        /* TABLES */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }

        th {
            text-align: left;
            background: #f8f9fa;
            padding: 6px;
            font-weight: 600;
        }

        td {
            border-bottom: 1px solid #eee;
            padding: 6px;
        }

        .text-right {
            text-align: right;
        }

        /* RISK BADGES */
        .risk-badge {
            padding: 2px 6px;
            border-radius: 3px;
            color: white;
            font-size: 8pt;
            font-weight: bold;
        }

        .risk-Rød {
            background: #e74c3c;
        }

        .risk-Gul {
            background: #f1c40f;
            color: #333;
        }

        .risk-Grøn {
            background: #27ae60;
        }

        .risk-Ikke {
            background: #bdc3c7;
            color: #333;
        }

        /* UTILS */
        .hidden-source {
            display: none;
        }

        .filtered-out {
            display: none !important;
        }

        #controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            width: 280px;
            transition: all 0.3s ease;
            overflow: hidden;
            max-height: 500px;
            /* arbitrary max for open state */
        }

        #controls.collapsed {
            max-height: 50px;
            /* Only header visible */
            width: 180px;
            padding: 10px 15px;
            opacity: 0.8;
        }

        #controls.collapsed:hover {
            opacity: 1;
        }

        .controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .controls-content {
            margin-top: 15px;
        }

        .chevron {
            transition: transform 0.3s ease;
            font-size: 1.2rem;
            line-height: 1;
        }

        #controls.collapsed .chevron {
            transform: rotate(-90deg);
        }

        #controls.collapsed .controls-content {
            display: none;
        }

        .filter-toggle {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .filter-toggle input[type="checkbox"] {
            cursor: pointer;
        }
    </style>
</head>

<body>

    <!-- NAVIGATION SIDEBAR (Only Visible on Screen) -->
    <div id="nav-sidebar" class="no-print">
        <h3>Indhold</h3>
        <?php
        $gCounter = 1;
        foreach ($tree as $group):
            $groupName = translate($group['name'], $translations);
            ?>
            <div class="nav-group">
                <a href="#elem-group-<?= $gCounter ?>" class="nav-group-title"><?= $gCounter ?>.
                    <?= htmlspecialchars($groupName) ?></a>
                <?php if (!empty($group['children'])): ?>
                    <ul class="nav-links">
                        <?php
                        $cCounter = 1;
                        foreach ($group['children'] as $child):
                            $childName = translate($child['name'], $translations);
                            $childNum = "$gCounter.$cCounter";
                            ?>
                            <li><a href="#elem-<?= $child['id'] ?>"><?= $childNum ?>             <?= htmlspecialchars($childName) ?></a></li>
                            <?php
                            $cCounter++;
                        endforeach;
                        ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
            $gCounter++;
        endforeach;
        ?>
        <div class="nav-group" style="margin-top:20px; border-top:1px solid #ddd; padding-top:10px;">
            <a href="#budget-section" class="nav-group-title">Budgetsoversigt</a>
        </div>
    </div>

    <div id="controls" class="no-print">
        <div class="controls-header" onclick="toggleControls()">
            <h3 style="margin:0; font-size:1rem;">Rapportværktøj</h3>
            <span class="chevron">▼</span>
        </div>
        <div class="controls-content">
            <button onclick="window.print()"
                style="width:100%; padding:10px; background:#2980b9; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:500;">
                🖨️ Udskriv / PDF
            </button>
            <div class="filter-toggle">
                <input type="checkbox" id="filter-irrelevant" onchange="toggleIrrelevantFilter()">
                <label for="filter-irrelevant" style="cursor:pointer; margin:0;">Skjul "Ikke relevant"</label>
            </div>
            <div style="margin-top:10px; font-size:0.85rem; color:#666; line-height:1.4;">
                <b>Tip:</b> Layoutet er optimeret til A4.<br>
                Sørg for at "Baggrundsgrafik" er slået <b>TIL</b> og "Sideoverskrifter" er slået <b>FRA</b> i print
                indstillinger.
            </div>
            <div id="status-msg" style="margin-top:10px; font-size:0.8rem; color:#27ae60;"></div>
        </div>
    </div>

    <!-- PREVIEW CONTAINER (Pages inserted here) -->
    <div id="report-preview"></div>

    <!-- HIDDEN SOURCE (Raw Content) -->
    <div id="source-content" class="hidden-source">

        <!-- COVER CONTENT -->
        <div id="source-cover">
            <div style="height:100%; display:flex; flex-direction:column;">
                <div style="text-align:right;">
                    <img src="/assets/logo.png" style="height:35px; opacity:0.6;">
                </div>

                <div style="margin-top:60px;">
                    <h1 style="font-size:36pt; margin-bottom:10px; color:#2c3e50;">Tilstandsrapport</h1>
                    <div style="font-size:18pt; color:#7f8c8d;"><?= htmlspecialchars($project['name']) ?></div>
                </div>

                <?php
                $coverImage = null; // Initialize
                if (!empty($project['cover_image'])) {
                    $path = $project['cover_image'];
                    if (strpos($path, 'assets/') === 0) {
                        $coverImage = '/' . $path;
                    } elseif (strpos($path, '/') === 0) {
                        $coverImage = $path;
                    } else {
                        $coverImage = '/assets/uploads/' . $path;
                    }
                    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $coverImage)) {
                        $coverImage = null;
                    }
                } else {
                    // Fallback to NO image preferred by user, 
                    // unless we want a specific default placeholder.
                    // User requested: "det skal være den som er uploadeed" (implies if none uploaded, show none/placeholder, NOT a random image from tree)
                    $coverImage = null;
                }
                ?>
                <div
                    style="margin-top:50px; flex-grow:1; max-height:400px; overflow:hidden; border:1px solid #ddd; background:#f9f9f9; display:flex; align-items:center; justify-content:center;">
                    <?php if ($coverImage): ?>
                        <img src="<?= $coverImage ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span style="color:#aaa; font-size:14pt;">(Ingen billede valgt)</span>
                    <?php endif; ?>
                </div>

                <div
                    style="margin-top:auto; margin-bottom:40px; background:#f8f9fa; border-left:5px solid #2c3e50; padding:20px;">
                    <div style="display:grid; grid-template-columns: 100px 1fr; gap:10px;">
                        <b>Kunde:</b> <span><?= htmlspecialchars($client['name'] ?? 'Ukendt') ?></span>
                        <b>Adresse:</b> <span><?= htmlspecialchars($project['address'] ?? '') ?></span>
                        <b>Dato:</b> <span><?= date('d.m.Y') ?></span>
                        <b>Udført af:</b> <span>Jacob Jacobsen</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- INTRO & DISCLAIMER CONTENT -->
        <div id="source-intro">
            <div style="margin-bottom:40px;">
                <h1>Introduction</h1>
                <div style="font-size:11pt; line-height:1.6; white-space:pre-wrap;">
                    <?= !empty($project['report_intro']) ? htmlspecialchars($project['report_intro']) : "On behalf of [Client] Sweco Denmark have conducted a Technical Due Diligence (TDD) Red flag & Finding's assessment of [Property].\nThe TDD has been carried out thru site visit and desktop survey, by performing a review of the relevant material in the project data room." ?>
                </div>
            </div>

            <div style="margin-bottom:40px;">
                <h1>Definitions</h1>
                <div style="font-size:11pt; line-height:1.6; white-space:pre-wrap;">
                    <?= !empty($project['report_disclaimer']) ? htmlspecialchars($project['report_disclaimer']) : "The technical review of the property/project's building elements is based on Molio's price data classification of main areas within maintenance. For outdoor areas, indoor areas, building envelope, and technical installations.\nThe pricing for repairs and improvements (defined as CAPEX) is based on Molio's price data and Sweco's empirical figures.\n\nSymbols:\n🚩 Red flag: Technical observations that have already caused or will cause failure.\n🟨 Major Condition: Failure in long term.\n⬛ Not comprehensive to law.\n🟦 Further investigation needed." ?>
                </div>
            </div>
        </div>

        <!-- TOC CONTENT -->
        <div id="source-toc">
            <h2>Indholdsfortegnelse</h2>
            <ul class="toc-list">
                <?php
                $gCounter = 1;
                foreach ($tree as $group):
                    $groupName = translate($group['name'], $translations);
                    ?>
                    <li class="toc-row">
                        <a href="#elem-group-<?= $gCounter ?>" class="toc-link">
                            <span class="toc-name"><?= $gCounter ?>. <?= htmlspecialchars($groupName) ?></span>
                            <span class="toc-fill"></span>
                            <span class="toc-page" data-target="elem-group-<?= $gCounter ?>">...</span>
                        </a>
                    </li>
                    <?php if (!empty($group['children'])):
                        $cCounter = 1;
                        foreach ($group['children'] as $child):
                            $childName = translate($child['name'], $translations);
                            $childNum = "$gCounter.$cCounter";
                            ?>
                            <li
                                class="toc-row <?= (!empty($child['risk_level']) && strpos($child['risk_level'], 'Ikke relevant') !== false) ? 'toc-irrelevant' : '' ?>">
                                <a href="#elem-<?= $child['id'] ?>" class="toc-link">
                                    <span class="toc-name toc-indent"><?= $childNum ?>             <?= htmlspecialchars($childName) ?></span>
                                    <span class="toc-fill"></span>
                                    <span class="toc-page" data-target="elem-<?= $child['id'] ?>">...</span>
                                </a>
                            </li>
                            <?php
                            $cCounter++;
                        endforeach;
                    endif;
                    $gCounter++;
                endforeach;
                ?>
                <li class="toc-row" style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                    <a href="#budget-section" class="toc-link">
                        <span class="toc-name">Budgetsoversigt (CAPEX)</span>
                        <span class="toc-fill"></span>
                        <span class="toc-page" data-target="budget-section">...</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- REPORT ELEMENTS -->
        <div id="source-elements">
            <?php
            $gCounter = 1;
            foreach ($tree as $group):
                $groupName = translate($group['name'], $translations);
                ?>
                <!-- GROUP SECTION -->
                <div class="report-element" id="elem-group-<?= $gCounter ?>">
                    <div class="report-group-header">
                        <h1><?= $gCounter ?>. <?= htmlspecialchars($groupName) ?></h1>
                        <p><i><?= htmlspecialchars(translate($group['location'], $translations)) ?></i></p>
                        <?php if (!empty($group['observation'])): ?>
                            <div style="background:#f8f9fa; padding:15px; border-radius:4px;">
                                <b>Generelt:</b> <?= nl2br(htmlspecialchars($group['observation'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CHILDREN -->
                <?php if (!empty($group['children'])):
                    $cCounter = 1;
                    foreach ($group['children'] as $child):
                        $childName = translate($child['name'], $translations);
                        $childNum = "$gCounter.$cCounter";
                        ?>
                        <div class="report-element" id="elem-<?= $child['id'] ?>">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:10px;">
                                <h3><?= $childNum ?>             <?= htmlspecialchars($childName) ?></h3>
                                <?php if (!empty($child['risk_level'])): ?>
                                    <span class="risk-badge risk-<?= explode(' ', $child['risk_level'])[0] ?>">
                                        <?= htmlspecialchars($child['risk_level']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Custom Fields Display -->
                            <?php if (!empty($customFieldDefs) && !empty($child['custom_values'])): ?>
                                <div
                                    style="margin-bottom:15px; background:#f5f5fa; padding:10px; border-radius:4px; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <?php foreach ($customFieldDefs as $def):
                                        if (isset($child['custom_values'][$def['id']]) && $child['custom_values'][$def['id']] !== ''):
                                            ?>
                                            <div>
                                                <span
                                                    style="font-size:0.8rem; color:#888; display:block;"><?= htmlspecialchars($def['label']) ?></span>
                                                <span
                                                    style="font-weight:500; font-size:0.9rem;"><?= htmlspecialchars($child['custom_values'][$def['id']]) ?></span>
                                            </div>
                                        <?php endif; endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="meta-grid">
                                <?php if (!empty($child['description'])): ?>
                                    <div>
                                        <h4 style="color:#e67e22;">Observation</h4>
                                        <p><?= nl2br(htmlspecialchars($child['description'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($child['recommendation'])): ?>
                                    <div>
                                        <h4 style="color:#27ae60;">Anbefaling</h4>
                                        <p><?= nl2br(htmlspecialchars($child['recommendation'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($child['budget_items'])): ?>
                                <div style="margin-top:10px; background:#f9f9f9; padding:10px;">
                                    <h4 style="margin-top:0;">Overslag</h4>
                                    <table>
                                        <tr style="font-weight:bold;">
                                            <td><?= htmlspecialchars(translate($child['name'], $translations)) ?></td>
                                            <td class="text-right"><?= number_format($child['capex'], 2, ',', '.') ?> DKK</td>
                                        </tr>
                                    </table>
                                    <details style="margin-top:8px; font-size:0.85rem; color:#666;">
                                        <summary style="cursor:pointer;">Se detaljer</summary>
                                        <table style="margin-top:5px;">
                                            <tr style="font-weight:bold; background:#eee;">
                                                <td style="padding:4px;">Beskrivelse</td>
                                                <td class="text-right" style="padding:4px;">Total</td>
                                                <td class="text-right" style="padding:4px; font-size:0.8em; color:gray;">&lt;1 år</td>
                                                <td class="text-right" style="padding:4px; font-size:0.8em; color:gray;">1-2 år</td>
                                                <td class="text-right" style="padding:4px; font-size:0.8em; color:gray;">3-5 år</td>
                                                <td class="text-right" style="padding:4px; font-size:0.8em; color:gray;">5-10 år</td>
                                            </tr>
                                            <?php foreach ($child['budget_items'] as $bItem): ?>
                                                <tr>
                                                    <td style="padding-left:10px;"><?= htmlspecialchars($bItem['description']) ?></td>
                                                    <td class="text-right"><?= number_format($bItem['total_calculated'], 0, ',', '.') ?>
                                                    </td>
                                                    <td class="text-right" style="color:red; font-size:0.8em;">
                                                        <?= $bItem['amount_0_1'] > 0 ? number_format($bItem['amount_0_1'], 0, ',', '.') : '-' ?>
                                                    </td>
                                                    <td class="text-right" style="color:#d4ac0d; font-size:0.8em;">
                                                        <?= $bItem['amount_1_2'] > 0 ? number_format($bItem['amount_1_2'], 0, ',', '.') : '-' ?>
                                                    </td>
                                                    <td class="text-right" style="color:green; font-size:0.8em;">
                                                        <?= $bItem['amount_3_5'] > 0 ? number_format($bItem['amount_3_5'], 0, ',', '.') : '-' ?>
                                                    </td>
                                                    <td class="text-right" style="color:blue; font-size:0.8em;">
                                                        <?= $bItem['amount_5_10'] > 0 ? number_format($bItem['amount_5_10'], 0, ',', '.') : '-' ?>
                                                    </td>
                                                </tr>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </details>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($child['media'])): ?>
                                <div class="image-grid">
                                    <?php
                                    $imgIndex = 1;
                                    foreach ($child['media'] as $media):
                                        ?>
                                        <div class="report-img-container">
                                            <?php
                                            // Use file_path which is the edited/annotated version
                                            $path = $media['file_path'];

                                            // Determine absolute filesystem path for checking
                                            $absPath = '';
                                            $src = '';

                                            if (strpos($path, 'assets/') === 0) {
                                                $absPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
                                                $src = '/' . $path;
                                            } elseif (strpos($path, '/') === 0) {
                                                $absPath = $_SERVER['DOCUMENT_ROOT'] . $path;
                                                $src = $path;
                                            } else {
                                                // Assume it's in assets/uploads/
                                                $absPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/' . $path;
                                                $src = '/assets/uploads/' . $path;
                                            }

                                            // Only render if file exists
                                            if (!file_exists($absPath)) {
                                                continue;
                                            }
                                            ?>
                                            <img src="<?= htmlspecialchars($src) ?>" class="report-img">
                                            <div class="img-caption">
                                                <b>Fig. <?= $childNum . '.' . $imgIndex ?></b>
                                                <?php
                                                $cap = !empty($media['caption']) ? $media['caption'] : ($media['comment'] ?? '');
                                                if ($cap): ?>
                                                    <br><?= nl2br(htmlspecialchars($cap)) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php
                                        $imgIndex++;
                                    endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                        $cCounter++;
                    endforeach;
                endif;
                $gCounter++;
            endforeach;
            ?>

            <!-- BUDGET TOTAL -->
            <div class="report-element" id="budget-section">
                <h1>Samlet Budgetoversigt</h1>
                <p>Overslag over samlede CAPEX omkostninger.</p>
                <table>
                    <thead>
                        <tr style="background:#2c3e50; color:white;">
                            <th>Emne</th>
                            <th class="text-right">Estimat (DKK)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tree as $group): ?>
                            <?php if (!empty($group['children'])): ?>
                                <tr style="background:#eee; font-weight:bold;">
                                    <td colspan="2"><?= htmlspecialchars(translate($group['name'], $translations)) ?></td>
                                </tr>
                                <?php foreach ($group['children'] as $child): ?>
                                    <?php if ($child['capex'] > 0): ?>
                                        <tr>
                                            <td style="padding-left:20px;">
                                                <?= htmlspecialchars(translate($child['name'], $translations)) ?>
                                            </td>
                                            <td class="text-right"><?= number_format($child['capex'], 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-size:12pt; font-weight:bold; background:#27ae60; color:white;">
                            <td style="padding:12px 6px; color:white !important;">TOTAL EKSKL. MOMS</td>
                            <td class="text-right" style="padding:12px 6px; color:white !important;">
                                <?= number_format($totalCapex, 2, ',', '.') ?> DKK
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>

    <!-- PAGINATION SCRIPT -->
    <script>
        // Toggle function for controls
        function toggleControls() {
            const controls = document.getElementById('controls');
            controls.classList.toggle('collapsed');
        }

        // Filter "Ikke relevant" elements
        function toggleIrrelevantFilter() {
            paginateReport();
        }

        function paginateReport() {
            const preview = document.getElementById('report-preview');
            const sourceElements = document.getElementById('source-elements');
            const sourceCover = document.getElementById('source-cover');
            const sourceIntro = document.getElementById('source-intro');
            const sourceToc = document.getElementById('source-toc');
            const isFilterActive = document.getElementById('filter-irrelevant').checked;

            // Clear previous preview
            preview.innerHTML = '';

            // Map to store Page Number for IDs
            const pageMap = {};
            let pageCounter = 1;

            function createPage() {
                const page = document.createElement('div');
                page.className = 'page';

                const content = document.createElement('div');
                content.className = 'page-content';
                page.appendChild(content);

                const footer = document.createElement('div');
                footer.className = 'page-footer';
                footer.textContent = `Side ${pageCounter}`;
                page.appendChild(footer);

                preview.appendChild(page);
                return { page, content };
            }

            // 1. Render Cover Page
            const coverPage = createPage();
            coverPage.page.querySelector('.page-footer').style.display = 'none';
            coverPage.content.innerHTML = sourceCover.innerHTML;
            pageCounter++;

            // 1.5 Render Intro & Definitions
            let introPage = createPage();
            Array.from(sourceIntro.children).forEach(child => {
                const node = child.cloneNode(true);
                introPage.content.appendChild(node);
                if (introPage.content.scrollHeight > introPage.content.clientHeight) {
                    introPage.content.removeChild(node);
                    pageCounter++;
                    introPage = createPage();
                    introPage.content.appendChild(node);
                }
            });
            pageCounter++;

            // 2. Render TOC - with overflow handling and filtering
            let tocPage = createPage();
            const tocContent = sourceToc.cloneNode(true);
            const tocItems = Array.from(tocContent.querySelectorAll('.toc-row'));

            tocPage.content.appendChild(tocContent.querySelector('h2'));
            let tocList = document.createElement('ul');
            tocList.className = 'toc-list';
            tocPage.content.appendChild(tocList);

            tocItems.forEach(item => {
                // Skip if filtered out
                if (isFilterActive && item.classList.contains('toc-irrelevant')) return;

                tocList.appendChild(item);

                if (tocPage.content.scrollHeight > tocPage.content.clientHeight) {
                    tocList.removeChild(item);
                    pageCounter++;
                    tocPage = createPage();
                    let newTocList = document.createElement('ul');
                    newTocList.className = 'toc-list';
                    newTocList.style.marginTop = '20px';
                    tocPage.content.appendChild(newTocList);
                    newTocList.appendChild(item);
                    tocList = newTocList;
                }
            });

            pageCounter++;

            // 3. Render Content Elements
            let currentPage = createPage();
            const elements = Array.from(sourceElements.children);

            elements.forEach(el => {
                // Skip if filtered out
                const riskBadge = el.querySelector('.risk-Ikke');
                if (isFilterActive && riskBadge) return;

                if (el.id && el.id.startsWith('elem-group-')) {
                    const currentFilledHeight = currentPage.content.scrollHeight;
                    const pageTotalHeight = currentPage.content.clientHeight;
                    if (currentFilledHeight > (pageTotalHeight * 0.33)) {
                        pageCounter++;
                        currentPage = createPage();
                    }
                }

                if (el.id) pageMap[el.id] = pageCounter;

                currentPage.content.appendChild(el.cloneNode(true));

                if (currentPage.content.scrollHeight > currentPage.content.clientHeight) {
                    currentPage.content.removeChild(currentPage.content.lastChild);
                    pageCounter++;
                    currentPage = createPage();
                    currentPage.content.appendChild(el.cloneNode(true));
                    if (el.id) pageMap[el.id] = pageCounter;
                }
            });

            // 4. Update TOC numbers across all pages
            const allTocPages = preview.querySelectorAll('.toc-page');
            allTocPages.forEach(span => {
                const targetId = span.getAttribute('data-target');
                if (pageMap[targetId]) {
                    span.textContent = `Side ${pageMap[targetId]}`;
                }
            });

            document.getElementById('status-msg').innerText = `Klar! ${pageCounter} sider genereret.`;
        }

        document.addEventListener('DOMContentLoaded', paginateReport);
    </script>

</body>

</html>