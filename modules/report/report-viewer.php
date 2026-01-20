<?php
/**
 * HTML Report Viewer
 * Viser rapport som HTML med print-venligt A4 layout
 * Inkluderer TOC sidebar navigation (skjules ved print)
 */

require_once __DIR__ . '/../../core/init.php';

// Get report ID
$reportId = sanitize_int($_GET['id'] ?? 0);

if (!$reportId) {
    die('Rapport ID mangler');
}

// Get report data
$report = db_fetch("
    SELECT r.*, u.name as generated_by_name
    FROM reports r
    LEFT JOIN users u ON u.id = r.generated_by_user_id
    WHERE r.id = :id
", ['id' => $reportId]);

if (!$report) {
    die('Rapport ikke fundet');
}

// Check access
if (!can_access_project($currentUser, $report['project_id'], 'viewer')) {
    die('Ingen adgang til rapporten');
}

// Decode report data
$reportData = json_decode($report['report_data'], true);
$project = $reportData['project'];
$buildings = $reportData['buildings'];
$redFlagsSummary = $reportData['red_flags_summary'];
$topRedFlags = $reportData['top_red_flags'];

// Generate page title
$pageTitle = $report['title'] . ' - ' . $project['project_name'];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc_html($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/report-print.css">
</head>
<body class="report-viewer">

    <!-- Table of Contents Sidebar (skjules ved print) -->
    <nav class="report-toc" id="reportTOC">
        <div class="toc-header">
            <h2>Indhold</h2>
            <button class="btn-icon" onclick="toggleTOC()" aria-label="Luk indholdsfortegnelse">
                <?= icon('x', 20) ?>
            </button>
        </div>

        <div class="toc-content">
            <a href="#cover" class="toc-item toc-level-1">Forside</a>
            <a href="#executive-summary" class="toc-item toc-level-1">Executive Summary</a>
            <a href="#key-metrics" class="toc-item toc-level-1">Nøgletal</a>

            <?php if (!empty($buildings)): ?>
            <a href="#buildings" class="toc-item toc-level-1">Bygninger</a>
            <?php foreach ($buildings as $i => $building): ?>
                <a href="#building-<?= $building['building_id'] ?>" class="toc-item toc-level-2">
                    <?= esc_html($building['building_name']) ?>
                </a>
                <?php if (!empty($building['elements'])): ?>
                    <?php foreach ($building['elements'] as $element): ?>
                        <a href="#element-<?= $element['element_id'] ?>" class="toc-item toc-level-3">
                            <?= esc_html($element['element_name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>

            <a href="#summary-tables" class="toc-item toc-level-1">Opsummerende Tabeller</a>
            <a href="#red-flags" class="toc-item toc-level-1">Røde Flag</a>
            <a href="#appendix" class="toc-item toc-level-1">Appendix</a>
        </div>

        <div class="toc-footer">
            <button class="btn btn-secondary btn-block" onclick="window.print()">
                <?= icon('printer', 18) ?> Print Rapport
            </button>
            <button class="btn btn-secondary btn-block" onclick="window.close()">
                <?= icon('x', 18) ?> Luk
            </button>
        </div>
    </nav>

    <!-- Main Report Content -->
    <main class="report-content" id="reportContent">

        <!-- Cover Page -->
        <section class="report-page page-cover" id="cover">
            <div class="cover-content">
                <div class="cover-logo">
                    <?= icon('file-text', 80) ?>
                </div>

                <h1 class="cover-title"><?= esc_html($report['title']) ?></h1>

                <h2 class="cover-subtitle"><?= esc_html($project['project_name']) ?></h2>

                <div class="cover-meta">
                    <div class="meta-item">
                        <strong>Rapport Type:</strong>
                        <?= esc_html(ucfirst(str_replace('_', ' ', $report['report_type']))) ?>
                    </div>
                    <div class="meta-item">
                        <strong>Genereret:</strong>
                        <?= date('d. F Y', strtotime($report['created_at'])) ?>
                    </div>
                    <div class="meta-item">
                        <strong>Udarbejdet af:</strong>
                        <?= esc_html($report['generated_by_name']) ?>
                    </div>
                </div>

                <div class="cover-footer">
                    <p>DueDiligence v2.0</p>
                    <p>&copy; <?= date('Y') ?></p>
                </div>
            </div>
        </section>

        <!-- Executive Summary -->
        <section class="report-page" id="executive-summary">
            <h1 class="page-title">Executive Summary</h1>

            <div class="summary-intro">
                <p>
                    Denne rapport præsenterer en omfattende analyse af projektet
                    <strong><?= esc_html($project['project_name']) ?></strong>.
                    Rapporten omfatter <?= $project['building_count'] ?> bygninger med i alt
                    <?= $project['element_count'] ?> elementer.
                </p>
            </div>

            <div class="summary-highlights">
                <div class="highlight-card">
                    <div class="highlight-icon" style="color: #0066cc;">
                        <?= icon('building', 32) ?>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-value"><?= $project['building_count'] ?></div>
                        <div class="highlight-label">Bygninger</div>
                    </div>
                </div>

                <div class="highlight-card">
                    <div class="highlight-icon" style="color: #0066cc;">
                        <?= icon('list', 32) ?>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-value"><?= $project['element_count'] ?></div>
                        <div class="highlight-label">Elementer</div>
                    </div>
                </div>

                <div class="highlight-card">
                    <div class="highlight-icon" style="color: #009900;">
                        <?= icon('dollar-sign', 32) ?>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-value">
                            DKK <?= number_format($project['total_capex'], 0, ',', '.') ?>
                        </div>
                        <div class="highlight-label">Total CAPEX</div>
                    </div>
                </div>

                <div class="highlight-card">
                    <div class="highlight-icon" style="color: #cc0000;">
                        <?= icon('alert-triangle', 32) ?>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-value"><?= $project['critical_count'] ?></div>
                        <div class="highlight-label">Kritiske Elementer</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Key Metrics Table -->
        <section class="report-page" id="key-metrics">
            <h1 class="page-title">Nøgletal</h1>

            <table class="report-table">
                <caption>Tabel 1: Projekt Oversigt</caption>
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Værdi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Antal bygninger</td>
                        <td><?= $project['building_count'] ?></td>
                    </tr>
                    <tr>
                        <td>Antal elementer</td>
                        <td><?= $project['element_count'] ?></td>
                    </tr>
                    <tr>
                        <td>Total CAPEX</td>
                        <td>DKK <?= number_format($project['total_capex'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td>Kritiske elementer</td>
                        <td class="text-danger"><?= $project['critical_count'] ?></td>
                    </tr>
                    <tr>
                        <td>Høj prioritet elementer</td>
                        <td class="text-warning"><?= $project['high_count'] ?></td>
                    </tr>
                    <tr>
                        <td>Dårlig tilstand elementer</td>
                        <td class="text-warning"><?= $project['poor_count'] ?></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Buildings Section -->
        <?php if (!empty($buildings)): ?>
        <section class="report-page page-break-before" id="buildings">
            <h1 class="page-title">Bygninger</h1>
            <p class="section-intro">
                Dette afsnit indeholder detaljerede oplysninger om projektets bygninger og deres elementer.
            </p>
        </section>

        <?php
        $figureCounter = 0;
        $tableCounter = 1;

        foreach ($buildings as $buildingIndex => $building):
        ?>
        <section class="report-page page-break-before" id="building-<?= $building['building_id'] ?>">
            <h1 class="page-title"><?= esc_html($building['building_name']) ?></h1>

            <table class="report-table">
                <caption>Tabel <?= ++$tableCounter ?>: Bygningsinformation</caption>
                <tbody>
                    <tr>
                        <th>Bygningsnummer</th>
                        <td><?= esc_html($building['building_number'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td><?= esc_html($building['building_type'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Bruttoareal</th>
                        <td><?= number_format($building['gross_area'], 0, ',', '.') ?> m²</td>
                    </tr>
                    <tr>
                        <th>Antal elementer</th>
                        <td><?= $building['element_count'] ?></td>
                    </tr>
                    <tr>
                        <th>CAPEX</th>
                        <td>DKK <?= number_format($building['total_capex'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <th>Gennemsnitlig tilstand</th>
                        <td><?= render_condition_badge($building['avg_condition']) ?></td>
                    </tr>
                    <tr>
                        <th>Kritiske elementer</th>
                        <td class="text-danger"><?= $building['critical_count'] ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ($report['include_budgets'] && !empty($building['opex'])): ?>
            <div class="info-box">
                <h3>OPEX Information</h3>
                <p>
                    <strong>Årlig OPEX:</strong>
                    DKK <?= number_format($building['opex']['effective_opex_yearly'], 0, ',', '.') ?>
                </p>
                <p>
                    <strong>OPEX per m²:</strong>
                    DKK <?= number_format($building['opex']['opex_per_sqm'], 0, ',', '.') ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Building Elements -->
            <?php if (!empty($building['elements'])): ?>
            <h2 class="section-heading">Bygningselementer</h2>

            <?php foreach ($building['elements'] as $element): ?>
                <?= render_element_section($element, 2, $figureCounter, $report['include_images']) ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Summary Tables -->
        <section class="report-page page-break-before" id="summary-tables">
            <h1 class="page-title">Opsummerende Tabeller</h1>

            <!-- CAPEX Overview -->
            <table class="report-table">
                <caption>Tabel <?= ++$tableCounter ?>: CAPEX Oversigt per Bygning</caption>
                <thead>
                    <tr>
                        <th>Bygning</th>
                        <th>Antal Elementer</th>
                        <th class="text-right">CAPEX (DKK)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buildings as $b): ?>
                    <tr>
                        <td><?= esc_html($b['building_name']) ?></td>
                        <td><?= $b['element_count'] ?></td>
                        <td class="text-right"><?= number_format($b['total_capex'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-total">
                        <th>Total</th>
                        <th><?= array_sum(array_column($buildings, 'element_count')) ?></th>
                        <th class="text-right"><?= number_format($project['total_capex'], 0, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>

            <!-- Red Flags Summary -->
            <?php if (!empty($redFlagsSummary)): ?>
            <table class="report-table">
                <caption>Tabel <?= ++$tableCounter ?>: Røde Flag Oversigt</caption>
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th class="text-center">Antal</th>
                        <th class="text-right">CAPEX (DKK)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Kritisk hastighed</td>
                        <td class="text-center"><?= $redFlagsSummary['critical_urgency_count'] ?></td>
                        <td class="text-right"><?= number_format($redFlagsSummary['critical_urgency_capex'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td>Dårlig tilstand</td>
                        <td class="text-center"><?= $redFlagsSummary['poor_condition_count'] ?></td>
                        <td class="text-right"><?= number_format($redFlagsSummary['poor_condition_capex'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td>Høje omkostninger</td>
                        <td class="text-center"><?= $redFlagsSummary['high_cost_count'] ?></td>
                        <td class="text-right"><?= number_format($redFlagsSummary['high_cost_capex'], 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <!-- Red Flags Section -->
        <?php if (!empty($topRedFlags)): ?>
        <section class="report-page page-break-before" id="red-flags">
            <h1 class="page-title">Top Røde Flag</h1>

            <table class="report-table">
                <caption>Tabel <?= ++$tableCounter ?>: Top 20 Røde Flag</caption>
                <thead>
                    <tr>
                        <th>Element</th>
                        <th>Bygning</th>
                        <th class="text-center">Score</th>
                        <th class="text-center">Alvorlighed</th>
                        <th class="text-right">CAPEX (DKK)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topRedFlags as $flag): ?>
                    <tr>
                        <td><?= esc_html($flag['element_name']) ?></td>
                        <td><?= esc_html($flag['building_name']) ?></td>
                        <td class="text-center"><?= number_format($flag['red_flag_score'], 1) ?></td>
                        <td class="text-center"><?= render_severity_badge($flag['severity']) ?></td>
                        <td class="text-right"><?= number_format($flag['capex'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <!-- Appendix -->
        <section class="report-page page-break-before" id="appendix">
            <h1 class="page-title">Appendix A: Definitioner</h1>

            <h2 class="section-heading">Tilstandsscorer</h2>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Score</th>
                        <th>Rating</th>
                        <th>Beskrivelse</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>5</td>
                        <td>Fremragende</td>
                        <td>Som ny tilstand, ingen mangler</td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>God</td>
                        <td>Normal vedligeholdelse, få mindre mangler</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Rimelig</td>
                        <td>Flere mindre mangler, normal vedligeholdelse påkrævet</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Dårlig</td>
                        <td>Betydelige mangler, øget vedligeholdelse påkrævet</td>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td>Meget dårlig</td>
                        <td>Kritiske mangler, øjeblikkelig handling påkrævet</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="section-heading">Hastighedskategorier</h2>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Tidsramme</th>
                        <th>Beskrivelse</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Akut</td>
                        <td>Øjeblikkeligt</td>
                        <td>Handling skal udføres med det samme</td>
                    </tr>
                    <tr>
                        <td>0-1 år</td>
                        <td>Indenfor 1 år</td>
                        <td>Skal håndteres inden for det næste år</td>
                    </tr>
                    <tr>
                        <td>1-2 år</td>
                        <td>Indenfor 2 år</td>
                        <td>Kan planlægges inden for 2 år</td>
                    </tr>
                    <tr>
                        <td>3-5 år</td>
                        <td>Mellemfristet</td>
                        <td>Mellemfristet planlægning</td>
                    </tr>
                    <tr>
                        <td>5-10 år</td>
                        <td>Langfristet</td>
                        <td>Langfristet planlægning</td>
                    </tr>
                </tbody>
            </table>
        </section>

    </main>

    <script>
        // TOC Functionality
        function toggleTOC() {
            document.getElementById('reportTOC').classList.toggle('toc-hidden');
        }

        // Highlight active section in TOC
        const tocLinks = document.querySelectorAll('.toc-item');
        const sections = document.querySelectorAll('.report-page[id]');

        function highlightTOC() {
            let current = '';

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.pageYOffset >= sectionTop - 100) {
                    current = section.getAttribute('id');
                }
            });

            tocLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', highlightTOC);
        highlightTOC();

        // Smooth scroll to sections
        tocLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>

<?php

/**
 * Helper function to render element section recursively
 */
function render_element_section($element, $level, &$figureCounter, $includeImages) {
    $headingTag = 'h' . min($level + 1, 6);

    ob_start();
    ?>

    <div class="element-section" id="element-<?= $element['element_id'] ?>">
        <<?= $headingTag ?> class="element-heading">
            <?= esc_html($element['element_name']) ?>
        </<?= $headingTag ?>>

        <?php if (!empty($element['element_code'])): ?>
        <p class="element-code">Element kode: <strong><?= esc_html($element['element_code']) ?></strong></p>
        <?php endif; ?>

        <table class="element-table">
            <tbody>
                <?php if (!empty($element['capex'])): ?>
                <tr>
                    <th>CAPEX</th>
                    <td>DKK <?= number_format($element['capex'], 0, ',', '.') ?></td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($element['urgency'])): ?>
                <tr>
                    <th>Hastighed</th>
                    <td><?= render_urgency_badge($element['urgency']) ?></td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($element['condition_score'])): ?>
                <tr>
                    <th>Tilstand</th>
                    <td><?= render_condition_badge($element['condition_score']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($element['observation'])): ?>
        <div class="element-observation">
            <strong>Observation:</strong>
            <p><?= nl2br(esc_html($element['observation'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($element['recommendation'])): ?>
        <div class="element-recommendation">
            <strong>Anbefaling:</strong>
            <p><?= nl2br(esc_html($element['recommendation'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($includeImages && !empty($element['images'])): ?>
        <div class="element-images">
            <?php foreach ($element['images'] as $image): ?>
                <?php $figureCounter++; ?>
                <figure class="report-figure">
                    <img src="<?= esc_attr($image['file_path']) ?>"
                         alt="<?= esc_attr($image['caption'] ?? $element['element_name']) ?>">
                    <figcaption>
                        <strong>Figur <?= $figureCounter ?>:</strong>
                        <?= esc_html($image['caption'] ?? $element['element_name']) ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($element['children'])): ?>
        <div class="element-children">
            <?php foreach ($element['children'] as $child): ?>
                <?= render_element_section($child, $level + 1, $figureCounter, $includeImages) ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * Helper functions for badges
 */
function render_condition_badge($score) {
    $labels = [
        5 => 'Fremragende',
        4 => 'God',
        3 => 'Rimelig',
        2 => 'Dårlig',
        1 => 'Meget dårlig'
    ];

    $colors = [
        5 => 'success',
        4 => 'success',
        3 => 'warning',
        2 => 'danger',
        1 => 'danger'
    ];

    $label = $labels[$score] ?? 'Ukendt';
    $color = $colors[$score] ?? 'secondary';

    return '<span class="badge badge-' . $color . '">' . $label . ' (' . $score . ')</span>';
}

function render_urgency_badge($urgency) {
    $colors = [
        'Akut' => 'danger',
        '0-1 år' => 'danger',
        '1-2 år' => 'warning',
        '3-5 år' => 'info',
        '5-10 år' => 'success'
    ];

    $color = $colors[$urgency] ?? 'secondary';

    return '<span class="badge badge-' . $color . '">' . esc_html($urgency) . '</span>';
}

function render_severity_badge($severity) {
    $colors = [
        'Kritisk' => 'danger',
        'Høj' => 'warning',
        'Mellem' => 'info',
        'Lav' => 'success'
    ];

    $color = $colors[$severity] ?? 'secondary';

    return '<span class="badge badge-' . $color . '">' . esc_html($severity) . '</span>';
}
