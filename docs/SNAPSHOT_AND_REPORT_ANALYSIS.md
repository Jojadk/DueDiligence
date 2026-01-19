# Snapshot og Report Funktionalitet - Analyse

**Dato**: 2026-01-19
**Status**: Delvist Implementeret

## Executive Summary

**Snapshots**: ✅ Grundlæggende funktionalitet eksisterer, men skal migreres fra OLD til aktiv kodebase
**Reports**: ⚠️ Data collection fungerer, men PDF generering med formatering mangler

---

## 1. Snapshot Funktionalitet

### 1.1 Nuværende Status

#### ✅ Hvad Eksisterer (i `/OLD/modules/Project/SnapshotManager.php`)

**Gemmer følgende data:**
```php
- Project metadata (navn, status, datoer, etc.)
- Building elements (komplet hierarki)
- Budget items (CAPEX, OPEX, timeline)
- Element media (billeder med captions, annotations)
- Custom field values (dynamiske felter)
- File manifest (alle filer med stier)
- Stats summary (total CAPEX, risk counts)
```

**Fil håndtering:**
```php
- Kopierer alle projektfiler til snapshot mappe
- Gemmer original → snapshot path mapping
- Kan genskabe filer ved restore
- Struktur: /assets/snapshots/{project_id}/{timestamp}/
```

**Restore funktionalitet:**
```php
- Fuld restore: Genskaber alt data og filer
- Transaction-baseret (alt eller intet)
- ID mapping: Håndterer foreign keys korrekt
- Hierarki bevaring: Parent-child relationer intakte
```

#### ❌ Hvad Mangler

1. **Integration med aktiv kodebase**
   - SnapshotManager ligger i OLD mappen
   - Ingen API endpoints i aktiv `/api.php`
   - Frontend component (project-snapshot.js) kalder endpoints der ikke eksisterer

2. **Automatisk snapshot ved report generering**
   - Reports genereres uden at lave snapshot
   - Ingen version tracking mellem snapshots og reports

3. **Delvis restore**
   - Kun fuld restore understøttes
   - Kan ikke vælge specifikke bygninger/elementer

4. **Snapshot sammenligning**
   - Ingen diff funktionalitet
   - Kan ikke sammenligne to snapshots

### 1.2 Manglende Funktionalitet

#### Prioritet 1: Migration til Aktiv Kodebase

**Skal implementeres:**
```php
// I /api.php
case 'create_snapshot':
    require_once __DIR__ . '/core/snapshot-manager.php';
    return SnapshotManager::create($projectId, $title, $desc, $user);

case 'restore_snapshot':
    require_once __DIR__ . '/core/snapshot-manager.php';
    return SnapshotManager::restore($snapshotId, $user['id']);

case 'list_snapshots':
    // Hent alle snapshots for projekt

case 'delete_snapshot':
    // Slet snapshot og tilhørende filer
```

#### Prioritet 2: Automatisk Snapshot ved Report

```php
// I modules/report/api.php - handle_generate()
function handle_generate(array $user): array {
    // ... eksisterende kode ...

    // TILFØJ: Opret snapshot automatisk
    $snapshotTitle = "Automatisk snapshot ved rapport: " . $title;
    $snapshotId = SnapshotManager::create(
        $projectId,
        $snapshotTitle,
        "Gemt automatisk ved generering af rapport",
        $user['id']
    );

    // Link snapshot til report
    $reportRecord['snapshot_id'] = $snapshotId;

    // ... gem rapport ...
}
```

#### Prioritet 3: Delvis Restore

```php
class SnapshotManager {
    /**
     * Restore kun specifikke dele
     */
    public function restorePartial(
        $snapshotId,
        $targetProjectId,
        $options = [
            'restore_buildings' => [],  // Array of building IDs
            'restore_elements' => [],   // Array of element IDs
            'restore_files' => true,
            'restore_budgets' => true
        ]
    ) {
        // Implementer selektiv restore
    }
}
```

### 1.3 Database Schema Requirements

**Nuværende `project_snapshots` tabel:**
```sql
CREATE TABLE project_snapshots (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id),
    created_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    title VARCHAR(255),
    description TEXT,
    total_price DECIMAL(12,2),
    stats_summary JSONB,
    db_dump JSONB,           -- Hele data strukturen
    files_path VARCHAR(500)  -- Sti til snapshot filer
);
```

**Tilføj til `reports` tabel:**
```sql
ALTER TABLE reports
ADD COLUMN snapshot_id INTEGER REFERENCES project_snapshots(id);

-- Index for hurtig søgning
CREATE INDEX idx_reports_snapshot ON reports(snapshot_id);
```

---

## 2. Report Funktionalitet

### 2.1 Nuværende Status

#### ✅ Hvad Eksisterer

**Data Collection (i `/modules/report/api.php`):**
```php
✓ Project summary (bygninger, elementer, CAPEX, flags)
✓ Building details med element hierarki
✓ Red flags summary og top 20
✓ CAPEX data per bygning og element
✓ OPEX data (hvis tilgængelig)
✓ Urgency og condition scores
✓ Report metadata (titel, type, dato, bruger)
```

**Report Templates:**
```php
✓ Due Diligence Rapport (komplet)
✓ Executive Summary (kort)
✓ Budget Oversigt (økonomi fokus)
✓ Tilstandsvurdering (condition fokus)
```

**Storage:**
```php
✓ Reports gemmes i database med JSON data
✓ Metadata tracking (hvem, hvornår, hvilke indstillinger)
✓ Linking til project
```

#### ❌ Hvad Mangler HELT

**PDF Generering:**
```php
// Nuværende implementation er kun placeholder
function generate_report_pdf(...) {
    // This is a placeholder
    return "/reports/pdf/{$reportId}.pdf";
}
```

**Manglende Features:**
1. ❌ Table of Contents (indholdsfortegnelse)
2. ❌ Sidetal (page numbering)
3. ❌ Figurnumre (figure numbering)
4. ❌ Formelt layout (headers, footers, logo)
5. ❌ Opsummerende tabeller (summary tables)
6. ❌ Tabel-format output (data tables)
7. ❌ Billeder med figurtekst
8. ❌ Sektionsnavigation
9. ❌ Appendix håndtering

### 2.2 Anbefalinger: PDF Generering

#### Option 1: mPDF (Anbefalet) ✅

**Fordele:**
- God HTML/CSS support
- UTF-8/Unicode support (vigtig for dansk)
- TOC generation built-in
- Header/footer templates
- Custom page numbering
- Bookmark support

**Installation:**
```bash
composer require mpdf/mpdf
```

**Eksempel Implementation:**
```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

class ReportPDFGenerator {
    private $mpdf;
    private $reportData;
    private $figureCounter = 0;
    private $tableCounter = 0;

    public function __construct(array $reportData) {
        $this->reportData = $reportData;

        // Initialize mPDF
        $this->mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);

        // Set document properties
        $this->mpdf->SetTitle($reportData['title']);
        $this->mpdf->SetAuthor($reportData['generated_by']);
        $this->mpdf->SetCreator('DueDiligence v2.0');
    }

    public function generate(): string {
        // 1. Cover Page
        $this->addCoverPage();

        // 2. Table of Contents
        $this->mpdf->AddPage();
        $this->mpdf->TOCpagebreak(
            'P', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            'I', 'I',
            'TOC-Heading', 0, '', '', '', ''
        );

        // 3. Executive Summary
        $this->addExecutiveSummary();

        // 4. Detailed Sections
        foreach ($this->reportData['buildings'] as $building) {
            $this->addBuildingSection($building);
        }

        // 5. Summary Tables
        $this->addSummaryTables();

        // 6. Red Flags Section
        $this->addRedFlagsSection();

        // 7. Appendices
        $this->addAppendices();

        // Save to file
        $filename = 'report_' . $this->reportData['report_id'] . '_' . date('Ymd_His') . '.pdf';
        $filepath = __DIR__ . '/../../assets/reports/' . $filename;

        $this->mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

        return '/assets/reports/' . $filename;
    }

    private function addCoverPage() {
        $html = <<<HTML
        <div style="text-align: center; margin-top: 100px;">
            <h1 style="font-size: 32pt; color: #003366;">
                {$this->reportData['title']}
            </h1>

            <h2 style="font-size: 18pt; color: #666; margin-top: 20px;">
                {$this->reportData['project']['project_name']}
            </h2>

            <div style="margin-top: 50px; font-size: 12pt;">
                <p><strong>Dato:</strong> {$this->reportData['generated_at']}</p>
                <p><strong>Udarbejdet af:</strong> {$this->reportData['generated_by']}</p>
            </div>

            <div style="position: absolute; bottom: 50px; width: 100%; text-align: center;">
                <img src="/assets/images/logo.png" style="width: 150px;">
            </div>
        </div>
        HTML;

        $this->mpdf->WriteHTML($html);
    }

    private function addExecutiveSummary() {
        $this->mpdf->AddPage();
        $this->mpdf->Bookmark('Executive Summary', 0);

        $project = $this->reportData['project'];

        $html = <<<HTML
        <h1>Executive Summary</h1>

        <h2>Nøgletal</h2>
        <table class="summary-table">
            <tr>
                <td><strong>Antal bygninger:</strong></td>
                <td>{$project['building_count']}</td>
            </tr>
            <tr>
                <td><strong>Antal elementer:</strong></td>
                <td>{$project['element_count']}</td>
            </tr>
            <tr>
                <td><strong>Total CAPEX:</strong></td>
                <td>DKK " . number_format($project['total_capex'], 0, ',', '.') . "</td>
            </tr>
            <tr>
                <td><strong>Kritiske elementer:</strong></td>
                <td style='color: #cc0000;'>{$project['critical_count']}</td>
            </tr>
        </table>
        HTML;

        $this->mpdf->WriteHTML($html);
    }

    private function addBuildingSection(array $building) {
        $this->mpdf->AddPage();
        $this->mpdf->Bookmark($building['building_name'], 1);

        $html = '<h1>' . htmlspecialchars($building['building_name']) . '</h1>';

        // Building info table
        $html .= $this->generateTable('Bygningsinformation', [
            ['Parameter', 'Værdi'],
            ['Bygningsnummer', $building['building_number']],
            ['Type', $building['building_type']],
            ['Areal', number_format($building['gross_area'], 0, ',', '.') . ' m²'],
            ['CAPEX', 'DKK ' . number_format($building['total_capex'], 0, ',', '.')]
        ]);

        // Elements
        foreach ($building['elements'] as $element) {
            $html .= $this->addElementSection($element, 2);
        }

        $this->mpdf->WriteHTML($html);
    }

    private function addElementSection(array $element, int $level) {
        $bookmark = str_repeat('&nbsp;&nbsp;', $level - 1) . $element['element_name'];
        $this->mpdf->Bookmark($bookmark, $level);

        $html = '<h' . min($level + 1, 6) . '>' . htmlspecialchars($element['element_name']) . '</h' . min($level + 1, 6) . '>';

        // Element details table
        $rows = [
            ['Parameter', 'Værdi']
        ];

        if (!empty($element['element_code'])) {
            $rows[] = ['Element kode', $element['element_code']];
        }

        if (!empty($element['capex'])) {
            $rows[] = ['CAPEX', 'DKK ' . number_format($element['capex'], 0, ',', '.')];
        }

        if (!empty($element['urgency'])) {
            $rows[] = ['Hastighed', $this->getUrgencyBadge($element['urgency'])];
        }

        if (!empty($element['condition_score'])) {
            $rows[] = ['Tilstand', $this->getConditionBadge($element['condition_score'])];
        }

        $html .= $this->generateTable('Elementdetaljer', $rows);

        // Observation og anbefaling
        if (!empty($element['observation'])) {
            $html .= '<p><strong>Observation:</strong><br>' . nl2br(htmlspecialchars($element['observation'])) . '</p>';
        }

        if (!empty($element['recommendation'])) {
            $html .= '<p><strong>Anbefaling:</strong><br>' . nl2br(htmlspecialchars($element['recommendation'])) . '</p>';
        }

        // Billeder med figurnumre
        if (!empty($element['images'])) {
            foreach ($element['images'] as $image) {
                $html .= $this->addFigure(
                    $image['file_path'],
                    $image['caption'] ?? $element['element_name']
                );
            }
        }

        // Recursive children
        if (!empty($element['children'])) {
            foreach ($element['children'] as $child) {
                $html .= $this->addElementSection($child, $level + 1);
            }
        }

        return $html;
    }

    private function addFigure(string $imagePath, string $caption): string {
        $this->figureCounter++;

        return <<<HTML
        <div class="figure">
            <img src="{$imagePath}" style="max-width: 100%; height: auto;">
            <p class="figure-caption">
                <strong>Figur {$this->figureCounter}:</strong> {$caption}
            </p>
        </div>
        HTML;
    }

    private function generateTable(string $title, array $rows): string {
        $this->tableCounter++;

        $html = '<h4>Tabel ' . $this->tableCounter . ': ' . $title . '</h4>';
        $html .= '<table class="data-table">';

        foreach ($rows as $i => $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $tag = $i === 0 ? 'th' : 'td';
                $html .= "<{$tag}>" . htmlspecialchars($cell) . "</{$tag}>";
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private function addSummaryTables() {
        $this->mpdf->AddPage();
        $this->mpdf->Bookmark('Opsummerende Tabeller', 0);

        $html = '<h1>Opsummerende Tabeller</h1>';

        // CAPEX oversigt
        $rows = [['Bygning', 'CAPEX (DKK)']];
        foreach ($this->reportData['buildings'] as $b) {
            $rows[] = [$b['building_name'], number_format($b['total_capex'], 0, ',', '.')];
        }
        $html .= $this->generateTable('CAPEX Oversigt', $rows);

        // Red flags oversigt
        $summary = $this->reportData['red_flags_summary'];
        $flagRows = [
            ['Flag Type', 'Antal', 'CAPEX (DKK)'],
            ['Kritisk hastighed', $summary['critical_urgency_count'],
             number_format($summary['critical_urgency_capex'], 0, ',', '.')],
            ['Dårlig tilstand', $summary['poor_condition_count'],
             number_format($summary['poor_condition_capex'], 0, ',', '.')],
            ['Høje omkostninger', $summary['high_cost_count'],
             number_format($summary['high_cost_capex'], 0, ',', '.')]
        ];
        $html .= $this->generateTable('Red Flags Oversigt', $flagRows);

        $this->mpdf->WriteHTML($html);
    }

    private function addRedFlagsSection() {
        $this->mpdf->AddPage();
        $this->mpdf->Bookmark('Røde Flag', 0);

        $html = '<h1>Top 20 Røde Flag</h1>';

        $rows = [['Element', 'Bygning', 'Score', 'CAPEX (DKK)']];
        foreach ($this->reportData['top_red_flags'] as $flag) {
            $rows[] = [
                $flag['element_name'],
                $flag['building_name'],
                number_format($flag['red_flag_score'], 1),
                number_format($flag['capex'], 0, ',', '.')
            ];
        }

        $html .= $this->generateTable('Top Røde Flag', $rows);

        $this->mpdf->WriteHTML($html);
    }

    private function addAppendices() {
        $this->mpdf->AddPage();
        $this->mpdf->Bookmark('Appendix', 0);

        $html = <<<HTML
        <h1>Appendix A: Definitioner</h1>

        <h2>Tilstandsscorer</h2>
        <ul>
            <li><strong>5 - Fremragende:</strong> Som ny</li>
            <li><strong>4 - God:</strong> Normal vedligeholdelse</li>
            <li><strong>3 - Rimelig:</strong> Mindre mangler</li>
            <li><strong>2 - Dårlig:</strong> Betydelige mangler</li>
            <li><strong>1 - Meget dårlig:</strong> Kritisk</li>
        </ul>

        <h2>Hastigheds kategorier</h2>
        <ul>
            <li><strong>Akut:</strong> Øjeblikkeligt</li>
            <li><strong>0-1 år:</strong> Indenfor 1 år</li>
            <li><strong>1-2 år:</strong> Indenfor 2 år</li>
            <li><strong>3-5 år:</strong> Mellemfristet</li>
            <li><strong>5-10 år:</strong> Langfristet</li>
        </ul>
        HTML;

        $this->mpdf->WriteHTML($html);
    }

    private function getUrgencyBadge(string $urgency): string {
        $colors = [
            'Akut' => '#cc0000',
            '0-1 år' => '#ff6600',
            '1-2 år' => '#ff9900',
            '3-5 år' => '#ffcc00',
            '5-10 år' => '#009900'
        ];

        $color = $colors[$urgency] ?? '#666666';
        return '<span style="color: ' . $color . '; font-weight: bold;">' . $urgency . '</span>';
    }

    private function getConditionBadge(int $score): string {
        $labels = [
            5 => 'Fremragende',
            4 => 'God',
            3 => 'Rimelig',
            2 => 'Dårlig',
            1 => 'Meget dårlig'
        ];

        $colors = [5 => '#009900', 4 => '#66cc00', 3 => '#ffcc00', 2 => '#ff6600', 1 => '#cc0000'];

        $label = $labels[$score] ?? 'Ukendt';
        $color = $colors[$score] ?? '#666666';

        return '<span style="color: ' . $color . '; font-weight: bold;">' . $label . ' (' . $score . ')</span>';
    }
}
```

**CSS Stylesheet for PDF:**
```php
// Add to mPDF
$stylesheet = <<<CSS
<style>
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        line-height: 1.5;
    }

    h1 {
        font-size: 18pt;
        color: #003366;
        border-bottom: 2px solid #003366;
        padding-bottom: 5px;
        margin-top: 20px;
    }

    h2 {
        font-size: 14pt;
        color: #003366;
        margin-top: 15px;
    }

    h3 {
        font-size: 12pt;
        color: #003366;
        margin-top: 10px;
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }

    .summary-table td {
        padding: 8px;
        border-bottom: 1px solid #cccccc;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        font-size: 9pt;
    }

    .data-table th {
        background-color: #003366;
        color: white;
        padding: 8px;
        text-align: left;
        font-weight: bold;
    }

    .data-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #dddddd;
    }

    .data-table tr:nth-child(even) {
        background-color: #f5f5f5;
    }

    .figure {
        text-align: center;
        margin: 20px 0;
        page-break-inside: avoid;
    }

    .figure-caption {
        font-size: 9pt;
        font-style: italic;
        color: #666;
        margin-top: 5px;
    }
</style>
CSS;

$this->mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
```

**Header og Footer Template:**
```php
// Set header
$this->mpdf->SetHTMLHeader('
    <div style="text-align: right; font-size: 8pt; color: #666;">
        <img src="/assets/images/logo-small.png" style="height: 20px; float: left;">
        ' . $this->reportData['title'] . '
    </div>
');

// Set footer med sidetal
$this->mpdf->SetHTMLFooter('
    <div style="text-align: center; font-size: 8pt; color: #666; border-top: 1px solid #ccc; padding-top: 5px;">
        Side {PAGENO} af {nbpg} | ' . date('d-m-Y') . '
    </div>
');
```

---

## 3. Implementation Roadmap

### Phase 1: Snapshot Migration (1-2 dage) 🔴 KRITISK

1. **Flyt SnapshotManager til aktiv kodebase**
   ```
   OLD/modules/Project/SnapshotManager.php → core/snapshot-manager.php
   ```

2. **Tilføj API endpoints**
   ```
   /api.php: create_snapshot, restore_snapshot, list_snapshots, delete_snapshot
   ```

3. **Test grundigt**
   - Create snapshot
   - List snapshots
   - Restore snapshot (fuld)
   - Delete snapshot

### Phase 2: Report-Snapshot Integration (1 dag)

1. **Link snapshots til reports**
   - ALTER TABLE reports ADD COLUMN snapshot_id

2. **Automatisk snapshot ved report generation**
   - Modificer handle_generate() i modules/report/api.php

3. **UI opdateringer**
   - Vis snapshot info i report liste
   - Link til snapshot fra report

### Phase 3: PDF Generation (3-4 dage) 🔴 KRITISK

1. **Install mPDF**
   ```bash
   composer require mpdf/mpdf
   ```

2. **Implementer ReportPDFGenerator klasse**
   - core/report-pdf-generator.php

3. **Opdater generate_report_pdf() function**
   - Brug ny ReportPDFGenerator

4. **Test alle report types**
   - Due Diligence
   - Executive Summary
   - Budget Overview
   - Condition Assessment

### Phase 4: Advanced Features (2-3 dage)

1. **Delvis snapshot restore**
2. **Snapshot comparison**
3. **Custom report templates**
4. **Batch report generation**

---

## 4. Testing Checklist

### Snapshots
- [ ] Opret snapshot med alle data typer
- [ ] Verificer filer kopieres korrekt
- [ ] Test fuld restore
- [ ] Verificer ID mapping fungerer
- [ ] Test sletning af snapshot
- [ ] Test permissions

### Reports
- [ ] Generer alle report typer
- [ ] Verificer data completeness
- [ ] Test PDF output for alle typer
- [ ] Verificer TOC generation
- [ ] Test figure numbering
- [ ] Test table formatting
- [ ] Verificer sidetal er korrekte
- [ ] Test billeder i PDF
- [ ] Verificer danske tegn (æ, ø, å)

### Integration
- [ ] Snapshot oprettes automatisk ved report
- [ ] Link mellem snapshot og report fungerer
- [ ] Kan genskabe projekt fra snapshot ved report dato

---

## 5. Estimeret Arbejdstid

| Fase | Opgave | Estimat |
|------|--------|---------|
| 1 | Snapshot migration | 2 dage |
| 2 | Report-snapshot integration | 1 dag |
| 3 | PDF generation (mPDF) | 4 dage |
| 4 | Advanced features | 3 dage |
| **Total** | | **10 dage** |

---

## 6. Konklusion

**Snapshots**: God grundlæggende funktionalitet eksisterer, men skal flyttes fra OLD til aktiv kode.

**Reports**: Data collection er komplet, men PDF generering mangler helt og er kritisk funktionalitet.

**Næste skridt**:
1. Migrer SnapshotManager (prioritet 1)
2. Implementer PDF generation med mPDF (prioritet 1)
3. Tilføj report-snapshot integration (prioritet 2)
4. Advanced features kan implementeres senere (prioritet 3)
