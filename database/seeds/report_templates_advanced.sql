-- Advanced Report Template Seeds
-- Demonstrerer nye template funktioner: nullish coalescing (??), ternary (?:), og flags highlighting

-- 9. AVANCERET: Template med Nullish Coalescing og Ternary Operators
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Avanceret Template - Ny Syntaks',
'<div class="advanced-template">
  <h2>{{project.name}}</h2>

  <!-- Nullish Coalescing Examples -->
  <div class="project-info">
    <h3>Projekt Information</h3>
    <table>
      <tr>
        <td>Beskrivelse:</td>
        <td>{{project.description??Ingen beskrivelse}}</td>
      </tr>
      <tr>
        <td>Reference:</td>
        <td>{{project.reference_number??N/A}}</td>
      </tr>
      <tr>
        <td>CAPEX:</td>
        <td>{{project.total_capex??0 | currency}}</td>
      </tr>
      <tr>
        <td>Budget:</td>
        <td>{{project.budget??Ikke fastsat}}</td>
      </tr>
    </table>
  </div>

  <!-- Ternary Operator Examples -->
  <div class="risk-assessment">
    <h3>Risikovurdering</h3>

    <!-- Simple ternary -->
    <p><strong>Status:</strong> {{project.critical_count > 0?⚠️ Opmærksomhed påkrævet:✓ Alt OK}}</p>

    <!-- Complex ternary with comparison -->
    <p><strong>Prioritet:</strong> {{project.critical_count > 10?HØJTESKAL KRITISK:project.critical_count > 5?MODERAT:LAV}}</p>

    <!-- Ternary with truthy check -->
    <p><strong>Har Beskrivelse:</strong> {{project.description?Ja:Nej}}</p>

    <div class="risk-badge {{project.critical_count > 10?badge-critical:project.critical_count > 0?badge-warning:badge-success}}">
      {{project.critical_count??0}} kritiske elementer
    </div>
  </div>

  <!-- Element Loop with New Flags Syntax -->
  <div class="elements-section">
    <h3>Elementer med Flag Highlighting</h3>

    <table class="elements-table">
      <thead>
        <tr>
          <th>Element</th>
          <th>Tilstand</th>
          <th>CAPEX</th>
          <th>Flags (Rød Highlighted)</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        {{for element in elements}}
        <tr>
          <td>{{element.element_name??Unavngivet}}</td>
          <td>{{element.condition_score??0}}/10</td>
          <td>{{element.capex??0 | currency}}</td>
          <!-- New syntax: element.flags (red) highlights the red flag -->
          <td>{{element.flags (red)}}</td>
          <td>{{element.urgency == "critical"?🔴 KRITISK:element.urgency == "high"?🟡 HØJ:🟢 NORMAL}}</td>
        </tr>
        {{endfor}}
      </tbody>
    </table>
  </div>

  <!-- Building Loop with Ternary and Nullish Coalescing -->
  <div class="buildings-section">
    <h3>Bygninger</h3>

    {{for building in buildings}}
    <div class="building-card">
      <h4>{{building.building_name??Unavngiven bygning}}</h4>

      <div class="building-stats">
        <div class="stat">
          <span class="label">Areal:</span>
          <span class="value">{{building.gross_area??0 | number}} m²</span>
        </div>

        <div class="stat">
          <span class="label">CAPEX:</span>
          <span class="value">{{building.total_capex??0 | currency}}</span>
        </div>

        <div class="stat">
          <span class="label">Type:</span>
          <span class="value">{{building.building_type??Ikke angivet}}</span>
        </div>

        <!-- Ternary in loop -->
        <div class="stat">
          <span class="label">Status:</span>
          <span class="value {{building.critical_count > 0?text-danger:text-success}}">
            {{building.critical_count > 0?Kræver handling:OK}}
          </span>
        </div>

        <!-- Nullish coalescing with default value in loop -->
        <div class="stat">
          <span class="label">Gennemsnitlig Tilstand:</span>
          <span class="value">{{building.avg_condition??N/A}}/10</span>
        </div>
      </div>

      <!-- Conditional with ternary -->
      {{if building.element_count > 0}}
      <p class="element-count">
        {{building.element_count}} elementer -
        {{building.critical_count > 5?Mange kritiske:building.critical_count > 0?Nogle kritiske:Ingen kritiske}}
      </p>
      {{else}}
      <p class="no-elements">Ingen elementer registreret</p>
      {{endif}}
    </div>
    {{endfor}}
  </div>
</div>

<style>
.advanced-template { font-family: Arial, sans-serif; max-width: 1000px; margin: auto; padding: 20px; }
.advanced-template h2 { font-size: 32px; color: #1f2937; margin-bottom: 30px; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
.advanced-template h3 { font-size: 22px; color: #374151; margin: 30px 0 15px; }
.advanced-template h4 { font-size: 18px; color: #4b5563; margin: 0 0 12px; }

.project-info table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.project-info td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
.project-info td:first-child { font-weight: 600; width: 200px; color: #6b7280; }

.risk-assessment { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
.risk-badge { display: inline-block; padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-top: 10px; }
.badge-critical { background: #fee; color: #dc2626; border: 2px solid #dc2626; }
.badge-warning { background: #fff3cd; color: #f59e0b; border: 2px solid #f59e0b; }
.badge-success { background: #d4edda; color: #059669; border: 2px solid #059669; }

.elements-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.elements-table th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
.elements-table td { padding: 10px; border-bottom: 1px solid #e5e7eb; }

.buildings-section { margin: 30px 0; }
.building-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 15px 0; }
.building-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
.stat { display: flex; flex-direction: column; gap: 4px; }
.stat .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
.stat .value { font-size: 16px; color: #1f2937; font-weight: 600; }
.text-danger { color: #dc2626 !important; }
.text-success { color: #059669 !important; }
.element-count { margin-top: 12px; font-size: 14px; color: #6b7280; }
.no-elements { margin-top: 12px; font-size: 14px; color: #9ca3af; font-style: italic; }
</style>',
'custom',
1,
NOW(),
NOW()
);

-- 10. DEMO: Alle Nye Funktioner i Én Template
INSERT INTO report_templates (name, template_content, report_type, created_by_user_id, created_at, updated_at) VALUES (
'Komplet Demo - Alle Nye Features',
'<div class="feature-demo">
  <h2>🚀 Komplet Demo af Nye Template Features</h2>

  <!-- Section 1: Nullish Coalescing -->
  <div class="demo-section">
    <h3>1. Nullish Coalescing Operator (??)</h3>
    <p>Returnerer default værdi hvis variable er null, undefined eller tom.</p>

    <div class="examples">
      <h4>Eksempler:</h4>
      <table class="demo-table">
        <thead>
          <tr>
            <th>Syntaks</th>
            <th>Resultat</th>
            <th>Forklaring</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>{{lb}}{{lb}}project.description??Ingen beskrivelse{{rb}}{{rb}}</code></td>
            <td>{{project.description??Ingen beskrivelse}}</td>
            <td>Viser beskrivelse eller "Ingen beskrivelse" hvis tom</td>
          </tr>
          <tr>
            <td><code>{{lb}}{{lb}}project.total_capex??0{{rb}}{{rb}}</code></td>
            <td>{{project.total_capex??0}}</td>
            <td>Viser CAPEX eller 0 hvis ikke sat</td>
          </tr>
          <tr>
            <td><code>{{lb}}{{lb}}project.budget??Ikke fastsat{{rb}}{{rb}}</code></td>
            <td>{{project.budget??Ikke fastsat}}</td>
            <td>Viser budget eller "Ikke fastsat"</td>
          </tr>
          <tr>
            <td><code>{{lb}}{{lb}}project.reference_number??N/A{{rb}}{{rb}}</code></td>
            <td>{{project.reference_number??N/A}}</td>
            <td>Viser reference eller "N/A"</td>
          </tr>
        </tbody>
      </table>

      <div class="note">
        <strong>💡 Tip:</strong> Brug ?? til at sikre at templates aldrig viser tomme felter.
        Det giver fejlhåndtering uden at templates crasher!
      </div>
    </div>
  </div>

  <!-- Section 2: Ternary Operator -->
  <div class="demo-section">
    <h3>2. Ternary Operator (?:)</h3>
    <p>Inline if/else logik - hvis betingelse er sand, vis første værdi, ellers anden værdi.</p>

    <div class="examples">
      <h4>Eksempler:</h4>
      <table class="demo-table">
        <thead>
          <tr>
            <th>Syntaks</th>
            <th>Resultat</th>
            <th>Forklaring</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>{{lb}}{{lb}}project.critical_count > 0?Ja:Nej{{rb}}{{rb}}</code></td>
            <td>{{project.critical_count > 0?Ja:Nej}}</td>
            <td>Simpel sand/falsk check</td>
          </tr>
          <tr>
            <td><code>{{lb}}{{lb}}project.critical_count > 10?KRITISK:OK{{rb}}{{rb}}</code></td>
            <td>{{project.critical_count > 10?KRITISK:OK}}</td>
            <td>Check om værdi overstiger grænse</td>
          </tr>
          <tr>
            <td><code>{{lb}}{{lb}}project.critical_count > 0?⚠️ Action Required:✓ All Good{{rb}}{{rb}}</code></td>
            <td>{{project.critical_count > 0?⚠️ Action Required:✓ All Good}}</td>
            <td>Med emojis og tekst</td>
          </tr>
          <tr>
            <td><code>{{lb}}{{lb}}project.name?Har navn:Ingen navn{{rb}}{{rb}}</code></td>
            <td>{{project.name?Har navn:Ingen navn}}</td>
            <td>Truthy check (findes variabel?)</td>
          </tr>
        </tbody>
      </table>

      <div class="note">
        <strong>💡 Tip:</strong> Ternary er perfekt til at vise forskellige ikoner, farver eller tekster
        baseret på data værdier uden at skrive lange {{lb}}{{lb}}if{{rb}}{{rb}} blokke!
      </div>
    </div>
  </div>

  <!-- Section 3: Flag Display with Highlighting -->
  <div class="demo-section">
    <h3>3. Flag Display med Highlighting</h3>
    <p>Vis alle 5 flag typer og highlight en specifik farve (red, orange, yellow, green, blue).</p>

    <div class="examples">
      <h4>Loop Eksempel med Elementer:</h4>
      <table class="demo-table">
        <thead>
          <tr>
            <th>Element</th>
            <th>Tilstand</th>
            <th>Flags (Rød Highlighted)</th>
            <th>Flags (Orange Highlighted)</th>
            <th>Flags (Standard)</th>
          </tr>
        </thead>
        <tbody>
          {{for element in elements}}
          <tr>
            <td>{{element.element_name??Unavngivet}}</td>
            <td>{{element.condition_score??0}}/10</td>
            <td>{{element.flags (red)}}</td>
            <td>{{element.flags (orange)}}</td>
            <td>{{red_flags_display(element)}}</td>
          </tr>
          {{endfor}}
        </tbody>
      </table>

      <div class="note">
        <strong>💡 Syntaks:</strong>
        <ul>
          <li><code>{{lb}}{{lb}}element.flags (red){{rb}}{{rb}}</code> - Highlight rød flag</li>
          <li><code>{{lb}}{{lb}}element.flags (orange){{rb}}{{rb}}</code> - Highlight orange flag</li>
          <li><code>{{lb}}{{lb}}element.flags (yellow){{rb}}{{rb}}</code> - Highlight gul flag</li>
          <li><code>{{lb}}{{lb}}element.flags (green){{rb}}{{rb}}</code> - Highlight grøn flag</li>
          <li><code>{{lb}}{{lb}}element.flags (blue){{rb}}{{rb}}</code> - Highlight blå flag</li>
          <li><code>{{lb}}{{lb}}red_flags_display(element){{rb}}{{rb}}</code> - Standard (highlight valgt score)</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Section 4: Combined Usage -->
  <div class="demo-section">
    <h3>4. Kombineret Brug</h3>
    <p>Kombiner alle features for maksimal fleksibilitet!</p>

    <div class="examples">
      <h4>Bygnings Eksempel:</h4>
      {{for building in buildings}}
      <div class="building-demo-card">
        <h4>{{building.building_name??Unavngiven Bygning}}</h4>

        <div class="metrics">
          <!-- Nullish coalescing + pipe filter -->
          <div class="metric">
            <span class="label">CAPEX:</span>
            <span class="value">{{building.total_capex??0 | currency}}</span>
          </div>

          <!-- Ternary operator med comparison -->
          <div class="metric">
            <span class="label">Risk Level:</span>
            <span class="value {{building.critical_count > 5?risk-high:building.critical_count > 0?risk-medium:risk-low}}">
              {{building.critical_count > 5?HØJ RISIKO:building.critical_count > 0?MODERAT RISIKO:LAV RISIKO}}
            </span>
          </div>

          <!-- Nullish coalescing in loop -->
          <div class="metric">
            <span class="label">Type:</span>
            <span class="value">{{building.building_type??Ikke angivet}}</span>
          </div>

          <!-- Ternary + nullish combined -->
          <div class="metric">
            <span class="label">Tilstand:</span>
            <span class="value">
              {{building.avg_condition??0 > 7?God:building.avg_condition??0 > 4?Acceptabel:Dårlig}}
              ({{building.avg_condition??N/A}}/10)
            </span>
          </div>
        </div>
      </div>
      {{endfor}}

      <div class="note success">
        <strong>✅ Fordele ved de nye features:</strong>
        <ul>
          <li><strong>Fejlsikker:</strong> Ingen tomme felter eller fejl ved manglende data</li>
          <li><strong>Kortere kod:</strong> Mindre behov for lange {{lb}}{{lb}}if{{rb}}{{rb}} statements</li>
          <li><strong>Mere læsbar:</strong> Inline logik er lettere at forstå</li>
          <li><strong>Fleksibel:</strong> Kombiner med eksisterende features (loops, filters, etc.)</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Section 5: Best Practices -->
  <div class="demo-section">
    <h3>5. Best Practices</h3>

    <div class="best-practices">
      <div class="practice">
        <h4>✅ Gør:</h4>
        <ul>
          <li>Brug <code>??</code> til at give default værdier for alle optional felter</li>
          <li>Brug ternary <code>?:</code> til simple if/else checks</li>
          <li>Kombiner med pipe filters for formatering</li>
          <li>Brug <code>element.flags (color)</code> til at highlighte specifikke flag typer</li>
        </ul>
      </div>

      <div class="practice dont">
        <h4>❌ Undgå:</h4>
        <ul>
          <li>Komplekse nestede ternary operatorer (brug {{lb}}{{lb}}if{{rb}}{{rb}} blocks istedet)</li>
          <li>At lade felter være tomme uden ?? fallback</li>
          <li>For lange ternary expressions (hold dem simple)</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<style>
.feature-demo { font-family: "Segoe UI", sans-serif; max-width: 1200px; margin: auto; padding: 40px; }
.feature-demo h2 { font-size: 36px; color: #1f2937; margin-bottom: 30px; text-align: center; }
.feature-demo h3 { font-size: 24px; color: #667eea; margin: 40px 0 15px; border-bottom: 2px solid #667eea; padding-bottom: 8px; }
.feature-demo h4 { font-size: 18px; color: #4b5563; margin: 20px 0 10px; }
.feature-demo p { line-height: 1.6; color: #6b7280; margin-bottom: 15px; }

.demo-section { background: #ffffff; padding: 30px; margin: 30px 0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.examples { margin: 20px 0; }

.demo-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.demo-table th { background: #667eea; color: white; padding: 12px; text-align: left; font-weight: 600; }
.demo-table td { padding: 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
.demo-table code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 13px; }

.note { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 4px; }
.note.success { background: #f0fdf4; border-left-color: #10b981; }
.note strong { color: #1e40af; }
.note.success strong { color: #047857; }
.note ul { margin: 10px 0 0 20px; line-height: 1.8; }

.building-demo-card { background: #f9fafb; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #e5e7eb; }
.building-demo-card h4 { margin-top: 0; color: #1f2937; }
.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
.metric { display: flex; flex-direction: column; gap: 4px; }
.metric .label { font-size: 12px; color: #6b7280; text-transform: uppercase; }
.metric .value { font-size: 16px; font-weight: 600; color: #1f2937; }
.metric .value.risk-high { color: #dc2626; }
.metric .value.risk-medium { color: #f59e0b; }
.metric .value.risk-low { color: #059669; }

.best-practices { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
.practice { background: #f0fdf4; padding: 20px; border-radius: 8px; border: 1px solid #10b981; }
.practice.dont { background: #fef2f2; border-color: #dc2626; }
.practice h4 { margin-top: 0; color: #047857; }
.practice.dont h4 { color: #dc2626; }
.practice ul { margin: 10px 0 0 20px; line-height: 1.8; }
</style>',
'custom',
1,
NOW(),
NOW()
);

-- Note: {{lb}} and {{rb}} are placeholders for {{ and }} in the demo template
-- They should be replaced with actual curly braces when rendered
