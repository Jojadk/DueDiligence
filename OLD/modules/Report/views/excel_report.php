<?php
// Report Layout - Landscape A4
// Based on old/full_report.php but simplified for preview/print
// No Excel headers


?>
<!DOCTYPE html>
<html lang="da">

<head>
    <meta charset="UTF-8">
    <title>Tilstandsrapport - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background: #525659;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .page {
            width: 297mm;
            /* Landscape */
            height: 210mm;
            /* Landscape */
            padding: 15mm;
            margin: 0 auto 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .page {
                width: 100%;
                height: 100%;
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }

            .no-print {
                display: none !important;
            }
        }

        h1 {
            font-size: 24pt;
            color: #2c3e50;
        }

        h2 {
            font-size: 18pt;
            color: #34495e;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        tr {
            page-break-inside: avoid;
        }


        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 10pt;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .cover-page {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            height: 100%;
        }

        .cover-title {
            font-size: 36pt;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .cover-subtitle {
            font-size: 18pt;
            color: #7f8c8d;
            margin-bottom: 40px;
        }

        .cover-info {
            font-size: 12pt;
            color: #333;
        }
    </style>
</head>

<body>

    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">🖨️ Udskriv / Gem
            som PDF</button>
    </div>

    <!-- Cover Page -->
    <div class="page">
        <div style="position: absolute; top: 10mm; right: 15mm; font-size: 16px; font-weight: bold; color: #333;">SWECO
            <span style="color:#FFC20E">＊</span>
        </div>
        <div style="position: absolute; top: 10mm; left: 15mm; font-size: 12px; color: #666;"><?= date('d.m.Y') ?></div>

        <div class="cover-page">
            <div class="cover-title"><?= htmlspecialchars($project['name']) ?></div>
            <div class="cover-subtitle">Tilstandsrapport</div>

            <?php if (!empty($project['cover_image'])): ?>
                <img src="/assets/uploads/<?= htmlspecialchars($project['cover_image']) ?>"
                    style="max-width: 80%; max-height: 400px; margin-bottom: 30px; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <?php endif; ?>

            <div class="cover-info">
                <p><strong>Kunde:</strong> <?= htmlspecialchars($client['name'] ?? '-') ?></p>
                <p><strong>Adresse:</strong> <?= htmlspecialchars($project['address'] ?? '-') ?></p>
            </div>
        </div>
    </div>

    <!-- Table of Contents -->
    <div class="page">
        <div style="position: absolute; top: 10mm; right: 15mm; font-size: 16px; font-weight: bold; color: #333;">SWECO
            <span style="color:#FFC20E">＊</span>
        </div>
        <h2>Indholdsfortegnelse</h2>
        <ul style="list-style:none; padding:0; font-size:11pt; line-height:1.5;">
            <li style="margin-bottom:5px;"><span style="display:inline-block; width:30px;">1.</span> Indledning</li>
            <?php $tocIdx = 2;
            foreach ($tree as $node): ?>
                <li style="margin-bottom:5px;">
                    <span style="display:inline-block; width:30px;"><?= $tocIdx++ ?>.</span>
                    <?= htmlspecialchars($node['name']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Introduction Page -->
    <div class="page">
        <div style="position: absolute; top: 10mm; right: 15mm; font-size: 16px; font-weight: bold; color: #333;">SWECO
            <span style="color:#FFC20E">＊</span>
        </div>
        <div style="margin-top:20px;">
            <h2 style="background:#ddd; padding:5px; border:none;">1. Introduction</h2>
            <div style="font-size: 11pt; line-height: 1.6; margin-bottom: 20px; white-space: pre-wrap;">
                <?= htmlspecialchars($project['report_intro'] ?? '') ?>
            </div>

            <h2 style="background:#ddd; padding:5px; border:none;">1.1 Definitions</h2>
            <div style="font-size: 11pt; line-height: 1.6; white-space: pre-wrap;">
                <?= htmlspecialchars($project['report_disclaimer'] ?? '') ?>
            </div>
        </div>
    </div>


    <!-- Overview Page -->
    <div class="page">
        <div style="position: absolute; top: 10mm; right: 15mm; font-size: 16px; font-weight: bold; color: #333;">SWECO
            <span style="color:#FFC20E">＊</span>
        </div>
        <h2>Oversigt</h2>

        <table>
            <thead>
                <tr style="background:#ddd;">
                    <th>Bygningsdel</th>
                    <th>
                        < 1 year</th>
                    <th>1-2 years</th>
                    <th>3-5 years</th>
                    <th>6-10 years</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotal = 0;
                $bucketsTotal = ['0-1' => 0, '1-2' => 0, '3-5' => 0, '6-10' => 0];

                foreach ($tree as $node):
                    // Calculate Node Totals (Recursive)
                    $nodeBuckets = ['0-1' => 0, '1-2' => 0, '3-5' => 0, '6-10' => 0];
                    $nodeTotal = 0;

                    // Helper for recursion
                    $calcNode = function ($n, &$bkt, &$tot) use (&$calcNode) {
                        // Current Node Cost
                        $budgetItems = $n['budget_items'] ?? [];
                        foreach ($budgetItems as $item) {
                            $bkt['0-1'] += $item['amount_0_1'] ?? 0;
                            $bkt['1-2'] += $item['amount_1_2'] ?? 0;
                            $bkt['3-5'] += $item['amount_3_5'] ?? 0;
                            $bkt['6-10'] += $item['amount_5_10'] ?? 0;

                            $tot += $item['total_calculated'];
                        }
                        // Children
                        if (!empty($n['children'])) {
                            foreach ($n['children'] as $child) {
                                $calcNode($child, $bkt, $tot);
                            }
                        }
                    };

                    $calcNode($node, $nodeBuckets, $nodeTotal);

                    // Add to Grand Totals
                    foreach ($bucketsTotal as $k => $v)
                        $bucketsTotal[$k] += $nodeBuckets[$k];
                    $grandTotal += $nodeTotal;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($node['name']) ?></strong></td>
                        <td><?= $nodeBuckets['0-1'] ? number_format($nodeBuckets['0-1'], 0, ',', '.') : '-' ?></td>
                        <td><?= $nodeBuckets['1-2'] ? number_format($nodeBuckets['1-2'], 0, ',', '.') : '-' ?></td>
                        <td><?= $nodeBuckets['3-5'] ? number_format($nodeBuckets['3-5'], 0, ',', '.') : '-' ?></td>
                        <td><?= $nodeBuckets['6-10'] ? number_format($nodeBuckets['6-10'], 0, ',', '.') : '-' ?></td>
                        <td><strong><?= $nodeTotal ? number_format($nodeTotal, 0, ',', '.') : '-' ?></strong></td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#eee; font-weight:bold;">
                    <td>Total sum, incl. All works</td>
                    <td><?= number_format($bucketsTotal['0-1'], 0, ',', '.') ?></td>
                    <td><?= number_format($bucketsTotal['1-2'], 0, ',', '.') ?></td>
                    <td><?= number_format($bucketsTotal['3-5'], 0, ',', '.') ?></td>
                    <td><?= number_format($bucketsTotal['6-10'], 0, ',', '.') ?></td>
                    <td><?= number_format($grandTotal, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Detailed Section -->
    <?php
    $riskIcons = [
        'Rød' => '<span style="color:red; font-size:1.2em;">🔴</span>',
        'Gul' => '<span style="color:orange; font-size:1.2em;">🟡</span>',
        'Grøn' => '<span style="color:green; font-size:1.2em;">🟢</span>',
        'Ikke relevant' => '⚪️'
    ];
    ?>

    <?php foreach ($tree as $category): ?>
        <div class="page">
            <div style="position: absolute; top: 10mm; right: 15mm; font-size: 16px; font-weight: bold; color: #333;">SWECO
                <span style="color:#FFC20E">＊</span>
            </div>
            <h2 style="background:#ddd; padding:5px; margin-top:0; border:1px solid #000; border-bottom:none;">
                <?php $catIdx = array_search($category, $tree) + 2; // 1 is Intro ?>
                <?= $catIdx ?>. <?= htmlspecialchars($category['name']) ?>
            </h2>

            <table style="font-size: 8pt; border: 1px solid #000; width:100%;">
                <thead>
                    <tr style="background:#f0f0f0;">
                        <th rowspan="2" width="10%" style="border:1px solid #000;">Bygningsdel</th>
                        <th rowspan="2" width="18%" style="border:1px solid #000;">Observation</th>
                        <th rowspan="2" width="12%" style="border:1px solid #000;">Foto</th>
                        <th rowspan="2" width="18%" style="border:1px solid #000;">Anbefaling</th>
                        <th rowspan="2" width="6%" style="border:1px solid #000;">Omfang</th>
                        <th colspan="5" style="border:1px solid #000; text-align:center;">Risiko</th>
                        <th colspan="4" style="border:1px solid #000; text-align:center;">Forventet udførelse</th>
                    </tr>
                    <tr style="background:#f0f0f0;">
                        <!-- Risk Subcols -->
                        <th style="border:1px solid #000; text-align:center; min-width:15px; background:#ffcccc;">🔴</th>
                        <th style="border:1px solid #000; text-align:center; min-width:15px; background:#fff5cc;">🟡</th>
                        <th style="border:1px solid #000; text-align:center; min-width:15px; background:#ccc;">⚫️</th>
                        <th style="border:1px solid #000; text-align:center; min-width:15px; background:#ccffcc;">🟢</th>
                        <th style="border:1px solid #000; text-align:center; min-width:15px; background:#cce5ff;">🔵</th>
                        <!-- Timeframe Subcols -->
                        <th style="border:1px solid #000; text-align:center; width:6%;">
                            < 1 år</th>
                        <th style="border:1px solid #000; text-align:center; width:6%;">1-2 år</th>
                        <th style="border:1px solid #000; text-align:center; width:6%;">3-5 år</th>
                        <th style="border:1px solid #000; text-align:center; width:6%;">6-10 år</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $startNodes = isset($category['children']) ? $category['children'] : [];

                    $renderRows = function ($nodes, $level = 0, $prefix = '') use (&$renderRows, $riskIcons) {
                        $idx = 1;
                        foreach ($nodes as $part):
                            $currentNum = $prefix ? ($prefix . '.' . $idx) : $idx;

                            // Costs
                            $pBuckets = ['0-1' => 0, '1-2' => 0, '3-5' => 0, '6-10' => 0];
                            $quantText = [];

                            $budgetItems = $part['budget_items'] ?? [];
                            foreach ($budgetItems as $item) {
                                $pBuckets['0-1'] += $item['amount_0_1'] ?? 0;
                                $pBuckets['1-2'] += $item['amount_1_2'] ?? 0;
                                $pBuckets['3-5'] += $item['amount_3_5'] ?? 0;
                                $pBuckets['6-10'] += $item['amount_5_10'] ?? 0;

                                if (!empty($item['description'])) {
                                    $q = (float) ($item['quantity'] ?? 0);
                                    $u = htmlspecialchars($item['unit'] ?? '');
                                    $d = htmlspecialchars($item['description']);
                                    $quantText[] = "$d $q $u";
                                }
                            }
                            $quantDisplay = implode(', ', $quantText);

                            // Media
                            $partMedia = $part['media'] ?? [];
                            $firstImg = !empty($partMedia) ? $partMedia[0] : null;
                            $firstImgPath = $firstImg ? $firstImg['file_path'] : null;

                            // Risk
                            $risk = $part['risk_level'] ?? '';
                            // Style
                            $pl = $level * 10;
                            $bg = $level == 0 ? '#fafafa' : '#fff';
                            $bold = $level == 0 ? 'font-weight:bold;' : '';
                            ?>
                            <tr style="background:<?= $bg ?>; page-break-inside: avoid;">
                                <td valign="top"
                                    style="border:1px solid #000; padding:4px; padding-left:<?= $pl + 4 ?>px; <?= $bold ?>">
                                    <?= $currentNum ?>             <?= htmlspecialchars($part['name']) ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; padding:4px;">
                                    <?= nl2br(htmlspecialchars($part['description'] ?? '')) ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; text-align:center; padding:4px;">
                                    <?php if ($firstImgPath): ?>
                                        <img src="/assets/uploads/<?= $firstImgPath ?>"
                                            style="max-width:80px; max-height:60px; display:block; margin:0 auto;">
                                        <div style="font-size:0.7em; color:#555; margin-top:2px; line-height:1.2;">
                                            Fig. <?= $currentNum ?>
                                            <?php
                                            $cap = !empty($firstImg['caption']) ? $firstImg['caption'] : ($firstImg['comment'] ?? '');
                                            if ($cap)
                                                echo '<br>' . nl2br(htmlspecialchars($cap));
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; padding:4px;">
                                    <?= nl2br(htmlspecialchars($part['recommendation'] ?? '')) ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; font-size:0.8em; text-align:left; padding:4px;">
                                    <?= $quantDisplay ?>
                                </td>

                                <!-- Risk Columns -->
                                <td style="border:1px solid #000; text-align:center;"><?= $risk == 'Rød' ? '☒' : '' ?></td>
                                <td style="border:1px solid #000; text-align:center;"><?= $risk == 'Gul' ? '☒' : '' ?></td>
                                <td style="border:1px solid #000; text-align:center;"><?= $risk == 'Sort' ? '☒' : '' ?></td>
                                <td style="border:1px solid #000; text-align:center;"><?= $risk == 'Grøn' ? '☒' : '' ?></td>
                                <td style="border:1px solid #000; text-align:center;"><?= $risk == 'Blå' ? '☒' : '' ?></td>

                                <!-- Timeframe Columns -->
                                <td valign="top" style="border:1px solid #000; text-align:right; padding:4px;">
                                    <?= $pBuckets['0-1'] ? number_format($pBuckets['0-1'], 0, ',', '.') : '' ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; text-align:right; padding:4px;">
                                    <?= $pBuckets['1-2'] ? number_format($pBuckets['1-2'], 0, ',', '.') : '' ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; text-align:right; padding:4px;">
                                    <?= $pBuckets['3-5'] ? number_format($pBuckets['3-5'], 0, ',', '.') : '' ?>
                                </td>
                                <td valign="top" style="border:1px solid #000; text-align:right; padding:4px;">
                                    <?= $pBuckets['6-10'] ? number_format($pBuckets['6-10'], 0, ',', '.') : '' ?>
                                </td>
                            </tr>
                            <?php
                            if (!empty($part['children'])) {
                                $renderRows($part['children'], $level + 1, $currentNum);
                            }
                            $idx++;
                        endforeach;
                    };

                    // Start keys should use the Category Index (e.g. 2, 3...) as prefix
                    // We need to calculate category index again or pass it
                    $catPrefix = (array_search($category, $tree) + 2); // Same logic as header
                    $renderRows($startNodes, 0, $catPrefix);
                    ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

</body>

</html>