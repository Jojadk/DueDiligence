<?php
/**
 * Text Review Report
 * Display all descriptions and recommendations for review.
 */
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'da' ?>">

<head>
    <meta charset="UTF-8">
    <title>Text Review -
        <?= htmlspecialchars($project['name']) ?>
    </title>
    <link rel="stylesheet" href="<?= \Core\Asset::css('assets/css/style.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        html,
        body {
            font-family: 'Inter', sans-serif;
            padding: 20px;
            background: #f4f6f8;
            overflow-y: auto;
            /* Ensure scroll */
            height: auto;
            min-height: 100%;
        }

        .review-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin-bottom: 20px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        tr:nth-child(even) {
            background: #fcfcfc;
        }

        .meta-col {
            width: 150px;
            font-size: 0.9em;
            color: #666;
        }

        .text-content {
            white-space: pre-wrap;
            font-size: 0.95em;
            line-height: 1.5;
        }

        .empty-text {
            color: #999;
            font-style: italic;
        }

        .btn-print {
            float: right;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .review-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>

<body>

    <div class="review-container">
        <a href="javascript:window.print()" class="btn-print">Print / PDF</a>
        <h1>Text Review:
            <?= htmlspecialchars($project['name']) ?>
        </h1>
        <p>Gennemgang af tekster, noter og anbefalinger for alle bygningsdele.</p>

        <table>
            <thead>
                <tr>
                    <th>Element</th>
                    <th>Beskrivelse</th>
                    <th>Noter / Tilstand</th>
                    <th>Anbefalinger</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elements as $el): ?>
                    <tr>
                        <td class="meta-col">
                            <strong>
                                <?= htmlspecialchars($el['name']) ?>
                            </strong><br>
                            <small>ID:
                                <?= $el['id'] ?>
                            </small><br>
                            <small>Parent:
                                <?= $el['parent_id'] ?: '-' ?>
                            </small>
                        </td>
                        <td>
                            <?php if (!empty($el['description'])): ?>
                                <div class="text-content">
                                    <?= htmlspecialchars($el['description']) ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-text">Ingen beskrivelse</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($el['notes'])): ?>
                                <div class="text-content">
                                    <?= htmlspecialchars($el['notes']) ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-text">-</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($el['recommendations'])): ?>
                                <div class="text-content">
                                    <?= htmlspecialchars($el['recommendations']) ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-text">-</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>