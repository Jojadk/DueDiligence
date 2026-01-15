<?php
/**
 * Seed Default Report Templates
 * Run once to populate report_templates with TDD Standard and Excel templates
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');

require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "Seeding Report Templates...\n\n";

// First, ensure all required columns exist
echo "Checking report_templates schema...\n";

$columnsToAdd = [
    'description' => 'TEXT',
    'css' => 'TEXT'
];

foreach ($columnsToAdd as $col => $type) {
    try {
        $db->query("SELECT column_name FROM information_schema.columns 
                   WHERE table_name = 'report_templates' AND column_name = :col");
        $db->bind(':col', $col);
        $exists = $db->single();

        if (!$exists) {
            echo "Adding $col column... ";
            $db->query("ALTER TABLE report_templates ADD COLUMN $col $type");
            $db->execute();
            echo "✅\n";
        } else {
            echo "✅ $col exists\n";
        }
    } catch (Exception $e) {
        echo "⚠️ $col: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// ==============================================
// Template 1: TDD Standard Report (Portrait)
// ==============================================
$standardHtml = <<<'HTML'
<!-- Cover Page -->
<div class="page cover-page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <div class="cover-content">
        <h1 class="cover-title">{{ project.name }}</h1>
        <div class="cover-subtitle">Tilstandsrapport</div>
        
        {if project.cover_image}
        <img src="/assets/uploads/{{ project.cover_image }}" class="cover-image">
        {/if}
        
        <div class="cover-meta">
            <p><strong>Kunde:</strong> {{ client.name }}</p>
            <p><strong>Adresse:</strong> {{ project.address }}</p>
            <p><strong>Dato:</strong> {{ today | date }}</p>
        </div>
    </div>
</div>

<!-- Table of Contents -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2>Indholdsfortegnelse</h2>
    <ul class="toc">
        <li><span class="toc-num">1.</span> Indledning</li>
        {foreach tree as category}
        <li><span class="toc-num">{{ category.index }}.</span> {{ category.name }}</li>
        {/foreach}
    </ul>
</div>

<!-- Introduction -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <div class="section">
        <h2 class="section-header">1. Indledning</h2>
        <div class="section-content">{{ project.report_intro | nl2br }}</div>
        
        <h3 class="subsection-header">1.1 Definitioner</h3>
        <div class="section-content">{{ project.report_disclaimer | nl2br }}</div>
    </div>
</div>

<!-- Project Overview -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2>Projektoversigt</h2>
    <table class="info-table">
        <tr>
            <th>BBR Nummer</th>
            <td>{{ project.bbr_number }}</td>
        </tr>
        <tr>
            <th>Byggeår</th>
            <td>{{ project.construction_year }}</td>
        </tr>
        <tr>
            <th>Renoveringsår</th>
            <td>{{ project.renovation_year }}</td>
        </tr>
        <tr>
            <th>Areal (m²)</th>
            <td>{{ project.area_m2 }}</td>
        </tr>
        <tr>
            <th>Opvarmning</th>
            <td>{{ project.heating_type }}</td>
        </tr>
    </table>
</div>

<!-- Budget Overview (Summary Table) -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2>Budgetoversigt</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Bygningsdel</th>
                <th class="num">&lt; 1 år</th>
                <th class="num">1-2 år</th>
                <th class="num">3-5 år</th>
                <th class="num">6-10 år</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            {foreach tree as category}
            <tr>
                <td><strong>{{ category.name }}</strong></td>
                <td class="num">{{ category.totals.0_1 | money }}</td>
                <td class="num">{{ category.totals.1_2 | money }}</td>
                <td class="num">{{ category.totals.3_5 | money }}</td>
                <td class="num">{{ category.totals.6_10 | money }}</td>
                <td class="num"><strong>{{ category.totals.total | money }}</strong></td>
            </tr>
            {/foreach}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>Total</strong></td>
                <td class="num">{{ totals.0_1 | money }}</td>
                <td class="num">{{ totals.1_2 | money }}</td>
                <td class="num">{{ totals.3_5 | money }}</td>
                <td class="num">{{ totals.6_10 | money }}</td>
                <td class="num"><strong>{{ totals.grand | money }}</strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Detailed Sections -->
{foreach tree as category}
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2 class="section-header">{{ category.index }}. {{ category.name }}</h2>
    
    {if category.description}
    <p class="category-desc">{{ category.description | nl2br }}</p>
    {/if}
    
    {foreach category.children as element}
    <div class="element-card">
        <h3 class="element-title">
            {{ category.index }}.{{ element.subindex }}. {{ element.name }}
        </h3>
        
        <div class="element-grid">
            <div class="element-left">
                <h4>Observation</h4>
                <p>{{ element.description | nl2br }}</p>
                
                <h4>Anbefaling</h4>
                <p>{{ element.recommendation | nl2br }}</p>
            </div>
            
            <div class="element-right">
                {if element.image}
                <div class="element-image">
                    <img src="/assets/uploads/{{ element.image.file_path }}">
                    <div class="image-caption">Fig. {{ category.index }}.{{ element.subindex }}</div>
                </div>
                {/if}
                
                <div class="element-meta">
                    <div class="risk-badge risk-{{ element.risk_level }}">
                        Risiko: {{ element.risk_level }}
                    </div>
                    <div class="capex">
                        CAPEX: {{ element.capex | money }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    {/foreach}
</div>
{/foreach}
HTML;

$standardCss = <<<'CSS'
/* ==================================
   TDD STANDARD REPORT - CSS
   Portrait A4 Layout
   ================================== */

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
    --primary-color: #2c3e50;
    --secondary-color: #34495e;
    --accent-color: #3498db;
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --light-bg: #f8f9fa;
    --border-color: #dee2e6;
    --sweco-yellow: #FFC20E;
}

* {
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    font-size: 11pt;
    line-height: 1.5;
    color: #333;
    margin: 0;
    padding: 20px;
    background: #525659;
}

/* Page Container */
.page {
    width: 210mm;
    min-height: 297mm;
    padding: 20mm;
    margin: 0 auto 20px auto;
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.3);
    position: relative;
    page-break-after: always;
}

.page:last-child {
    page-break-after: auto;
}

/* Logo */
.logo {
    position: absolute;
    top: 10mm;
    right: 15mm;
    font-size: 16px;
    font-weight: bold;
    color: #333;
}

.logo .star {
    color: var(--sweco-yellow);
}

/* Cover Page */
.cover-page {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
}

.cover-title {
    font-size: 36pt;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 20px;
}

.cover-subtitle {
    font-size: 18pt;
    color: #7f8c8d;
    margin-bottom: 40px;
}

.cover-image {
    max-width: 80%;
    max-height: 300px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    margin-bottom: 40px;
}

.cover-meta {
    font-size: 12pt;
    color: #333;
}

.cover-meta p {
    margin: 8px 0;
}

/* Table of Contents */
.toc {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.toc li {
    padding: 8px 0;
    border-bottom: 1px dotted var(--border-color);
    font-size: 11pt;
}

.toc-num {
    display: inline-block;
    width: 40px;
    font-weight: 600;
}

/* Section Headers */
.section-header {
    background: var(--light-bg);
    padding: 10px 15px;
    border-left: 4px solid var(--accent-color);
    margin-bottom: 15px;
}

.subsection-header {
    color: var(--secondary-color);
    font-size: 14pt;
    margin: 20px 0 10px 0;
}

.section-content {
    margin-bottom: 20px;
    line-height: 1.7;
}

/* Tables */
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.info-table th,
.info-table td {
    padding: 12px;
    border: 1px solid var(--border-color);
    text-align: left;
}

.info-table th {
    background: var(--light-bg);
    font-weight: 600;
    width: 40%;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10pt;
}

.data-table th,
.data-table td {
    padding: 8px 10px;
    border: 1px solid var(--border-color);
}

.data-table th {
    background: var(--light-bg);
    font-weight: 600;
}

.data-table .num {
    text-align: right;
}

.data-table .total-row {
    background: #eee;
    font-weight: bold;
}

/* Element Cards */
.element-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.element-title {
    background: var(--light-bg);
    padding: 12px 15px;
    margin: 0;
    font-size: 12pt;
    border-bottom: 1px solid var(--border-color);
}

.element-grid {
    display: flex;
    padding: 15px;
    gap: 20px;
}

.element-left {
    flex: 1;
}

.element-left h4 {
    color: var(--secondary-color);
    margin: 0 0 8px 0;
    font-size: 10pt;
}

.element-right {
    width: 200px;
}

.element-image img {
    width: 100%;
    border-radius: 4px;
}

.image-caption {
    font-size: 9pt;
    color: #666;
    text-align: center;
    margin-top: 5px;
}

.element-meta {
    margin-top: 10px;
}

/* Risk Badges */
.risk-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 9pt;
    font-weight: 600;
}

.risk-Rød { background: #ffcccc; color: #8b0000; }
.risk-Gul { background: #fff3cd; color: #856404; }
.risk-Grøn { background: #d4edda; color: #155724; }
.risk-Blå { background: #cce5ff; color: #004085; }

.capex {
    margin-top: 8px;
    font-weight: 600;
    color: var(--primary-color);
}

/* Print Styles */
@media print {
    body {
        background: white;
        padding: 0;
    }
    
    .page {
        width: 100%;
        height: auto;
        margin: 0;
        box-shadow: none;
        page-break-after: always;
    }
    
    .element-card {
        page-break-inside: avoid;
    }
}
CSS;

// ==============================================
// Template 2: Excel/Landscape Report
// ==============================================
$excelHtml = <<<'HTML'
<!-- Cover Page -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <div class="date">{{ today | date }}</div>
    <div class="cover-content">
        <h1>{{ project.name }}</h1>
        <h2>Tilstandsrapport</h2>
        
        {if project.cover_image}
        <img src="/assets/uploads/{{ project.cover_image }}" class="cover-image">
        {/if}
        
        <div class="cover-info">
            <p><strong>Kunde:</strong> {{ client.name }}</p>
            <p><strong>Adresse:</strong> {{ project.address }}</p>
        </div>
    </div>
</div>

<!-- Table of Contents -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2>Indholdsfortegnelse</h2>
    <ul class="toc">
        <li>1. Indledning</li>
        {foreach tree as category}
        <li>{{ category.index }}. {{ category.name }}</li>
        {/foreach}
    </ul>
</div>

<!-- Introduction -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <div class="intro-section">
        <h2 class="section-title">1. Introduction</h2>
        <div class="content">{{ project.report_intro | nl2br }}</div>
        
        <h3 class="section-title">1.1 Definitions</h3>
        <div class="content">{{ project.report_disclaimer | nl2br }}</div>
    </div>
</div>

<!-- Budget Overview -->
<div class="page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2>Oversigt</h2>
    <table class="overview-table">
        <thead>
            <tr class="header-row">
                <th>Bygningsdel</th>
                <th>&lt; 1 år</th>
                <th>1-2 år</th>
                <th>3-5 år</th>
                <th>6-10 år</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            {foreach tree as category}
            <tr>
                <td><strong>{{ category.name }}</strong></td>
                <td class="num">{{ category.totals.0_1 | money }}</td>
                <td class="num">{{ category.totals.1_2 | money }}</td>
                <td class="num">{{ category.totals.3_5 | money }}</td>
                <td class="num">{{ category.totals.6_10 | money }}</td>
                <td class="num"><strong>{{ category.totals.total | money }}</strong></td>
            </tr>
            {/foreach}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td>Total sum, incl. All works</td>
                <td class="num">{{ totals.0_1 | money }}</td>
                <td class="num">{{ totals.1_2 | money }}</td>
                <td class="num">{{ totals.3_5 | money }}</td>
                <td class="num">{{ totals.6_10 | money }}</td>
                <td class="num">{{ totals.grand | money }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Detailed Sections (Excel-style) -->
{foreach tree as category}
<div class="page detail-page">
    <div class="logo">SWECO<span class="star">＊</span></div>
    <h2 class="section-title bordered">{{ category.index }}. {{ category.name }}</h2>
    
    <table class="detail-table">
        <thead>
            <tr class="header-row">
                <th rowspan="2" style="width:10%">Bygningsdel</th>
                <th rowspan="2" style="width:18%">Observation</th>
                <th rowspan="2" style="width:12%">Foto</th>
                <th rowspan="2" style="width:18%">Anbefaling</th>
                <th rowspan="2" style="width:6%">Omfang</th>
                <th colspan="5" class="centered">Risiko</th>
                <th colspan="4" class="centered">Forventet udførelse</th>
            </tr>
            <tr class="header-row sub-header">
                <th class="risk-col red">🔴</th>
                <th class="risk-col yellow">🟡</th>
                <th class="risk-col black">⚫️</th>
                <th class="risk-col green">🟢</th>
                <th class="risk-col blue">🔵</th>
                <th class="time-col">&lt; 1 år</th>
                <th class="time-col">1-2 år</th>
                <th class="time-col">3-5 år</th>
                <th class="time-col">6-10 år</th>
            </tr>
        </thead>
        <tbody>
            {foreach category.children as element}
            <tr class="data-row">
                <td class="name-cell">
                    {{ category.index }}.{{ element.subindex }} {{ element.name }}
                </td>
                <td>{{ element.description | nl2br }}</td>
                <td class="image-cell">
                    {if element.image}
                    <img src="/assets/uploads/{{ element.image.file_path }}">
                    <div class="fig-caption">Fig. {{ category.index }}.{{ element.subindex }}</div>
                    {/if}
                </td>
                <td>{{ element.recommendation | nl2br }}</td>
                <td class="scope-cell">{{ element.scope }}</td>
                
                <!-- Risk Checkboxes -->
                <td class="check-cell">{if element.risk_level == 'Rød'}☒{/if}</td>
                <td class="check-cell">{if element.risk_level == 'Gul'}☒{/if}</td>
                <td class="check-cell">{if element.risk_level == 'Sort'}☒{/if}</td>
                <td class="check-cell">{if element.risk_level == 'Grøn'}☒{/if}</td>
                <td class="check-cell">{if element.risk_level == 'Blå'}☒{/if}</td>
                
                <!-- Time Columns -->
                <td class="num">{{ element.budget.0_1 | money }}</td>
                <td class="num">{{ element.budget.1_2 | money }}</td>
                <td class="num">{{ element.budget.3_5 | money }}</td>
                <td class="num">{{ element.budget.6_10 | money }}</td>
            </tr>
            {/foreach}
        </tbody>
    </table>
</div>
{/foreach}
HTML;

$excelCss = <<<'CSS'
/* ==================================
   EXCEL/LANDSCAPE REPORT - CSS
   Landscape A4 Layout
   ================================== */

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
    --primary: #2c3e50;
    --secondary: #34495e;
    --border: #000;
    --light-bg: #f8f9fa;
    --header-bg: #ddd;
    --sweco-yellow: #FFC20E;
}

* {
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    padding: 20px;
    background: #525659;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Page - Landscape */
.page {
    width: 297mm;
    height: 210mm;
    padding: 15mm;
    margin: 0 auto 10mm auto;
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.3);
    position: relative;
    overflow: hidden;
}

/* Logo */
.logo {
    position: absolute;
    top: 10mm;
    right: 15mm;
    font-size: 16px;
    font-weight: bold;
}

.logo .star {
    color: var(--sweco-yellow);
}

.date {
    position: absolute;
    top: 10mm;
    left: 15mm;
    font-size: 12px;
    color: #666;
}

/* Cover */
.cover-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
    text-align: center;
}

.cover-content h1 {
    font-size: 36pt;
    color: var(--primary);
    margin-bottom: 10px;
}

.cover-content h2 {
    font-size: 18pt;
    color: #7f8c8d;
    font-weight: normal;
    margin-bottom: 30px;
}

.cover-image {
    max-width: 80%;
    max-height: 400px;
    margin-bottom: 30px;
    border-radius: 4px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.cover-info {
    font-size: 12pt;
}

/* TOC */
.toc {
    list-style: none;
    padding: 0;
    font-size: 11pt;
    line-height: 1.5;
}

.toc li {
    margin-bottom: 5px;
}

/* Section Titles */
.section-title {
    font-size: 18pt;
    color: var(--secondary);
    border-bottom: 2px solid #eee;
    padding-bottom: 5px;
    margin-bottom: 15px;
}

.section-title.bordered {
    background: var(--header-bg);
    padding: 5px 10px;
    border: 1px solid var(--border);
    border-bottom: none;
    margin-bottom: 0;
}

/* Overview Table */
.overview-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.overview-table th,
.overview-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    font-size: 10pt;
}

.overview-table .header-row {
    background: var(--header-bg);
}

.overview-table .total-row {
    background: #eee;
    font-weight: bold;
}

.overview-table .num {
    text-align: right;
}

/* Detail Table (Excel-style) */
.detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
    border: 1px solid var(--border);
}

.detail-table th,
.detail-table td {
    border: 1px solid var(--border);
    padding: 4px;
    vertical-align: top;
}

.detail-table .header-row {
    background: #f0f0f0;
}

.detail-table .sub-header th {
    font-size: 7pt;
    text-align: center;
}

/* Risk Columns */
.risk-col {
    min-width: 15px;
    text-align: center;
}

.risk-col.red { background: #ffcccc; }
.risk-col.yellow { background: #fff5cc; }
.risk-col.black { background: #ccc; }
.risk-col.green { background: #ccffcc; }
.risk-col.blue { background: #cce5ff; }

/* Time Columns */
.time-col {
    width: 6%;
    text-align: center;
}

/* Data Cells */
.name-cell {
    font-weight: bold;
    padding-left: 6px;
}

.image-cell {
    text-align: center;
}

.image-cell img {
    max-width: 80px;
    max-height: 60px;
    display: block;
    margin: 0 auto;
}

.fig-caption {
    font-size: 0.7em;
    color: #555;
    margin-top: 2px;
}

.scope-cell {
    font-size: 0.8em;
    text-align: left;
}

.check-cell {
    text-align: center;
    font-size: 10pt;
}

.num {
    text-align: right;
}

.data-row {
    page-break-inside: avoid;
}

.centered {
    text-align: center;
}

/* Print */
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
CSS;

// Insert Templates
echo "Inserting Standard Report Template...\n";
try {
    // Try with description
    $db->query("INSERT INTO report_templates (name, description, content, css, is_active) 
                VALUES (:name, :desc, :content, :css, TRUE)");
    $db->bind(':name', 'TDD Standard Report');
    $db->bind(':desc', 'Standard tilstandsrapport i A4 portrait format med coverbillede, indholdsfortegnelse og detaljerede sektioner.');
    $db->bind(':content', $standardHtml);
    $db->bind(':css', $standardCss);
    $db->execute();
    echo "✅ Standard Report Template saved\n";
} catch (Exception $e) {
    // Try without description column
    try {
        $db->query("INSERT INTO report_templates (name, content, css, is_active) VALUES (:name, :content, :css, TRUE)");
        $db->bind(':name', 'TDD Standard Report');
        $db->bind(':content', $standardHtml);
        $db->bind(':css', $standardCss);
        $db->execute();
        echo "✅ Standard Report Template saved (without description)\n";
    } catch (Exception $e2) {
        echo "⚠️ Standard template error: " . $e2->getMessage() . "\n";
    }
}

echo "\nInserting Excel/Landscape Report Template...\n";
try {
    // Try with description
    $db->query("INSERT INTO report_templates (name, description, content, css, is_active) 
                VALUES (:name, :desc, :content, :css, TRUE)");
    $db->bind(':name', 'Excel Landscape Report');
    $db->bind(':desc', 'Landskabsorienteret rapport i Excel-style med detaljerede tabeller, risikoindikatorer og budgetkolonner.');
    $db->bind(':content', $excelHtml);
    $db->bind(':css', $excelCss);
    $db->execute();
    echo "✅ Excel/Landscape Report Template saved\n";
} catch (Exception $e) {
    // Try without description
    try {
        $db->query("INSERT INTO report_templates (name, content, css, is_active) VALUES (:name, :content, :css, TRUE)");
        $db->bind(':name', 'Excel Landscape Report');
        $db->bind(':content', $excelHtml);
        $db->bind(':css', $excelCss);
        $db->execute();
        echo "✅ Excel/Landscape Report Template saved (without description)\n";
    } catch (Exception $e2) {
        echo "⚠️ Excel template error: " . $e2->getMessage() . "\n";
    }
}

echo "\n===============================\n";
echo "✅ Report Templates Seeded!\n";
echo "View at: ?module=Report&action=editor\n";
echo "===============================\n";
