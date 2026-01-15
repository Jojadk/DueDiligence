<?php
// Report/views/excel_report.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Helper translation
$translations = [
    'std.1' => 'Udendørsarealer',
    'std.1.1' => 'Belægninger',
    'std.1.2' => 'Grønne områder',
    'std.1.3' => 'Hegn, porte og støttemure',
    'std.1.4' => 'Udvendig belysning',
    'std.1.5' => 'Afvanding af terræn',
    'std.1.6' => 'Skilte og inventar',
    'std.2' => 'Bygningsskal',
    'std.2.1' => 'Fundamenter og terrændæk',
    'std.2.2' => 'Facader',
    'std.2.3' => 'Vinduer og yderdøre',
    'std.2.4' => 'Tage',
    'std.2.5' => 'Altaner og udvendige trapper',
    'std.2.6' => 'Porte og ramper',
    'std.3' => 'Indvendige bygningsdele',
    'std.3.1' => 'Indvendige vægge',
    'std.3.2' => 'Indvendige døre',
    'std.3.3' => 'Gulve',
    'std.3.4' => 'Lofter',
    'std.3.5' => 'Indvendige trapper',
    'std.3.6' => 'Inventar',
    'std.4' => 'Tekniske installationer',
    'std.4.1' => 'Vand',
    'std.4.2' => 'Afløb',
    'std.4.3' => 'Varme',
    'std.4.4' => 'Køling',
    'std.4.5' => 'Ventilation',
    'std.4.6' => 'El-grundinstallationer',
    'std.4.7' => 'Belysning',
    'std.4.8' => 'Elevatorer',
    'std.4.9' => 'Brandsikring',
    'std.4.10' => 'CTS'
];

function t($key, $map)
{
    return $map[$key] ?? $key;
}

// Logic to distribute CAPEX into TimeBuckets based on Risk
// Logic to distribute CAPEX into TimeBuckets based on Budget Items OR Risk
function getBuckets($capex, $risk, $budgetItems = [])
{
    $buckets = ['<1' => 0, '1-2' => 0, '3-5' => 0, '6-10' => 0];

    // 1. Try Granular Budget Items
    if (!empty($budgetItems)) {
        foreach ($budgetItems as $item) {
            // Map DB columns to our buckets
            $buckets['<1'] += $item['amount_0_1'] ?? 0;
            $buckets['1-2'] += $item['amount_1_2'] ?? 0;
            $buckets['3-5'] += $item['amount_3_5'] ?? 0;
            $buckets['6-10'] += $item['amount_5_10'] ?? 0;
        }
        // If sum > 0, return.
        if (array_sum($buckets) > 0)
            return $buckets;
    }

    // 2. Fallback to Risk Heuristic if CAPEX exists but no granular data
    if (empty($capex))
        return $buckets;

    // Simple heuristic mapping
    if (strpos($risk, 'Rød') !== false)
        $buckets['<1'] = $capex;
    elseif (strpos($risk, 'Gul') !== false)
        $buckets['1-2'] = $capex;
    elseif (strpos($risk, 'Grøn') !== false)
        $buckets['3-5'] = $capex;
    else
        $buckets['6-10'] = $capex; // Default

    return $buckets;
}

// Prepare Summary Data
$summaryData = [];
$totalBuckets = ['<1' => 0, '1-2' => 0, '3-5' => 0, '6-10' => 0, 'total' => 0];

foreach ($tree as $group) {
    $groupRow = [
        'name' => t($group['name'], $translations),
        'buckets' => ['<1' => 0, '1-2' => 0, '3-5' => 0, '6-10' => 0],
        'children' => []
    ];

    foreach ($group['children'] as $child) {
        // Child Logic
        $childCapex = $child['capex'] ?? 0;
        $childBudgetItems = $child['budget_items'] ?? [];
        $buckets = getBuckets($childCapex, $child['risk_level'] ?? '', $childBudgetItems);

        $childRow = [
            'name' => t($child['name'], $translations),
            'buckets' => $buckets
        ];

        // Add to Group Totals
        foreach ($buckets as $k => $v) {
            $groupRow['buckets'][$k] += $v;
            $totalBuckets[$k] += $v;
            $totalBuckets['total'] += $v;
        }
        $groupRow['children'][] = $childRow;
    }
    $summaryData[] = $groupRow;
}

?>
<!DOCTYPE html>
<html lang="da">

<head>
    <meta charset="UTF-8">
    <title>Light Report - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            margin: 0;
            background: #eee;
            padding: 20px;
        }

        .page {
            background: white;
            margin: 0 auto 30px auto;
            padding: 15mm;
            width: 297mm;
            min-height: 210mm;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .landscape {
            width: 297mm;
        }

        /* A4 Landscape */

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background: #e0e0e0;
            font-weight: 600;
            text-align: center;
        }

        .header-title {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .group-row {
            background: #f0f0f0;
            font-weight: bold;
        }

        .num-cell {
            text-align: right;
            white-space: nowrap;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                width: 100%;
                height: auto;
            }

            .no-print {
                display: none;
            }
        }

        /* Detail Table Specifics */
        .detail-img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border: 1px solid #ddd;
            background: #f9f9f9;
            display: block;
            margin-bottom: 5px;
        }

        .risk-box {
            width: 15px;
            height: 15px;
            border: 1px solid #999;
            display: inline-block;
            margin-right: 2px;
        }

        .risk-selected {
            background: black;
        }

        /* Or checkmark */
        .risk-Rød {
            background: #ffcccc;
        }

        .risk-Gul {
            background: #ffffcc;
        }

        .risk-Grøn {
            background: #ccffcc;
        }

        .flag-checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #333;
            margin: 0 2px;
            text-align: center;
            line-height: 10px;
            font-size: 10px;
        }
    </style>
</head>

<body>

    <div class="no-print"
        style="position:fixed; top:10px; right:10px; background:white; padding:10px; border:1px solid #ccc; z-index:999;">
        <button onclick="window.print()">Print / PDF</button>
    </div>

    <!-- PAGE 1: SUMMARY -->
    <div class="page landscape">
        <div class="header-title">3. Building elements across the portfolio</div>

        <div style="margin-bottom: 10px; font-size: 0.9em;">
            All rates are trade prices excluding VAT in DKK.
        </div>

        <table>
            <thead>
                <tr style="background:#ddd;">
                    <th rowspan="2" style="text-align:left; width:25%;">Building parts</th>
                    <th colspan="4" style="background:#e0e0e0; border-bottom:1px solid #999;">Expected timeframe for
                        execution</th>
                    <th colspan="5" style="background:#d0d0d0; border-bottom:1px solid #999;">Estimates*</th>
                </tr>
                <tr style="font-size:8pt;">
                    <th style="width:10%;">&lt; 1 year</th>
                    <th style="width:10%;">1-2 years</th>
                    <th style="width:10%;">3-5 years</th>
                    <th style="width:10%;">6-10 years</th>
                    <th style="width:10%; background:#f0f0f0;">Contractor</th>
                    <th style="width:5%;">Unforeseen / risks (0%)</th>
                    <th style="width:5%;">Support (0%)</th>
                    <th style="width:5%;">Mgmt (0%)</th>
                    <th style="width:5%;">Consultancy (0%)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotalContractor = 0;
                $idx = 1;
                foreach ($summaryData as $grp):
                    $grpContractor = array_sum($grp['buckets']);
                    $grandTotalContractor += $grpContractor;
                    ?>
                    <tr class="group-row">
                        <td style="text-align:left;"><?= $idx ?> - <?= htmlspecialchars($grp['name']) ?></td>
                        <td class="num-cell"><?= number_format($grp['buckets']['<1'], 0, ',', '.') ?> kr.</td>
                        <td class="num-cell"><?= number_format($grp['buckets']['1-2'], 0, ',', '.') ?> kr.</td>
                        <td class="num-cell"><?= number_format($grp['buckets']['3-5'], 0, ',', '.') ?> kr.</td>
                        <td class="num-cell"><?= number_format($grp['buckets']['6-10'], 0, ',', '.') ?> kr.</td>
                        <!-- Contractor Sum -->
                        <td class="num-cell" style="background:#fafafa;"><?= number_format($grpContractor, 0, ',', '.') ?>
                            kr.</td>
                        <td class="num-cell">-</td>
                        <td class="num-cell">-</td>
                        <td class="num-cell">-</td>
                        <td class="num-cell">-</td>
                    </tr>
                    <?php $subIdx = 1;
                    foreach ($grp['children'] as $child):
                        $childContractor = array_sum($child['buckets']);
                        ?>
                        <tr>
                            <td style="padding-left: 20px;"><?= $idx ?>.<?= $subIdx ?>         <?= htmlspecialchars($child['name']) ?>
                            </td>
                            <td class="num-cell">
                                <?= $child['buckets']['<1'] > 0 ? number_format($child['buckets']['<1'], 0, ',', '.') . ' kr.' : '-' ?>
                            </td>
                            <td class="num-cell">
                                <?= $child['buckets']['1-2'] > 0 ? number_format($child['buckets']['1-2'], 0, ',', '.') . ' kr.' : '-' ?>
                            </td>
                            <td class="num-cell">
                                <?= $child['buckets']['3-5'] > 0 ? number_format($child['buckets']['3-5'], 0, ',', '.') . ' kr.' : '-' ?>
                            </td>
                            <td class="num-cell">
                                <?= $child['buckets']['6-10'] > 0 ? number_format($child['buckets']['6-10'], 0, ',', '.') . ' kr.' : '-' ?>
                            </td>
                            <!-- Child Contractor -->
                            <td class="num-cell" style="background:#fafafa;">
                                <?= $childContractor > 0 ? number_format($childContractor, 0, ',', '.') . ' kr.' : '-' ?>
                            </td>
                            <td class="num-cell">0%</td>
                            <td class="num-cell">0%</td>
                            <td class="num-cell">0%</td>
                            <td class="num-cell">0%</td>
                        </tr>
                        <?php $subIdx++; endforeach; ?>
                    <?php $idx++; endforeach; ?>
                <tr style="background:#333; color:white; font-weight:bold; border-top:2px solid black;">
                    <td>TOTAL</td>
                    <td class="num-cell"><?= number_format($totalBuckets['<1'], 0, ',', '.') ?> kr.</td>
                    <td class="num-cell"><?= number_format($totalBuckets['1-2'], 0, ',', '.') ?> kr.</td>
                    <td class="num-cell"><?= number_format($totalBuckets['3-5'], 0, ',', '.') ?> kr.</td>
                    <td class="num-cell"><?= number_format($totalBuckets['6-10'], 0, ',', '.') ?> kr.</td>
                    <td class="num-cell"><?= number_format($grandTotalContractor, 0, ',', '.') ?> kr.</td>
                    <td class="num-cell" colspan="4"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGE 2+: DETAILS -->
    <div class="page landscape">
        <div class="header-title">Detailed Building Elements</div>

        <table>
            <thead>
                <tr style="background:#e0e0e0;">
                    <th rowspan="2" style="width: 15%;">Category</th>
                    <th rowspan="2" style="width: 25%;">Description of error, deficiency, or damage</th>
                    <th rowspan="2" style="width: 15%;">Attachment</th>
                    <th rowspan="2" style="width: 20%;">Remarks / Recommendation</th>
                    <th rowspan="2" style="width: 10%;">Quantification</th>
                    <th colspan="5" style="width: 10%;">Risk assessment</th>
                    <th colspan="4" style="width: 10%;">Expected timeframe</th>
                </tr>
                <tr style="font-size:7pt;">
                    <th title="Red">🔴</th>
                    <th title="Yellow">🟡</th>
                    <th title="Black/Green">⚫️</th>
                    <th title="Green">🟢</th>
                    <th title="Blue">🔵</th>
                    <th>&lt;1</th>
                    <th>1-2</th>
                    <th>3-5</th>
                    <th>6-10</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $gCounter = 1;
                foreach ($tree as $group):
                    ?>
                    <tr class="group-row">
                        <td colspan="15"><?= $gCounter ?> - <?= t($group['name'], $translations) ?></td>
                    </tr>
                    <?php
                    $cCounter = 1;
                    foreach ($group['children'] as $child):
                        $images = $child['media'] ?? [];
                        $imgHtml = '';
                        if (!empty($images)) {
                            foreach ($images as $img) {
                                $path = $img['file_path'];
                                if (strpos($path, 'assets/') === 0)
                                    $src = '/' . $path;
                                else if (strpos($path, '/') === 0)
                                    $src = $path;
                                else
                                    $src = '/assets/uploads/' . $path;
                                $imgHtml .= "<img src='{$src}' class='detail-img'>";
                            }
                        }

                        $risk = $child['risk_level'] ?? '';
                        // Risk Logic matches standard: 'Rød', 'Gul', 'Grøn'
                        // Users might want exact flags. We map what we have.
                        // Rød (Critical) -> Red Flag
                        // Gul (Attention) -> Yellow Flag
                        // Grøn (Good) -> Green Flag
                        // Black/Blue? We don't have them in enum yet, but we map 'Ikke relevant' or others?
                
                        $r = strpos($risk, 'Rød') !== false ? '☒' : '☐';
                        $y = strpos($risk, 'Gul') !== false ? '☒' : '☐';
                        // Mapping Green to the 4th slot (Green flag in PDF seems to be 4th)
                        // 3rd is Black? 5th is Blue?
                        $g = strpos($risk, 'Grøn') !== false ? '☒' : '☐';
                        $b = '☐'; // Blue placeholder
                        $blk = '☐'; // Black placeholder
                        // Timeframe Checks
                        // Marker is 'BCL' if is_bcl flag is true, otherwise 'X'? 
                        // User asked for "BCL markers", defaulting to 'BCL' if flagged.
                        $marker = (!empty($child['is_bcl']) && $child['is_bcl'] == 1) ? 'BCL' : 'X';

                        $t1 = strpos($risk, 'Rød') !== false ? $marker : '';
                        $t2 = strpos($risk, 'Gul') !== false ? $marker : '';
                        $t3 = strpos($risk, 'Grøn') !== false ? $marker : '';
                        // Default to marker if cost exists but no risk, placed in 6-10?
                        $t4 = (!$t1 && !$t2 && !$t3 && $child['capex'] > 0) ? $marker : '';

                        ?>
                        <tr>
                            <td><b><?= $gCounter ?>.<?= $cCounter ?>
                                    <?= htmlspecialchars(t($child['name'], $translations)) ?></b></td>
                            <td style="font-size:0.9em;"><?= nl2br(htmlspecialchars($child['description'] ?? '')) ?></td>
                            <td style="text-align:center;"><?= $imgHtml ?></td>
                            <td style="font-size:0.9em;"><?= nl2br(htmlspecialchars($child['recommendation'] ?? '')) ?></td>
                            <td class="num-cell" style="text-align:left; font-size:0.9em;">
                                <?= htmlspecialchars($child['name']) ?>
                                <?php if (!empty($child['quantity'])): ?>
                                    <br><span style="color:#555;"><?= $child['quantity'] ?>             <?= $child['unit'] ?? 'stk' ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Risk Flags -->
                            <td style="text-align:center; color:red;"><?= $r ?></td>
                            <td style="text-align:center; color:#d4ac0d;"><?= $y ?></td>
                            <td style="text-align:center;"><?= $blk ?></td>
                            <td style="text-align:center; color:green;"><?= $g ?></td>
                            <td style="text-align:center; color:blue;"><?= $b ?></td>

                            <!-- Timeframe -->
                            <td style="text-align:center; font-size:0.8em;"><?= $t1 ?></td>
                            <td style="text-align:center; font-size:0.8em;"><?= $t2 ?></td>
                            <td style="text-align:center; font-size:0.8em;"><?= $t3 ?></td>
                            <td style="text-align:center; font-size:0.8em;"><?= $t4 ?></td>
                        </tr>
                        <?php
                        $cCounter++;
                    endforeach;
                    $gCounter++;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>

</body>

</html>