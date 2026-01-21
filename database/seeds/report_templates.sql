-- Report Template Seeds
-- Indsæt 8 rapport templates fra simpel til avanceret

-- 1. SIMPEL: Projekt Oversigt
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Simpel Projekt Oversigt',
'<div class="report-simple">
  <h1>{{project.name}}</h1>
  <p><strong>Kunde:</strong> {{project.customer_name}}</p>
  <p><strong>Oprettet:</strong> {{project.created_at | date}}</p>

  <h2>Opsummering</h2>
  <ul>
    <li>Bygninger: {{project.building_count}}</li>
    <li>Elementer: {{project.element_count}}</li>
    <li>Total CAPEX: {{project.total_capex | currency}}</li>
  </ul>

  {{if project.critical_count > 0}}
  <div class="alert alert-danger">
    <strong>OBS!</strong> {{project.critical_count}} kritiske elementer kræver opmærksomhed.
  </div>
  {{endif}}
</div>',
'custom',
1,
NOW(),
NOW()
);

-- 2. MEDIUM: Bygnings Liste
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Bygnings Oversigt',
'<div class="report-buildings">
  <h1>Bygningsrapport: {{project.name}}</h1>
  <p class="report-meta">
    Genereret: {{project.generated_at | date}}<br>
    Af: {{project.generated_by}}
  </p>

  <h2>Bygninger ({{project.building_count}} total)</h2>

  {{for building in buildings}}
  <div class="building-section">
    <h3>{{building.building_name}} ({{building.building_number}})</h3>

    <table class="info-table">
      <tr>
        <td>Type:</td>
        <td>{{building.building_type}}</td>
      </tr>
      <tr>
        <td>Areal:</td>
        <td>{{building.gross_area | number}} m²</td>
      </tr>
      <tr>
        <td>CAPEX:</td>
        <td>{{building.total_capex | currency}}</td>
      </tr>
      <tr>
        <td>Gennemsnitlig tilstand:</td>
        <td>{{building.avg_condition}}/10</td>
      </tr>
    </table>

    {{if building.critical_count > 0}}
    <p class="warning">⚠️ {{building.critical_count}} kritiske elementer</p>
    {{endif}}
  </div>
  {{endfor}}
</div>',
'due_diligence',
1,
NOW(),
NOW()
);

-- 3. AVANCERET: Komplet Due Diligence med TOC
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Komplet Due Diligence Rapport',
'<div class="report-complete">
  <!-- Forside -->
  <div class="cover-page">
    <h1>Due Diligence Rapport</h1>
    <h2>{{project.name}}</h2>
    <p class="meta">
      Kunde: {{project.customer_name}}<br>
      Dato: {{project.generated_at | date}}<br>
      Udarbejdet af: {{project.generated_by}}
    </p>
  </div>

  <!-- Indholdsfortegnelse -->
  <div class="toc-page">
    <h2>Indholdsfortegnelse</h2>
    <ol class="toc">
      <li><a href="#executive">Executive Summary</a></li>
      <li><a href="#buildings">Bygningsoversigt</a></li>
      <li><a href="#elements">Elementanalyse</a></li>
      <li><a href="#redflags">Røde Flag</a></li>
      <li><a href="#budget">Budget & Økonomi</a></li>
      <li><a href="#recommendations">Anbefalinger</a></li>
    </ol>
  </div>

  <!-- 1. Executive Summary -->
  <div id="executive" class="section">
    <h2>1. Executive Summary</h2>

    <h3>Projekt Overview</h3>
    <p>Denne rapport omfatter due diligence analyse af <strong>{{project.name}}</strong>
    for kunde {{project.customer_name}}. Projektet består af {{project.building_count}} bygninger
    med i alt {{project.element_count}} registrerede elementer.</p>

    <h3>Nøgletal</h3>
    <table class="key-metrics">
      <tr>
        <td><strong>Total CAPEX:</strong></td>
        <td class="number">{{project.total_capex | currency}}</td>
      </tr>
      <tr>
        <td><strong>Kritiske elementer:</strong></td>
        <td class="number">{{project.critical_count}}</td>
      </tr>
      <tr>
        <td><strong>Høj prioritet:</strong></td>
        <td class="number">{{project.high_count}}</td>
      </tr>
      <tr>
        <td><strong>Dårlig tilstand:</strong></td>
        <td class="number">{{project.poor_count}}</td>
      </tr>
    </table>

    {{if project.critical_count > 0}}
    <div class="alert alert-critical">
      <h4>⚠️ Kritisk Opmærksomhed Påkrævet</h4>
      <p>Der er identificeret {{project.critical_count}} elementer med kritisk prioritet,
      der kræver øjeblikkelig handling. Se sektion 4 for detaljer.</p>
    </div>
    {{else}}
    <div class="alert alert-success">
      <h4>✓ Ingen Kritiske Problemer</h4>
      <p>Der er ikke identificeret elementer med kritisk prioritet i denne analyse.</p>
    </div>
    {{endif}}
  </div>

  <!-- 2. Bygningsoversigt -->
  <div id="buildings" class="section">
    <h2>2. Bygningsoversigt</h2>

    {{for building in buildings}}
    <div class="building-detail">
      <h3>{{building.building_name}}</h3>

      <div class="building-header">
        <table class="building-info">
          <tr>
            <td>Bygningsnummer:</td>
            <td><strong>{{building.building_number}}</strong></td>
          </tr>
          <tr>
            <td>Type:</td>
            <td>{{building.building_type}}</td>
          </tr>
          <tr>
            <td>Bruttoareal:</td>
            <td>{{building.gross_area | number}} m²</td>
          </tr>
          <tr>
            <td>Antal elementer:</td>
            <td>{{building.element_count}}</td>
          </tr>
        </table>

        <table class="building-metrics">
          <tr>
            <td>CAPEX:</td>
            <td class="number">{{building.total_capex | currency}}</td>
          </tr>
          <tr>
            <td>Gns. tilstand:</td>
            <td>{{building.avg_condition}}/10</td>
          </tr>
          <tr>
            <td>Kritiske:</td>
            <td class="{{if building.critical_count > 0}}critical{{endif}}">
              {{building.critical_count}}
            </td>
          </tr>
          <tr>
            <td>Høj prioritet:</td>
            <td>{{building.high_count}}</td>
          </tr>
        </table>
      </div>

      {{if building.element_count > 0}}
      <h4>Elementer</h4>
      <table class="elements-table">
        <thead>
          <tr>
            <th>Element</th>
            <th>Kode</th>
            <th>Tilstand</th>
            <th>Prioritet</th>
            <th>CAPEX</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {{for element in elements}}
          {{if element.building_id == building.building_id}}
          <tr class="element-row">
            <td>{{element.element_name}}</td>
            <td><code>{{element.element_code}}</code></td>
            <td>{{element.condition_score}}/10</td>
            <td>{{element.urgency | capitalize}}</td>
            <td class="number">{{element.capex | currency}}</td>
            <td>{{red_flags_display(element)}}</td>
          </tr>
          {{endif}}
          {{endfor}}
        </tbody>
      </table>
      {{else}}
      <p class="no-data">Ingen elementer registreret for denne bygning.</p>
      {{endif}}
    </div>
    {{endfor}}
  </div>

  <!-- 3. Elementanalyse -->
  <div id="elements" class="section">
    <h2>3. Elementanalyse</h2>

    <h3>Fordeling efter Tilstand</h3>
    <div class="condition-distribution">
      <!-- Tilstands distribution ville normalt være en chart -->
      <p>Baseret på {{project.element_count}} registrerede elementer.</p>
    </div>

    <h3>Fordeling efter Prioritet</h3>
    <ul>
      <li><strong>Kritisk:</strong> {{project.critical_count}} elementer</li>
      <li><strong>Høj:</strong> {{project.high_count}} elementer</li>
      <li><strong>Dårlig tilstand:</strong> {{project.poor_count}} elementer</li>
    </ul>
  </div>

  <!-- 4. Røde Flag -->
  <div id="redflags" class="section">
    <h2>4. Røde Flag & Kritiske Punkter</h2>

    {{if project.critical_count > 0}}
    <p>Følgende elementer er identificeret med høj risiko og kræver øjeblikkelig opmærksomhed:</p>

    <table class="red-flags-table">
      <thead>
        <tr>
          <th>Element</th>
          <th>Bygning</th>
          <th>Alvorlighed</th>
          <th>Score</th>
          <th>CAPEX</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        {{for flag in red_flags}}
        <tr class="flag-{{flag.severity}}">
          <td>{{flag.element_name}}</td>
          <td>{{flag.building_name}}</td>
          <td>{{flag.severity | capitalize}}</td>
          <td class="score">{{flag.red_flag_score}}</td>
          <td class="number">{{flag.capex | currency}}</td>
          <td>{{red_flags_display(flag)}}</td>
        </tr>
        {{endfor}}
      </tbody>
    </table>
    {{else}}
    <div class="alert alert-success">
      <p>✓ Der er ingen kritiske røde flag identificeret i denne analyse.</p>
    </div>
    {{endif}}
  </div>

  <!-- 5. Budget & Økonomi -->
  <div id="budget" class="section">
    <h2>5. Budget & Økonomi</h2>

    <h3>CAPEX Fordeling</h3>
    <table class="budget-table">
      <tr>
        <td>Total CAPEX:</td>
        <td class="number">{{project.total_capex | currency}}</td>
      </tr>
      <tr>
        <td>Kritiske elementer:</td>
        <td class="number">
          {{if project.critical_count > 0}}
          Ca. 30-40% af total (estimeret)
          {{else}}
          -
          {{endif}}
        </td>
      </tr>
    </table>

    {{if project.include_budgets}}
    <h3>OPEX (Driftsomkostninger)</h3>
    <p>OPEX data inkluderet i bygningsoversigten.</p>
    {{endif}}
  </div>

  <!-- 6. Anbefalinger -->
  <div id="recommendations" class="section">
    <h2>6. Anbefalinger</h2>

    {{if project.critical_count > 5}}
    <h3>Høj Prioritet</h3>
    <p>Med {{project.critical_count}} kritiske elementer anbefales det at:</p>
    <ol>
      <li>Håndtere alle kritiske elementer inden for 0-6 måneder</li>
      <li>Allokere budget til øjeblikkelige reparationer</li>
      <li>Etablere løbende vedligeholdelsesprogram</li>
    </ol>
    {{else if project.critical_count > 0}}
    <h3>Moderat Prioritet</h3>
    <p>Med {{project.critical_count}} kritiske elementer anbefales det at:</p>
    <ol>
      <li>Planlægge håndtering af kritiske elementer inden for 6-12 måneder</li>
      <li>Udarbejde detaljeret vedligeholdelsesplan</li>
    </ol>
    {{else}}
    <h3>Lav Prioritet</h3>
    <p>Ingen kritiske elementer identificeret. Fortsæt med normal vedligeholdelse.</p>
    {{endif}}

    <h3>Næste Skridt</h3>
    <ol>
      <li>Review denne rapport med relevante stakeholders</li>
      <li>Prioriter budget allokering baseret på findings</li>
      <li>Etabler timeline for implementering</li>
      <li>Planlæg opfølgende inspektioner</li>
    </ol>
  </div>

  <!-- Appendix -->
  <div class="appendix">
    <h2>Appendix</h2>
    <p><strong>Rapport genereret:</strong> {{project.generated_at | date}}</p>
    <p><strong>Genereret af:</strong> {{project.generated_by}}</p>
    <p><strong>System version:</strong> DueDiligence v2.0</p>
  </div>
</div>

<style>
.report-complete { font-family: Arial, sans-serif; max-width: 210mm; margin: auto; }
.cover-page { page-break-after: always; text-align: center; padding-top: 40%; }
.toc-page { page-break-after: always; }
.toc { list-style: decimal; line-height: 2; }
.section { page-break-after: always; margin: 20px 0; }
.alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
.alert-critical { background: #fee; border-left: 4px solid #c00; }
.alert-success { background: #efe; border-left: 4px solid #0c0; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #f5f5f5; font-weight: bold; }
.number { text-align: right; }
.critical { color: #c00; font-weight: bold; }
.building-detail { margin: 30px 0; }
.building-header { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 15px 0; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
</style>',
'due_diligence',
1,
NOW(),
NOW()
);

-- 4. KUNDE-SPECIFIK: Executive Summary (Kort version)
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Executive Summary (Kunde Version)',
'<div class="executive-report">
  <div class="header">
    <h1>Executive Summary</h1>
    <h2>{{project.name}}</h2>
    <p class="subtitle">Forberedt for {{project.customer_name}}</p>
    <p class="date">{{project.generated_at | date}}</p>
  </div>

  <div class="key-findings">
    <h3>Nøglefund</h3>

    <div class="metric-cards">
      <div class="card">
        <div class="card-value">{{project.building_count}}</div>
        <div class="card-label">Bygninger</div>
      </div>

      <div class="card">
        <div class="card-value">{{project.total_capex | currency}}</div>
        <div class="card-label">Estimeret CAPEX</div>
      </div>

      <div class="card {{if project.critical_count > 0}}card-warning{{endif}}">
        <div class="card-value">{{project.critical_count}}</div>
        <div class="card-label">Kritiske Elementer</div>
      </div>

      <div class="card">
        <div class="card-value">{{project.element_count}}</div>
        <div class="card-label">Analyserede Elementer</div>
      </div>
    </div>
  </div>

  {{if project.critical_count > 10}}
  <div class="risk-assessment high-risk">
    <h3>🔴 Høj Risiko</h3>
    <p>Med {{project.critical_count}} kritiske elementer vurderes projektet til <strong>høj risiko</strong>.
    Øjeblikkelig handling anbefales.</p>
  </div>
  {{else if project.critical_count > 3}}
  <div class="risk-assessment medium-risk">
    <h3>🟡 Moderat Risiko</h3>
    <p>Med {{project.critical_count}} kritiske elementer vurderes projektet til <strong>moderat risiko</strong>.
    Planlægning af reparationer anbefales.</p>
  </div>
  {{else}}
  <div class="risk-assessment low-risk">
    <h3>🟢 Lav Risiko</h3>
    <p>Projektet vurderes til <strong>lav risiko</strong> med normal vedligeholdelse anbefalet.</p>
  </div>
  {{endif}}

  <div class="recommendations">
    <h3>Anbefalinger</h3>
    <ol>
      {{if project.critical_count > 0}}
      <li>Prioriter reparation af {{project.critical_count}} kritiske elementer</li>
      {{endif}}
      <li>Gennemgå detaljeret due diligence rapport for fuld analyse</li>
      <li>Allokér budget til højt prioriterede opgaver</li>
      <li>Planlæg opfølgende inspektioner om 6-12 måneder</li>
    </ol>
  </div>

  <div class="next-steps">
    <h3>Næste Skridt</h3>
    <p>Denne executive summary giver et hurtigt overblik. For detaljeret information,
    se venligst den komplette due diligence rapport.</p>
  </div>

  <div class="footer">
    <p>Forberedt af {{project.generated_by}} | {{project.customer_name}}</p>
  </div>
</div>

<style>
.executive-report { font-family: "Segoe UI", sans-serif; max-width: 800px; margin: auto; padding: 40px; }
.header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #333; padding-bottom: 20px; }
.header h1 { font-size: 32px; margin: 0; color: #333; }
.header h2 { font-size: 24px; margin: 10px 0; color: #666; }
.subtitle { font-size: 18px; color: #888; margin: 10px 0; }
.date { color: #aaa; font-size: 14px; }
.metric-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 30px 0; }
.card { background: #f8f9fa; padding: 30px; border-radius: 8px; text-align: center; border: 1px solid #e0e0e0; }
.card-value { font-size: 36px; font-weight: bold; color: #333; margin-bottom: 10px; }
.card-label { font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
.card-warning { background: #fff3cd; border-color: #ffc107; }
.card-warning .card-value { color: #ff6b00; }
.risk-assessment { padding: 20px; margin: 30px 0; border-radius: 8px; border-left: 5px solid; }
.high-risk { background: #fee; border-color: #c00; }
.medium-risk { background: #fff8e1; border-color: #ffc107; }
.low-risk { background: #e8f5e9; border-color: #4caf50; }
.risk-assessment h3 { margin-top: 0; }
.recommendations, .next-steps { margin: 30px 0; }
.recommendations ol { line-height: 2; }
.footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #888; font-size: 12px; }
</style>',
'executive_summary',
1,
NOW(),
NOW()
);

-- 5. MEDIUM: Budget Fokuseret Rapport
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Budget Analyse Rapport',
'<div class="budget-report">
  <h1>Budget Analyse: {{project.name}}</h1>
  <p class="meta">Kunde: {{project.customer_name}} | Dato: {{project.generated_at | date}}</p>

  <h2>Budget Oversigt</h2>

  <table class="budget-summary">
    <thead>
      <tr>
        <th>Kategori</th>
        <th>Antal</th>
        <th>Estimat (DKK)</th>
        <th>%</th>
      </tr>
    </thead>
    <tbody>
      <tr class="total-row">
        <td><strong>Total CAPEX</strong></td>
        <td>{{project.element_count}}</td>
        <td class="amount"><strong>{{project.total_capex | currency}}</strong></td>
        <td>100%</td>
      </tr>
      <tr>
        <td>Kritisk prioritet</td>
        <td>{{project.critical_count}}</td>
        <td class="amount">Inkl. i total</td>
        <td>-</td>
      </tr>
      <tr>
        <td>Høj prioritet</td>
        <td>{{project.high_count}}</td>
        <td class="amount">Inkl. i total</td>
        <td>-</td>
      </tr>
    </tbody>
  </table>

  <h2>Bygnings Fordeling</h2>

  {{for building in buildings}}
  <div class="building-budget">
    <h3>{{building.building_name}}</h3>

    <table class="building-budget-table">
      <tr>
        <td>Bruttoareal:</td>
        <td>{{building.gross_area | number}} m²</td>
        <td rowspan="3" class="capex-cell">
          <div class="capex-amount">{{building.total_capex | currency}}</div>
          <div class="capex-label">Estimeret CAPEX</div>
        </td>
      </tr>
      <tr>
        <td>CAPEX per m²:</td>
        <td>{{building.total_capex / building.gross_area | currency}}/m²</td>
      </tr>
      <tr>
        <td>Elementer:</td>
        <td>{{building.element_count}}</td>
      </tr>
    </table>

    {{if building.critical_count > 0}}
    <div class="budget-warning">
      ⚠️ {{building.critical_count}} elementer med kritisk prioritet kræver øjeblikkelig budget allokering
    </div>
    {{endif}}
  </div>
  {{endfor}}

  <h2>Budget Anbefaling</h2>

  {{if project.critical_count > 5}}
  <p><strong>Anbefalet umiddelbar allokering:</strong> Prioriter kritiske elementer ({{project.critical_count}} stk)</p>
  <p><strong>Timeline:</strong> 0-6 måneder</p>
  <p><strong>Estimeret beløb:</strong> 30-40% af total CAPEX</p>
  {{else}}
  <p><strong>Anbefalet planlagt allokering:</strong> Spredt over 12-24 måneder</p>
  <p><strong>Prioriter:</strong> Elementer med høj prioritet først</p>
  {{endif}}
</div>

<style>
.budget-report { max-width: 900px; margin: auto; font-family: Arial, sans-serif; }
.budget-summary { margin: 20px 0; }
.budget-summary th { background: #2c3e50; color: white; padding: 12px; }
.budget-summary td { padding: 10px; }
.total-row { background: #ecf0f1; font-size: 18px; }
.amount { text-align: right; font-family: monospace; }
.building-budget { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
.building-budget-table { width: 100%; }
.capex-cell { text-align: center; vertical-align: middle; background: #f8f9fa; }
.capex-amount { font-size: 24px; font-weight: bold; color: #2c3e50; }
.capex-label { font-size: 12px; color: #7f8c8d; text-transform: uppercase; }
.budget-warning { margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; }
</style>',
'budget_overview',
1,
NOW(),
NOW()
);

-- 6. SIMPEL: Quick Status
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Quick Status Rapport',
'<div class="quick-status">
  <h1>{{project.name}} - Status</h1>
  <p>{{project.generated_at | date}}</p>

  <div class="status-grid">
    <div class="status-item">
      <div class="label">Bygninger</div>
      <div class="value">{{project.building_count}}</div>
    </div>

    <div class="status-item">
      <div class="label">Elementer</div>
      <div class="value">{{project.element_count}}</div>
    </div>

    <div class="status-item {{if project.critical_count > 0}}critical{{endif}}">
      <div class="label">Kritiske</div>
      <div class="value">{{project.critical_count}}</div>
    </div>

    <div class="status-item">
      <div class="label">CAPEX</div>
      <div class="value small">{{project.total_capex | currency}}</div>
    </div>
  </div>

  {{if project.critical_count > 0}}
  <div class="alert">
    <strong>Action Required:</strong> {{project.critical_count}} kritiske elementer
  </div>
  {{endif}}

  <h2>Bygninger</h2>
  <ul>
  {{for building in buildings}}
    <li>
      <strong>{{building.building_name}}</strong>
      - {{building.element_count}} elementer
      {{if building.critical_count > 0}}
      <span class="badge">{{building.critical_count}} kritiske</span>
      {{endif}}
    </li>
  {{endfor}}
  </ul>
</div>

<style>
.quick-status { max-width: 600px; margin: auto; font-family: Arial, sans-serif; }
.status-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
.status-item { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
.status-item.critical { background: #fee; border: 2px solid #c00; }
.label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
.value { font-size: 32px; font-weight: bold; color: #333; }
.value.small { font-size: 20px; }
.alert { padding: 15px; margin: 20px 0; background: #fff3cd; border-left: 4px solid #ffc107; }
.badge { background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
</style>',
'custom',
1,
NOW(),
NOW()
);

-- 7. AVANCERET: Tilstandsvurdering med Detaljer
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Detaljeret Tilstandsvurdering',
'<div class="condition-report">
  <h1>Tilstandsvurdering</h1>
  <h2>{{project.name}}</h2>

  <div class="report-header">
    <p><strong>Kunde:</strong> {{project.customer_name}}</p>
    <p><strong>Dato:</strong> {{project.generated_at | date}}</p>
    <p><strong>Udarbejdet af:</strong> {{project.generated_by}}</p>
  </div>

  <h2>Samlet Vurdering</h2>

  <div class="overall-condition">
    {{if project.poor_count > project.element_count * 0.3}}
    <div class="grade poor">D - Dårlig tilstand</div>
    <p>Over 30% af elementerne er i dårlig tilstand. Omfattende renovering anbefales.</p>
    {{else if project.critical_count > 10}}
    <div class="grade mediocre">C - Moderat tilstand</div>
    <p>Adskillige kritiske elementer kræver opmærksomhed. Prioriteret renovering nødvendig.</p>
    {{else if project.critical_count > 0}}
    <div class="grade fair">B - Acceptabel tilstand</div>
    <p>Enkelte kritiske elementer. Normal vedligeholdelse med fokus på prioriteter.</p>
    {{else}}
    <div class="grade good">A - God tilstand</div>
    <p>Ingen kritiske problemer identificeret. Fortsæt forebyggende vedligeholdelse.</p>
    {{endif}}
  </div>

  <h2>Bygninger - Detaljeret Analyse</h2>

  {{for building in buildings}}
  <div class="building-condition">
    <h3>{{building.building_name}} ({{building.building_number}})</h3>

    <div class="building-stats">
      <table>
        <tr>
          <td>Gennemsnitlig tilstandsscore:</td>
          <td class="score">
            <strong>{{building.avg_condition}}/10</strong>
            {{if building.avg_condition >= 8}}
            <span class="grade-badge good">God</span>
            {{else if building.avg_condition >= 6}}
            <span class="grade-badge fair">Acceptabel</span>
            {{else if building.avg_condition >= 4}}
            <span class="grade-badge mediocre">Moderat</span>
            {{else}}
            <span class="grade-badge poor">Dårlig</span>
            {{endif}}
          </td>
        </tr>
        <tr>
          <td>Kritiske elementer:</td>
          <td>{{building.critical_count}} af {{building.element_count}}</td>
        </tr>
        <tr>
          <td>Høj prioritet:</td>
          <td>{{building.high_count}} af {{building.element_count}}</td>
        </tr>
      </table>
    </div>

    {{if building.element_count > 0}}
    <h4>Element Detaljer</h4>

    <table class="elements-detail">
      <thead>
        <tr>
          <th>Element</th>
          <th>Kode</th>
          <th>Tilstand</th>
          <th>Prioritet</th>
          <th>Flags</th>
          <th>CAPEX</th>
          <th>Vurdering</th>
        </tr>
      </thead>
      <tbody>
        {{for element in elements}}
        {{if element.building_id == building.building_id}}
        <tr class="element-detail-row {{if element.urgency == 'critical'}}row-critical{{else if element.urgency == 'high'}}row-high{{endif}}">
          <td>{{element.element_name}}</td>
          <td><code>{{element.element_code}}</code></td>
          <td class="center">
            {{element.condition_score}}/10
            {{if element.condition_score >= 8}}
            <div class="condition-bar good" style="width: {{element.condition_score * 10}}%"></div>
            {{else if element.condition_score >= 6}}
            <div class="condition-bar fair" style="width: {{element.condition_score * 10}}%"></div>
            {{else if element.condition_score >= 4}}
            <div class="condition-bar mediocre" style="width: {{element.condition_score * 10}}%"></div>
            {{else}}
            <div class="condition-bar poor" style="width: {{element.condition_score * 10}}%"></div>
            {{endif}}
          </td>
          <td>
            {{if element.urgency == 'critical'}}
            🔴 Kritisk
            {{else if element.urgency == 'high'}}
            🟡 Høj
            {{else if element.urgency == 'medium'}}
            🟢 Mellem
            {{else}}
            ⚪ Lav
            {{endif}}
          </td>
          <td>{{red_flags_display(element)}}</td>
          <td class="amount">{{element.capex | currency}}</td>
          <td class="notes">
            {{if element.condition_score < 4}}
            Udskiftning påkrævet
            {{else if element.condition_score < 6}}
            Renovering anbefales
            {{else if element.condition_score < 8}}
            Forebyggende vedligehold
            {{else}}
            God stand
            {{endif}}
          </td>
        </tr>
        {{endif}}
        {{endfor}}
      </tbody>
    </table>
    {{endif}}
  </div>
  {{endfor}}

  <h2>Anbefalinger</h2>

  <div class="recommendations">
    <h3>Kort Sigt (0-12 måneder)</h3>
    <ul>
      {{if project.critical_count > 0}}
      <li>Håndter alle {{project.critical_count}} kritiske elementer</li>
      {{endif}}
      {{if project.high_count > 0}}
      <li>Planlæg renovering af {{project.high_count}} høj prioritet elementer</li>
      {{endif}}
      <li>Gennemfør detaljerede inspektioner af kritiske elementer</li>
    </ul>

    <h3>Mellem Sigt (1-3 år)</h3>
    <ul>
      <li>Etabler forebyggende vedligeholdelsesprogram</li>
      <li>Prioriter elementer med moderat tilstand</li>
      <li>Budgetlæg for planlagte udskiftninger</li>
    </ul>

    <h3>Lang Sigt (3-5 år)</h3>
    <ul>
      <li>Moderniser ældre installationer</li>
      <li>Gennemfør ny tilstandsvurdering</li>
      <li>Evaluer energieffektiviseringer</li>
    </ul>
  </div>
</div>

<style>
.condition-report { max-width: 1000px; margin: auto; font-family: Arial, sans-serif; }
.report-header { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px; }
.overall-condition { text-align: center; margin: 30px 0; padding: 30px; background: #f8f9fa; border-radius: 8px; }
.grade { display: inline-block; font-size: 48px; font-weight: bold; margin-bottom: 15px; padding: 20px 40px; border-radius: 8px; }
.grade.good { background: #d4edda; color: #155724; }
.grade.fair { background: #d1ecf1; color: #0c5460; }
.grade.mediocre { background: #fff3cd; color: #856404; }
.grade.poor { background: #f8d7da; color: #721c24; }
.building-condition { margin: 40px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
.grade-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-left: 10px; }
.grade-badge.good { background: #d4edda; color: #155724; }
.grade-badge.fair { background: #d1ecf1; color: #0c5460; }
.grade-badge.mediocre { background: #fff3cd; color: #856404; }
.grade-badge.poor { background: #f8d7da; color: #721c24; }
.elements-detail { width: 100%; border-collapse: collapse; margin: 15px 0; }
.elements-detail th { background: #2c3e50; color: white; padding: 10px; text-align: left; }
.elements-detail td { padding: 8px; border-bottom: 1px solid #ddd; }
.row-critical { background: #fff5f5; }
.row-high { background: #fffef5; }
.condition-bar { height: 6px; border-radius: 3px; margin-top: 4px; }
.condition-bar.good { background: #28a745; }
.condition-bar.fair { background: #17a2b8; }
.condition-bar.mediocre { background: #ffc107; }
.condition-bar.poor { background: #dc3545; }
.amount { text-align: right; font-family: monospace; }
.center { text-align: center; }
.notes { font-size: 12px; color: #666; font-style: italic; }
.recommendations { margin: 30px 0; }
.recommendations h3 { color: #2c3e50; margin-top: 20px; }
.recommendations ul { line-height: 1.8; }
</style>',
'condition_assessment',
1,
NOW(),
NOW()
);

-- 8. KUNDE-SPECIFIK: Præsentation Format
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Præsentations Rapport (Kunde)',
'<div class="presentation-report">
  <!-- Slide 1: Forside -->
  <div class="slide slide-cover">
    <h1>{{project.name}}</h1>
    <h2>Due Diligence Præsentation</h2>
    <div class="cover-meta">
      <p>Forberedt for: {{project.customer_name}}</p>
      <p>Dato: {{project.generated_at | date}}</p>
      <p>Udarbejdet af: {{project.generated_by}}</p>
    </div>
  </div>

  <!-- Slide 2: Agenda -->
  <div class="slide">
    <h2>Agenda</h2>
    <ol class="agenda">
      <li>Executive Summary</li>
      <li>Projekt Overview</li>
      <li>Nøglefund</li>
      <li>Budget & Økonomi</li>
      <li>Risikovurdering</li>
      <li>Anbefalinger</li>
      <li>Næste Skridt</li>
    </ol>
  </div>

  <!-- Slide 3: Executive Summary -->
  <div class="slide">
    <h2>Executive Summary</h2>

    <div class="key-metrics-slide">
      <div class="metric-box">
        <div class="metric-number">{{project.building_count}}</div>
        <div class="metric-label">Bygninger</div>
      </div>

      <div class="metric-box">
        <div class="metric-number">{{project.element_count}}</div>
        <div class="metric-label">Elementer</div>
      </div>

      <div class="metric-box {{if project.critical_count > 0}}highlight{{endif}}">
        <div class="metric-number">{{project.critical_count}}</div>
        <div class="metric-label">Kritiske</div>
      </div>

      <div class="metric-box">
        <div class="metric-number">{{project.total_capex | currency}}</div>
        <div class="metric-label">CAPEX</div>
      </div>
    </div>
  </div>

  <!-- Slide 4: Projekt Overview -->
  <div class="slide">
    <h2>Projekt Overview</h2>

    <p class="slide-text">
      Analyse af <strong>{{project.name}}</strong> omfatter {{project.building_count}} bygninger
      med i alt {{project.element_count}} registrerede elementer.
    </p>

    <div class="overview-stats">
      {{if project.critical_count > 0}}
      <div class="stat-item critical">
        <span class="stat-icon">⚠️</span>
        <span class="stat-text">{{project.critical_count}} elementer kræver øjeblikkelig handling</span>
      </div>
      {{endif}}

      {{if project.high_count > 0}}
      <div class="stat-item high">
        <span class="stat-icon">🔶</span>
        <span class="stat-text">{{project.high_count}} elementer med høj prioritet</span>
      </div>
      {{endif}}

      <div class="stat-item">
        <span class="stat-icon">💰</span>
        <span class="stat-text">Estimeret investering: {{project.total_capex | currency}}</span>
      </div>
    </div>
  </div>

  <!-- Slide 5: Nøglefund -->
  <div class="slide">
    <h2>Nøglefund</h2>

    <div class="findings">
      {{if project.critical_count > 10}}
      <div class="finding finding-critical">
        <h3>🔴 Kritisk Opmærksomhed Påkrævet</h3>
        <p>{{project.critical_count}} elementer med kritisk prioritet identificeret</p>
        <p class="finding-detail">Øjeblikkelig handling anbefales for at undgå risici</p>
      </div>
      {{else if project.critical_count > 0}}
      <div class="finding finding-moderate">
        <h3>🟡 Moderat Opmærksomhed</h3>
        <p>{{project.critical_count}} elementer kræver planlægning</p>
        <p class="finding-detail">Håndtering anbefales inden for 6-12 måneder</p>
      </div>
      {{else}}
      <div class="finding finding-good">
        <h3>🟢 God Tilstand</h3>
        <p>Ingen kritiske problemer identificeret</p>
        <p class="finding-detail">Fortsæt med normal vedligeholdelse</p>
      </div>
      {{endif}}

      <div class="finding">
        <h3>📊 Budget Estimat</h3>
        <p>Total CAPEX: {{project.total_capex | currency}}</p>
        {{if project.critical_count > 0}}
        <p class="finding-detail">Umiddelbar allokering anbefales: 30-40% af total</p>
        {{endif}}
      </div>
    </div>
  </div>

  <!-- Slide 6: Bygninger -->
  <div class="slide">
    <h2>Bygnings Fordeling</h2>

    <table class="buildings-table">
      <thead>
        <tr>
          <th>Bygning</th>
          <th>Elementer</th>
          <th>Kritiske</th>
          <th>CAPEX</th>
        </tr>
      </thead>
      <tbody>
        {{for building in buildings}}
        <tr {{if building.critical_count > 0}}class="row-attention"{{endif}}>
          <td><strong>{{building.building_name}}</strong></td>
          <td>{{building.element_count}}</td>
          <td class="{{if building.critical_count > 0}}critical-cell{{endif}}">
            {{building.critical_count}}
          </td>
          <td class="amount">{{building.total_capex | currency}}</td>
        </tr>
        {{endfor}}
      </tbody>
    </table>
  </div>

  <!-- Slide 7: Risikovurdering -->
  <div class="slide">
    <h2>Risikovurdering</h2>

    <div class="risk-matrix">
      {{if project.critical_count > 10}}
      <div class="risk-level high">
        <h3>HØJ RISIKO</h3>
        <p>Omfattende kritiske elementer kræver øjeblikkelig handling</p>
      </div>
      {{else if project.critical_count > 3}}
      <div class="risk-level medium">
        <h3>MODERAT RISIKO</h3>
        <p>Flere kritiske elementer kræver planlægning</p>
      </div>
      {{else if project.critical_count > 0}}
      <div class="risk-level low-medium">
        <h3>LAV-MODERAT RISIKO</h3>
        <p>Enkelte kritiske elementer - håndterbart</p>
      </div>
      {{else}}
      <div class="risk-level low">
        <h3>LAV RISIKO</h3>
        <p>Ingen umiddelbare bekymringer</p>
      </div>
      {{endif}}
    </div>
  </div>

  <!-- Slide 8: Anbefalinger -->
  <div class="slide">
    <h2>Anbefalinger</h2>

    <div class="recommendations-slide">
      <div class="recommendation-box">
        <h3>Kort Sigt (0-6 måneder)</h3>
        <ul>
          {{if project.critical_count > 0}}
          <li>Håndter {{project.critical_count}} kritiske elementer</li>
          {{endif}}
          <li>Allokér budget til højeste prioriteter</li>
          <li>Gennemfør detaljerede inspektioner</li>
        </ul>
      </div>

      <div class="recommendation-box">
        <h3>Mellem Sigt (6-18 måneder)</h3>
        <ul>
          <li>Implementer vedligeholdelsesprogram</li>
          <li>Prioriter øvrige høj-prioritet elementer</li>
          <li>Etabler monitoring system</li>
        </ul>
      </div>

      <div class="recommendation-box">
        <h3>Lang Sigt (18+ måneder)</h3>
        <ul>
          <li>Moderniseringsprojekter</li>
          <li>Gennemfør ny vurdering</li>
          <li>Evaluer energieffektivitet</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Slide 9: Næste Skridt -->
  <div class="slide">
    <h2>Næste Skridt</h2>

    <ol class="next-steps-list">
      <li>
        <strong>Review</strong>
        <p>Gennemgå findings med stakeholders</p>
      </li>

      <li>
        <strong>Prioriter</strong>
        <p>Beslut budget allokering baseret på findings</p>
      </li>

      <li>
        <strong>Planlæg</strong>
        <p>Etabler timeline for implementering</p>
      </li>

      <li>
        <strong>Implementer</strong>
        <p>Påbegynd arbejde på kritiske elementer</p>
      </li>

      <li>
        <strong>Følg op</strong>
        <p>Planlæg opfølgende inspektioner</p>
      </li>
    </ol>
  </div>

  <!-- Slide 10: Afslutning -->
  <div class="slide slide-closing">
    <h2>Spørgsmål?</h2>

    <div class="closing-content">
      <p class="closing-text">Tak for opmærksomheden</p>

      <div class="contact-info">
        <p><strong>Kontakt:</strong></p>
        <p>{{project.generated_by}}</p>
        <p>{{project.customer_name}}</p>
      </div>

      <p class="closing-note">For detaljeret information, se venligst den komplette rapport</p>
    </div>
  </div>
</div>

<style>
.presentation-report { font-family: "Segoe UI", sans-serif; }
.slide { page-break-after: always; min-height: 100vh; padding: 60px; display: flex; flex-direction: column; }
.slide-cover { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; justify-content: center; text-align: center; }
.slide-cover h1 { font-size: 48px; margin-bottom: 20px; }
.slide-cover h2 { font-size: 32px; font-weight: normal; margin-bottom: 60px; }
.cover-meta { font-size: 18px; opacity: 0.9; }
.slide h2 { font-size: 36px; margin-bottom: 30px; color: #2c3e50; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
.agenda { font-size: 24px; line-height: 2; }
.key-metrics-slide { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; margin-top: 40px; }
.metric-box { background: #f8f9fa; padding: 40px; border-radius: 12px; text-align: center; border: 2px solid #e0e0e0; }
.metric-box.highlight { background: #fff3cd; border-color: #ffc107; }
.metric-number { font-size: 48px; font-weight: bold; color: #333; margin-bottom: 10px; }
.metric-label { font-size: 16px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
.slide-text { font-size: 20px; line-height: 1.8; margin: 20px 0; }
.overview-stats { margin-top: 40px; }
.stat-item { padding: 20px; margin: 15px 0; border-left: 5px solid #667eea; background: #f8f9fa; border-radius: 4px; font-size: 18px; }
.stat-item.critical { border-color: #dc3545; background: #fff5f5; }
.stat-item.high { border-color: #ffc107; background: #fffef5; }
.stat-icon { font-size: 24px; margin-right: 15px; }
.findings { display: grid; gap: 20px; margin-top: 20px; }
.finding { padding: 25px; border-radius: 8px; border-left: 5px solid #667eea; background: #f8f9fa; }
.finding h3 { margin-top: 0; font-size: 22px; }
.finding-critical { border-color: #dc3545; background: #fff5f5; }
.finding-moderate { border-color: #ffc107; background: #fffef5; }
.finding-good { border-color: #28a745; background: #f0fff4; }
.finding-detail { font-size: 14px; color: #666; margin-top: 10px; }
.buildings-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 18px; }
.buildings-table th { background: #667eea; color: white; padding: 15px; text-align: left; }
.buildings-table td { padding: 12px; border-bottom: 1px solid #ddd; }
.row-attention { background: #fff8e1; }
.critical-cell { color: #dc3545; font-weight: bold; }
.risk-matrix { display: flex; justify-content: center; align-items: center; min-height: 300px; }
.risk-level { padding: 60px; text-align: center; border-radius: 12px; }
.risk-level h3 { font-size: 36px; margin-bottom: 20px; }
.risk-level.high { background: #f8d7da; color: #721c24; border: 3px solid #dc3545; }
.risk-level.medium { background: #fff3cd; color: #856404; border: 3px solid #ffc107; }
.risk-level.low-medium { background: #d1ecf1; color: #0c5460; border: 3px solid #17a2b8; }
.risk-level.low { background: #d4edda; color: #155724; border: 3px solid #28a745; }
.recommendations-slide { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
.recommendation-box { background: #f8f9fa; padding: 25px; border-radius: 8px; }
.recommendation-box h3 { font-size: 18px; color: #667eea; margin-top: 0; }
.recommendation-box ul { font-size: 14px; line-height: 1.8; }
.next-steps-list { font-size: 20px; line-height: 2.5; }
.next-steps-list li { margin-bottom: 20px; }
.next-steps-list strong { color: #667eea; font-size: 24px; display: block; }
.next-steps-list p { margin-top: 5px; color: #666; font-size: 16px; }
.slide-closing { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; justify-content: center; text-align: center; }
.closing-content { margin-top: 40px; }
.closing-text { font-size: 32px; margin-bottom: 60px; }
.contact-info { font-size: 18px; line-height: 1.8; margin: 40px 0; }
.closing-note { font-size: 14px; opacity: 0.8; margin-top: 60px; }
</style>',
'executive_summary',
1,
NOW(),
NOW()
);
