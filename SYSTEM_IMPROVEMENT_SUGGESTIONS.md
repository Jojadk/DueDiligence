# System Forbedringsforslag - DueDiligence Platform

**Dato:** 21. januar 2026
**Baseret på:** Analyse af kodebase i `/home/user/DueDiligence`

---

## 📋 Executive Summary

Efter gennemgang af systemets kodebase, her er de mest kritiske forbedringsforslag kategoriseret efter prioritet og emne. Fokus områder inkluderer CAPEX/OPEX håndtering, rapport funktionalitet, performance optimering og brugeroplevelse.

---

## 🎯 Høj Prioritet Forbedringer

### 1. **CAPEX & Budget Forbedringer**

#### 1.1 Manglende CAPEX Validering
**Problem:**
Budget modulet opdaterer element CAPEX i `handle_save_lines()`, men der er ingen validering af om summen matcher element's forventede CAPEX.

**Anbefaling:**
```php
// modules/budget/api.php - Linje 346-356
// Tilføj validering og warning
if ($budgetType === 'capex') {
    $total = 0;
    foreach ($lines as $line) {
        $qty = (float)($line['quantity'] ?? 0);
        $price = (float)($line['price_per_unit'] ?? 0);
        $total += $qty * $price;
    }

    $currentCapex = (float)$element['capex'];
    $difference = abs($total - $currentCapex);
    $threshold = $currentCapex * 0.10; // 10% threshold

    // Warn hvis stor afvigelse
    if ($difference > $threshold && $currentCapex > 0) {
        // Log warning eller returner notice
        log_activity('capex_variance_detected', 'building_element', $elementId, [
            'expected' => $currentCapex,
            'calculated' => $total,
            'variance_pct' => ($difference / $currentCapex) * 100
        ]);
    }

    db_update('building_elements', ['capex' => $total], 'id = :id', ['id' => $elementId]);
}
```

**Impact:** Bedre data integritet og synlighed af budget afvigelser.

---

#### 1.2 CAPEX Budget Line Historik
**Problem:**
Der er ingen historik eller versionering af budget lines. Hvis en bruger ændrer budget linjer, er der ingen måde at se tidligere versioner.

**Anbefaling:**
Opret `budget_lines_history` tabel:
```sql
CREATE TABLE budget_lines_history (
    id BIGSERIAL PRIMARY KEY,
    budget_line_id BIGINT REFERENCES budget_lines(id) ON DELETE CASCADE,
    element_id BIGINT NOT NULL,
    budget_type VARCHAR(20) NOT NULL,

    -- Snapshot af data
    description TEXT,
    quantity DECIMAL(15,2),
    unit VARCHAR(50),
    price_per_unit DECIMAL(15,2),
    year_0_1 DECIMAL(15,2),
    year_1_2 DECIMAL(15,2),
    year_3_5 DECIMAL(15,2),
    year_5_10 DECIMAL(15,2),
    year_10_plus DECIMAL(15,2),

    -- Metadata
    changed_by_user_id INT REFERENCES users(id),
    change_type VARCHAR(20), -- 'created', 'updated', 'deleted'
    changed_at TIMESTAMP DEFAULT NOW(),

    INDEX idx_budget_history_line (budget_line_id),
    INDEX idx_budget_history_element (element_id)
);
```

Tilføj trigger eller service layer til at gemme historik ved ændringer.

**Impact:** Audit trail, bedre compliance og mulighed for at gendanne tidligere versioner.

---

#### 1.3 CAPEX Year Distribution Validation
**Problem:**
Budget linjer har year distributions (year_0_1, year_1_2, etc.), men der er ingen validering af om summen matcher quantity × price_per_unit.

**Anbefaling:**
```php
// Valider at year distributions summen matcher totalen
function validate_year_distributions($line) {
    $total = $line['quantity'] * $line['price_per_unit'];
    $yearSum = $line['year_0_1'] + $line['year_1_2'] +
               $line['year_3_5'] + $line['year_5_10'] +
               $line['year_10_plus'];

    $tolerance = 0.01; // 1 øre tolerance
    if (abs($total - $yearSum) > $tolerance) {
        return [
            'valid' => false,
            'error' => "Year distribution ($yearSum) matcher ikke total ($total)",
            'expected' => $total,
            'actual' => $yearSum
        ];
    }

    return ['valid' => true];
}
```

**Impact:** Data konsistens og bedre cash flow forecasting.

---

### 2. **OPEX & TCO Forbedringer**

#### 2.1 TCO Cache/Memoization
**Problem:**
TCO beregningen i `handle_calculate_tco()` udfører komplekse NPV beregninger hver gang. For store projekter med mange bygninger kan dette være langsomt.

**Anbefaling:**
```sql
-- Cache TCO beregninger
CREATE TABLE building_tco_cache (
    building_id BIGINT PRIMARY KEY REFERENCES buildings(id) ON DELETE CASCADE,
    capex DECIMAL(15,2),
    capex_with_contingency DECIMAL(15,2),
    opex_per_year DECIMAL(15,2),
    opex_npv DECIMAL(15,2),
    tco DECIMAL(15,2),

    -- Configuration snapshot
    lifecycle_years INT,
    discount_rate DECIMAL(5,4),
    inflation_rate DECIMAL(5,4),
    capex_contingency DECIMAL(5,4),
    opex_escalation DECIMAL(5,4),

    calculated_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_tco_cache_building (building_id)
);

-- View der auto-refresher cache hvis data er forældet
CREATE VIEW v_building_tco AS
SELECT
    b.id as building_id,
    CASE
        WHEN tc.calculated_at > GREATEST(
            b.updated_at,
            (SELECT MAX(updated_at) FROM building_elements WHERE building_id = b.id),
            (SELECT MAX(updated_at) FROM building_opex WHERE building_id = b.id)
        ) THEN tc.tco
        ELSE NULL -- Signal to recalculate
    END as tco,
    tc.*
FROM buildings b
LEFT JOIN building_tco_cache tc ON b.id = tc.building_id;
```

**PHP Implementation:**
```php
function handle_calculate_tco(array $user): array {
    // ... validation ...

    // Check cache first
    $cached = db_fetch("
        SELECT * FROM building_tco_cache
        WHERE building_id = :building_id
        AND calculated_at > (
            SELECT MAX(updated_at) FROM (
                SELECT updated_at FROM buildings WHERE id = :building_id
                UNION ALL
                SELECT updated_at FROM building_elements WHERE building_id = :building_id
                UNION ALL
                SELECT updated_at FROM building_opex WHERE building_id = :building_id
            ) t
        )
    ", ['building_id' => $buildingId]);

    if ($cached) {
        return ['success' => true, 'tco' => $cached, 'cached' => true];
    }

    // Calculate fresh TCO
    // ... existing calculation logic ...

    // Save to cache
    db_execute("
        INSERT INTO building_tco_cache (...) VALUES (...)
        ON CONFLICT (building_id) DO UPDATE SET ...
    ");

    return ['success' => true, 'tco' => $result, 'cached' => false];
}
```

**Impact:** 10-100x performance forbedring for TCO queries på store projekter.

---

#### 2.2 OPEX Forecasting & Trends
**Problem:**
Systemet beregner årlig OPEX men viser ingen trends eller forecasting baseret på historiske data.

**Anbefaling:**
Tilføj OPEX trend analyse:
```php
/**
 * Get OPEX trend analysis for building
 * GET /api.php?module=opex&action=get_trends&building_id=123&years=10
 */
function handle_get_trends(array $user): array {
    // ... validation ...

    $years = min((int)($params['years'] ?? 10), 30);

    // Get current OPEX
    $opexSummary = db_fetch("...");
    $opexPerYear = (float)($opexSummary['effective_opex_yearly'] ?? 0);

    // Get escalation rate
    $opexEscalation = get_config('opex_escalation', 0.025);

    // Calculate forecast
    $forecast = [];
    for ($year = 0; $year <= $years; $year++) {
        $yearOpex = $opexPerYear * pow(1 + $opexEscalation, $year);
        $forecast[] = [
            'year' => $year,
            'opex' => $yearOpex,
            'cumulative' => array_sum(array_column($forecast, 'opex')) + $yearOpex
        ];
    }

    return [
        'success' => true,
        'trends' => [
            'current_opex' => $opexPerYear,
            'escalation_rate' => $opexEscalation,
            'forecast' => $forecast,
            'total_10y' => $forecast[9]['cumulative'],
            'total_30y' => end($forecast)['cumulative']
        ]
    ];
}
```

**Impact:** Bedre langtidsplanlægning og budget forecasting for kunder.

---

### 3. **Rapport System Forbedringer**

#### 3.1 Rapport Template Versionering
**Problem:**
Report templates (`report_templates` tabel) mangler versionering. Hvis en template opdateres, kan gamle rapporter der brugte den pågældende template ikke genskabes.

**Anbefaling:**
```sql
-- Tilføj version tracking til templates
ALTER TABLE report_templates ADD COLUMN version INT DEFAULT 1;
ALTER TABLE report_templates ADD COLUMN parent_template_id BIGINT REFERENCES report_templates(id);
ALTER TABLE report_templates ADD COLUMN is_published BOOLEAN DEFAULT false;

CREATE INDEX idx_template_versions ON report_templates(parent_template_id, version);

-- Track which template version blev brugt til hver rapport
ALTER TABLE reports ADD COLUMN template_id BIGINT REFERENCES report_templates(id);
ALTER TABLE reports ADD COLUMN template_version INT;
```

**PHP Implementation:**
```php
/**
 * Create new template version
 */
function handle_create_template_version(array $user): array {
    $templateId = $params['template_id'];

    // Get current template
    $current = db_fetch("SELECT * FROM report_templates WHERE id = :id", ['id' => $templateId]);

    // Create new version
    $newVersion = api_crud_create('report_templates', [
        'name' => $current['name'],
        'template_content' => $params['template_content'],
        'report_type' => $current['report_type'],
        'parent_template_id' => $current['parent_template_id'] ?? $templateId,
        'version' => ($current['version'] ?? 0) + 1,
        'is_published' => false,
        'created_by_user_id' => $user['id']
    ]);

    return $newVersion;
}
```

**Impact:** Template audit trail og mulighed for at genskabe gamle rapporter præcist.

---

#### 3.2 Rapport Preview Cache
**Problem:**
`handle_preview()` renderer template hver gang, selv for identiske requests. Dette er ineffektivt for store rapporter.

**Anbefaling:**
```php
function handle_preview(array $user): array {
    // ... validation ...

    // Generate cache key
    $cacheKey = md5($params['template'] . '|' . $params['project_id']);

    // Check cache (Redis eller memcached)
    $cached = cache_get("report_preview:$cacheKey");
    if ($cached) {
        return [
            'success' => true,
            'rendered' => $cached,
            'cached' => true
        ];
    }

    // Render fresh
    $data = get_report_data($params['project_id']);
    $rendered = render_template($params['template'], $data);

    // Cache for 5 minutes
    cache_set("report_preview:$cacheKey", $rendered, 300);

    return [
        'success' => true,
        'rendered' => $rendered,
        'cached' => false
    ];
}
```

**Impact:** Hurtigere preview rendering og reduceret server load.

---

#### 3.3 Rapport Export til Excel
**Problem:**
Systemet kan kun generere HTML rapporter. Mange kunder ønsker Excel export til videre analyse.

**Anbefaling:**
Tilføj Excel export handling:
```php
/**
 * Export rapport til Excel
 * POST /api.php?module=report_builder&action=export_excel
 */
function handle_export_excel(array $user): array {
    require_once 'vendor/phpoffice/phpspreadsheet/autoload.php';

    // ... validation & data fetching ...

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Project summary
    $sheet->setCellValue('A1', 'Projekt');
    $sheet->setCellValue('B1', $data['project']['name']);

    // Buildings
    $row = 3;
    $sheet->setCellValue('A' . $row, 'Bygning');
    $sheet->setCellValue('B' . $row, 'Type');
    $sheet->setCellValue('C' . $row, 'Areal');
    $sheet->setCellValue('D' . $row, 'CAPEX');

    $row++;
    foreach ($data['buildings'] as $building) {
        $sheet->setCellValue('A' . $row, $building['building_name']);
        $sheet->setCellValue('B' . $row, $building['building_type']);
        $sheet->setCellValue('C' . $row, $building['gross_area']);
        $sheet->setCellValue('D' . $row, $building['total_capex']);
        $row++;
    }

    // Elements
    // ... add elements data ...

    // Save to file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filename = "rapport_" . $data['project']['name'] . "_" . date('Y-m-d') . ".xlsx";
    $filepath = "/tmp/$filename";
    $writer->save($filepath);

    return [
        'success' => true,
        'download_url' => "/download.php?file=$filename",
        'filename' => $filename
    ];
}
```

**Impact:** Bedre kunde tilfredshed og mulighed for videre data analyse.

---

### 4. **Performance & Database Optimering**

#### 4.1 Database Index Audit
**Problem:**
Mange queries på joins mellem tabeller men potentielt manglende indexes.

**Anbefaling:**
Kør index audit:
```sql
-- Find missing indexes på foreign keys
SELECT
    tc.table_name,
    kcu.column_name,
    EXISTS (
        SELECT 1 FROM pg_indexes
        WHERE tablename = tc.table_name
        AND indexdef LIKE '%' || kcu.column_name || '%'
    ) as has_index
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
    ON tc.constraint_name = kcu.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
AND tc.table_schema = 'public'
HAVING has_index = false;

-- Tilføj manglende indexes
CREATE INDEX idx_building_elements_building_id ON building_elements(building_id);
CREATE INDEX idx_budget_lines_element_id ON budget_lines(element_id);
CREATE INDEX idx_building_opex_building_id ON building_opex(building_id);
CREATE INDEX idx_reports_project_id ON reports(project_id);
-- etc.
```

**Impact:** 5-50x performance forbedring på queries med joins.

---

#### 4.2 Query Optimization - N+1 Problem
**Problem:**
Mange steder i koden hentes data i loops hvilket skaber N+1 query problem.

**Eksempel Problem:**
```php
// Bad - N+1 queries
$elements = db_fetch_all("SELECT * FROM building_elements WHERE building_id = :id", ['id' => $buildingId]);
foreach ($elements as $element) {
    $budgetLines = db_fetch_all("SELECT * FROM budget_lines WHERE element_id = :id", ['id' => $element['id']]);
    // Process...
}
```

**Anbefaling:**
```php
// Good - 2 queries total
$elements = db_fetch_all("SELECT * FROM building_elements WHERE building_id = :id", ['id' => $buildingId]);
$elementIds = array_column($elements, 'id');

$budgetLines = db_fetch_all("
    SELECT * FROM budget_lines
    WHERE element_id = ANY(:ids)
    ORDER BY element_id, line_number
", ['ids' => '{' . implode(',', $elementIds) . '}']);

// Group by element_id
$linesByElement = [];
foreach ($budgetLines as $line) {
    $linesByElement[$line['element_id']][] = $line;
}

foreach ($elements as $element) {
    $element['budget_lines'] = $linesByElement[$element['id']] ?? [];
    // Process...
}
```

**Impact:** 10-100x performance forbedring afhængig af data størrelse.

---

#### 4.3 Materialized Views til Dashboards
**Problem:**
Dashboard queries aggregerer data fra mange tabeller hvilket kan være langsomt.

**Anbefaling:**
```sql
-- Create materialized view for project dashboard
CREATE MATERIALIZED VIEW mv_project_dashboard_stats AS
SELECT
    p.id as project_id,
    p.name as project_name,
    p.customer_id,
    c.name as customer_name,
    COUNT(DISTINCT b.id) as building_count,
    COUNT(DISTINCT be.id) as element_count,
    COALESCE(SUM(be.capex), 0) as total_capex,
    COUNT(DISTINCT be.id) FILTER (WHERE be.urgency = 'critical') as critical_count,
    COUNT(DISTINCT be.id) FILTER (WHERE be.urgency = 'high') as high_count,
    AVG(be.condition_score) as avg_condition,
    p.updated_at as last_updated
FROM projects p
LEFT JOIN customers c ON p.customer_id = c.id
LEFT JOIN buildings b ON p.id = b.project_id
LEFT JOIN building_elements be ON b.id = be.building_id
GROUP BY p.id, p.name, p.customer_id, c.name, p.updated_at;

-- Index på materialized view
CREATE INDEX idx_mv_project_dashboard_project ON mv_project_dashboard_stats(project_id);
CREATE INDEX idx_mv_project_dashboard_customer ON mv_project_dashboard_stats(customer_id);

-- Refresh function (kald periodisk eller ved ændringer)
CREATE OR REPLACE FUNCTION refresh_project_dashboard_stats()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_project_dashboard_stats;
END;
$$ LANGUAGE plpgsql;

-- Trigger til auto-refresh (optional - kan være tungt)
-- Alternativt: Kør via cron job eller on-demand
```

**Impact:** Dashboard loads 50-100x hurtigere.

---

### 5. **Sikkerhed & Validering**

#### 5.1 SQL Injection Prevention Audit
**Problem:**
Selvom de fleste queries bruger parameterized queries, er der steder med dynamic SQL der kan være sårbare.

**Eksempel Fund:**
```php
// modules/budget/api.php:53
$whereClause = implode(' AND ', $where);
$items = db_fetch_all("
    SELECT *
    FROM price_catalog
    WHERE $whereClause
    ORDER BY category, name
    LIMIT 50
", $params);
```

Dette er generelt sikkert fordi `$where` er hårdkodet, men bedste praksis er at bruge query builder.

**Anbefaling:**
```php
// Brug query builder pattern
class QueryBuilder {
    private $table;
    private $wheres = [];
    private $params = [];
    private $orderBy = [];
    private $limit = null;

    public function from(string $table) {
        $this->table = $table;
        return $this;
    }

    public function where(string $condition, array $params = []) {
        $this->wheres[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC') {
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function limit(int $limit) {
        $this->limit = $limit;
        return $this;
    }

    public function get() {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . (int)$this->limit;
        }

        return db_fetch_all($sql, $this->params);
    }
}

// Usage
$items = (new QueryBuilder())
    ->from('price_catalog')
    ->where('is_active = true')
    ->where('(name ILIKE :query OR description ILIKE :query)', ['query' => "%$query%"])
    ->orderBy('category')
    ->orderBy('name')
    ->limit(50)
    ->get();
```

**Impact:** Bedre sikkerhed og mindre risiko for SQL injection.

---

#### 5.2 CSRF Token Rotation
**Problem:**
CSRF tokens er statiske per session. Bedste praksis er at rotere tokens efter POST requests.

**Anbefaling:**
```php
// core/api-helpers.php
function api_require_csrf(): array {
    $token = $_POST['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return api_error('CSRF token mangler - prøv igen');
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return api_error('Ugyldig CSRF token');
    }

    // Rotate token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return ['success' => true];
}

// Return new token i response
function api_success(string $message = '', array $data = []): array {
    return [
        'success' => true,
        'message' => $message,
        'data' => $data,
        'csrf_token' => $_SESSION['csrf_token'] ?? null // New token for next request
    ];
}
```

**Frontend Update:**
```javascript
// Update CSRF token efter POST request
fetch('/api.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.csrf_token) {
        // Update token for next request
        document.querySelector('input[name="csrf_token"]').value = data.csrf_token;
    }
});
```

**Impact:** Bedre CSRF beskyttelse mod replay attacks.

---

#### 5.3 Rate Limiting
**Problem:**
API endpoints mangler rate limiting hvilket åbner for brute force og DoS attacks.

**Anbefaling:**
```php
/**
 * Simple rate limiter
 */
function check_rate_limit(string $identifier, int $maxRequests = 60, int $windowSeconds = 60): bool {
    $key = "rate_limit:$identifier";

    // Get current count from cache (Redis/Memcached)
    $count = (int)cache_get($key);

    if ($count >= $maxRequests) {
        return false; // Rate limit exceeded
    }

    // Increment counter
    cache_increment($key);

    // Set expiry on first request
    if ($count === 0) {
        cache_expire($key, $windowSeconds);
    }

    return true;
}

// Usage i router
$identifier = $user['id'] ?? $_SERVER['REMOTE_ADDR']; // User ID eller IP
if (!check_rate_limit($identifier, 60, 60)) {
    api_error('Rate limit exceeded. Try again in 1 minute.');
    exit;
}
```

**Impact:** Beskyttelse mod brute force og DoS attacks.

---

### 6. **Brugeroplevelse (UX) Forbedringer**

#### 6.1 Bulk Operations
**Problem:**
Mange operationer kræver individuelle requests (f.eks. sletning af multiple budget lines).

**Anbefaling:**
```php
/**
 * Bulk delete budget lines
 * POST /api.php {module: 'budget', action: 'bulk_delete', ids: [1,2,3]}
 */
function handle_bulk_delete(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $ids = json_decode($_POST['ids'] ?? '[]', true);

    if (!is_array($ids) || empty($ids)) {
        return api_error('Ingen IDs angivet');
    }

    // Verify all lines belong to projects user has access to
    $lines = db_fetch_all("
        SELECT bl.id, p.id as project_id
        FROM budget_lines bl
        JOIN building_elements be ON bl.element_id = be.id
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE bl.id = ANY(:ids)
    ", ['ids' => '{' . implode(',', $ids) . '}']);

    foreach ($lines as $line) {
        if (!can_access_project($user, $line['project_id'], 'editor')) {
            return api_error('Ingen adgang til en eller flere linjer');
        }
    }

    return api_transaction(
        function() use ($ids) {
            $deleted = db_execute("
                DELETE FROM budget_lines WHERE id = ANY(:ids)
            ", ['ids' => '{' . implode(',', $ids) . '}']);

            log_activity('budget_lines_bulk_deleted', 'budget_line', 0, ['count' => $deleted]);

            return ['deleted_count' => $deleted];
        },
        function($result) {
            return "{$result['deleted_count']} linjer slettet";
        },
        'Kunne ikke slette linjer'
    );
}
```

**Impact:** Hurtigere workflow for brugere der arbejder med store datasæt.

---

#### 6.2 Auto-Save & Draft System
**Problem:**
Hvis en bruger arbejder på en rapport eller budget og mister forbindelse/lukker browser, mistes alt arbejde.

**Anbefaling:**
```javascript
// Frontend auto-save implementation
class AutoSave {
    constructor(formId, saveEndpoint, interval = 30000) {
        this.form = document.getElementById(formId);
        this.saveEndpoint = saveEndpoint;
        this.interval = interval;
        this.isDirty = false;
        this.lastSave = null;

        this.init();
    }

    init() {
        // Track changes
        this.form.addEventListener('input', () => {
            this.isDirty = true;
        });

        // Auto-save interval
        setInterval(() => {
            if (this.isDirty) {
                this.save();
            }
        }, this.interval);

        // Save before unload
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty) {
                e.preventDefault();
                e.returnValue = 'Du har ugemte ændringer. Vil du fortsætte?';
                this.save();
            }
        });
    }

    async save() {
        const formData = new FormData(this.form);
        formData.append('is_draft', 'true');

        try {
            const response = await fetch(this.saveEndpoint, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.isDirty = false;
                this.lastSave = new Date();
                this.showSaveIndicator('Gemt');
            }
        } catch (error) {
            this.showSaveIndicator('Fejl ved gem', 'error');
        }
    }

    showSaveIndicator(message, type = 'success') {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 3000);
    }
}

// Usage
new AutoSave('budget-form', '/api.php?module=budget&action=auto_save', 30000);
```

**Backend:**
```php
/**
 * Auto-save draft
 * POST /api.php?module=budget&action=auto_save
 */
function handle_auto_save(array $user): array {
    // Similar to handle_save_lines but mark as draft
    // Store in separate drafts table or use is_draft flag
}
```

**Impact:** Bedre brugeroplevelse og ingen tabt arbejde ved unexpected disconnects.

---

#### 6.3 Keyboard Shortcuts
**Problem:**
Power users skal bruge musen til alt hvilket er ineffektivt.

**Anbefaling:**
```javascript
// Keyboard shortcuts system
class KeyboardShortcuts {
    constructor() {
        this.shortcuts = {
            's': { ctrl: true, action: () => this.save(), description: 'Gem' },
            'n': { ctrl: true, action: () => this.new(), description: 'Ny' },
            'f': { ctrl: true, action: () => this.search(), description: 'Søg' },
            'p': { ctrl: true, action: () => this.print(), description: 'Print' },
            'h': { ctrl: true, shift: true, action: () => this.showHelp(), description: 'Hjælp' },
            'Escape': { action: () => this.closeModal(), description: 'Luk' }
        };

        this.init();
    }

    init() {
        document.addEventListener('keydown', (e) => {
            const key = e.key;
            const shortcut = this.shortcuts[key];

            if (!shortcut) return;

            // Check modifiers
            if (shortcut.ctrl && !e.ctrlKey) return;
            if (shortcut.shift && !e.shiftKey) return;
            if (shortcut.alt && !e.altKey) return;

            // Don't trigger in input fields (unless explicitly allowed)
            if (['INPUT', 'TEXTAREA'].includes(e.target.tagName) && key !== 'Escape') {
                return;
            }

            e.preventDefault();
            shortcut.action();
        });
    }

    showHelp() {
        const helpContent = Object.entries(this.shortcuts)
            .map(([key, shortcut]) => {
                const modifiers = [];
                if (shortcut.ctrl) modifiers.push('Ctrl');
                if (shortcut.shift) modifiers.push('Shift');
                if (shortcut.alt) modifiers.push('Alt');
                modifiers.push(key.toUpperCase());

                return `<div class="shortcut-item">
                    <kbd>${modifiers.join(' + ')}</kbd>
                    <span>${shortcut.description}</span>
                </div>`;
            })
            .join('');

        // Show modal with shortcuts
        showModal('Keyboard Shortcuts', helpContent);
    }
}

new KeyboardShortcuts();
```

**Impact:** Hurtigere workflow for power users.

---

## 🔄 Medium Prioritet Forbedringer

### 7. **Data Export & Import**

#### 7.1 CSV Import for Budget Lines
**Anbefaling:** Tillad brugere at upload CSV files med budget lines i stedet for manuel indtastning.

#### 7.2 Project Export/Import
**Anbefaling:** Eksport komplet projekt som JSON eller ZIP for backup eller migration mellem systemer.

---

### 8. **Notifications & Alerts**

#### 8.1 Email Notifications
**Anbefaling:** Send email ved kritiske events (ny rapport, budget overskredet, element urgent status).

#### 8.2 In-App Notifications
**Anbefaling:** Notification center i UI for at vise alerts og opdateringer.

---

### 9. **Analytics & Reporting**

#### 9.1 Usage Analytics
**Anbefaling:** Track bruger aktivitet for at forstå hvilke features der bruges mest.

#### 9.2 Custom Report Builder
**Anbefaling:** Drag-and-drop report builder hvor brugere kan lave custom queries uden at skrive SQL.

---

## 💡 Lavere Prioritet (Nice-to-Have)

### 10. **AI/ML Features**

#### 10.1 CAPEX Prediction
Brug ML til at forudsige CAPEX baseret på bygnings karakteristika.

#### 10.2 Anomaly Detection
Automatisk detect usædvanlige værdier i budget lines eller CAPEX.

---

### 11. **Mobile App**
Native mobile app til iOS/Android for field inspections.

---

### 12. **Integration med Tredjepartsværktøjer**
- Accounting software (e.g., Economic, Dynamics)
- Project management (e.g., Asana, Monday.com)
- BIM software (e.g., Revit, ArchiCAD)

---

## 🎯 Implementerings Roadmap

### Phase 1 (Måned 1-2) - Critical Fixes
- CAPEX validering og warnings
- Database indexes audit og tilføjelse
- N+1 query optimization
- Rate limiting
- CSRF token rotation

### Phase 2 (Måned 3-4) - Performance
- TCO cache implementation
- Materialized views for dashboards
- Rapport preview cache
- Query builder refactoring

### Phase 3 (Måned 5-6) - Features
- Budget line historik
- Bulk operations
- Auto-save system
- Excel export
- Keyboard shortcuts

### Phase 4 (Måned 7-9) - Advanced
- OPEX forecasting
- Template versionering
- Custom report builder
- Advanced analytics

### Phase 5 (Måned 10-12) - Nice-to-Have
- Email notifications
- Mobile app POC
- AI/ML features exploration
- Third-party integrations

---

## 📊 Estimated Impact

| Forbedring | Development Tid | Performance Gain | User Satisfaction | ROI |
|------------|----------------|------------------|-------------------|-----|
| Database indexes | 1-2 dage | 5-50x | Medium | Meget Høj |
| N+1 query fix | 3-5 dage | 10-100x | Medium | Meget Høj |
| TCO cache | 2-3 dage | 10-100x | Medium | Høj |
| CAPEX validering | 2-3 dage | N/A | Høj | Høj |
| Auto-save | 5-7 dage | N/A | Meget Høj | Høj |
| Excel export | 3-5 dage | N/A | Meget Høj | Høj |
| Bulk operations | 3-5 dage | 5-10x | Høj | Høj |
| Budget historik | 5-7 dage | N/A | Medium | Medium |
| Keyboard shortcuts | 2-3 dage | N/A | Høj | Medium |

---

## 🔧 Quick Wins (Kan implementeres hurtigt)

1. **Database Indexes** (2 dage, massiv performance gain)
2. **CAPEX Validering** (2 dage, bedre data quality)
3. **Rate Limiting** (1 dag, sikkerhed)
4. **CSRF Token Rotation** (1 dag, sikkerhed)
5. **Bulk Delete** (2 dage, bedre UX)

---

## 📞 Support & Implementation

For assistance med implementation af disse forbedringer:
- Review kode i `/home/user/DueDiligence`
- Test changes på staging environment først
- Kør performance benchmarks før og efter
- Dokumenter alle ændringer

**Version:** 1.0
**Dato:** 21. januar 2026
**Baseret på:** Kodebase analyse af DueDiligence platform
