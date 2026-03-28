# Report Template Guide v2.0

**Opdateret guide til at oprette kundespecifikke rapporter med avanceret template syntax**

---

## 🚀 Nyt i Version 2.0

- **Nullish Coalescing Operator (`??`)** - Fallback værdier uden fejl
- **Ternary Operator (`?:`)** - Inline if/else logik
- **Flag Highlighting (`element.flags (color)`)** - Highlight specifikke flag farver
- **Forbedret Fejlhåndtering** - Templates crasher ikke ved manglende data
- **Interaktiv Rapport Viewer** - Sidebar TOC navigation til web view

---

## 📚 Indholdsfortegnelse

1. [Nye Features](#nye-features)
   - [Nullish Coalescing](#nullish-coalescing-operator)
   - [Ternary Operator](#ternary-operator)
   - [Flag Highlighting](#flag-highlighting)
2. [Template Syntax](#template-syntax)
3. [Tilgængelige Variabler](#tilgængelige-variabler)
4. [Loops & Iteration](#loops--iteration)
5. [Conditionals](#conditionals)
6. [Filters](#filters)
7. [Specielle Funktioner](#specielle-funktioner)
8. [Rapport Viewer](#rapport-viewer)
9. [Eksempler](#eksempler)
10. [Best Practices](#best-practices)

---

## Nye Features

### Nullish Coalescing Operator (`??`)

**Formål:** Returnér en default værdi hvis variablen er null, undefined eller tom.

**Syntaks:**
```html
{{variable??default_value}}
```

**Eksempler:**
```html
<!-- Simpel fallback -->
{{project.description??Ingen beskrivelse}}

<!-- Numerisk fallback -->
{{project.total_capex??0}}

<!-- Med pipe filter -->
{{project.budget??0 | currency}}

<!-- I loops -->
{{for building in buildings}}
  <p>Type: {{building.building_type??Ikke angivet}}</p>
{{endfor}}
```

**Use Cases:**
- Undgå tomme felter i rapporter
- Sikre at numeriske felter altid har en værdi
- Lave fejlsikre templates

**Fordele:**
- ✅ Ingen fejl ved manglende data
- ✅ Kortere end {{if}} statements
- ✅ Fungerer i loops og conditionals
- ✅ Kan kombineres med filters

---

### Ternary Operator (`?:`)

**Formål:** Inline if/else - hvis betingelse er sand, vis første værdi, ellers anden værdi.

**Syntaks:**
```html
<!-- Simpel truthy check -->
{{variable?true_value:false_value}}

<!-- Med comparison operator -->
{{variable > value?true_value:false_value}}
{{variable < value?true_value:false_value}}
{{variable == value?true_value:false_value}}
{{variable != value?true_value:false_value}}
```

**Eksempler:**

**1. Simpel Sand/Falsk Check:**
```html
{{project.critical_count > 0?Ja:Nej}}
{{project.critical_count > 0?⚠️ Action Required:✓ All Good}}
```

**2. Numeriske Comparisons:**
```html
<!-- Greater than -->
{{project.critical_count > 10?KRITISK:OK}}

<!-- Less than -->
{{project.element_count < 50?Lille projekt:Stort projekt}}

<!-- Equals -->
{{building.building_type == "residential"?Bolig:Erhverv}}
```

**3. Truthy Check (findes variablen?):**
```html
{{project.description?Har beskrivelse:Ingen beskrivelse}}
{{project.name?Named Project:Unavngivet}}
```

**4. I Loops:**
```html
{{for element in elements}}
  <td class="{{element.urgency == "critical"?bg-red:bg-green}}">
    {{element.urgency == "critical"?🔴 KRITISK:🟢 OK}}
  </td>
{{endfor}}
```

**5. I CSS Classes:**
```html
<div class="badge {{project.critical_count > 10?badge-danger:badge-success}}">
  {{project.critical_count??0}} kritiske
</div>
```

**6. Nestede Ternary (brug sparsomt):**
```html
{{project.critical_count > 10?HØJTESKAL KRITISK:project.critical_count > 5?MODERAT:LAV}}
```

**Supported Operators:**
- `>` - Større end
- `<` - Mindre end
- `>=` - Større end eller lig med
- `<=` - Mindre end eller lig med
- `==` - Lig med
- `!=` - Ikke lig med

**Fordele:**
- ✅ Kortere end {{if}}...{{else}}...{{endif}}
- ✅ Perfekt til simple checks
- ✅ Kan bruges i CSS classes og attributes
- ✅ Kombineres med emojis og symboler

**Undgå:**
- ❌ Meget komplekse nestede ternaries (brug {{if}} istedet)
- ❌ For lange expressions (hold dem simple og læsbare)

---

### Flag Highlighting

**Formål:** Vis alle 5 flag typer og highlight en specifik farve.

**Alle 5 Flag Typer:**
1. 🚩 **Kritisk** (Rød - `red`)
2. ⚠️ **Alvorlig** (Orange - `orange`)
3. ⚡ **Moderat** (Gul - `yellow`)
4. ℹ️ **Mindre** (Grøn - `green`)
5. 💡 **Info** (Blå - `blue`)

**Syntaks:**
```html
<!-- Ny syntaks: Highlight specifik farve -->
{{element.flags (red)}}      <!-- Highlight rød flag -->
{{element.flags (orange)}}   <!-- Highlight orange flag -->
{{element.flags (yellow)}}   <!-- Highlight gul flag -->
{{element.flags (green)}}    <!-- Highlight grøn flag -->
{{element.flags (blue)}}     <!-- Highlight blå flag -->

<!-- Legacy syntaks: Highlight baseret på element's score -->
{{red_flags_display(element)}}
```

**Eksempel i Loop:**
```html
<table>
  <thead>
    <tr>
      <th>Element</th>
      <th>Flags (Kritiske Highlighted)</th>
      <th>Flags (Standard)</th>
    </tr>
  </thead>
  <tbody>
    {{for element in elements}}
    <tr>
      <td>{{element.element_name}}</td>
      <td>{{element.flags (red)}}</td>
      <td>{{red_flags_display(element)}}</td>
    </tr>
    {{endfor}}
  </tbody>
</table>
```

**Visuel Output:**
- **Active flag** (baseret på element's red_flag_score): Bold, tykkere border, colored background
- **Highlighted flag** (requested color): Extra tyk border (3px), mere opacity
- **Inactive flags**: Faded (20% opacity), grå border

**Use Cases:**
- Kunde-specifike rapporter hvor kun visse flag typer er relevante
- Fokus på specifikke risiko niveauer
- Sammenligning mellem forskellige flag typer

---

## Template Syntax

### Basis Variabler

```html
{{variable_name}}
```

**Med Fejlhåndtering:**
```html
<!-- Uden fallback (kan være tom) -->
{{project.name}}

<!-- Med fallback (fejlsikker) -->
{{project.name??Unavngivet Projekt}}
```

---

## Tilgængelige Variabler

### Project Variabler

```html
{{project.name}}              <!-- Projekt navn -->
{{project.description}}        <!-- Projekt beskrivelse -->
{{project.customer_name}}      <!-- Kunde navn -->
{{project.building_count}}     <!-- Antal bygninger -->
{{project.element_count}}      <!-- Antal elementer -->
{{project.total_capex}}        <!-- Total CAPEX -->
{{project.critical_count}}     <!-- Kritiske elementer -->
{{project.high_count}}         <!-- Høj prioritet elementer -->
{{project.poor_count}}         <!-- Elementer i dårlig tilstand -->
{{project.created_at}}         <!-- Oprettelsesdato -->
{{project.generated_at}}       <!-- Rapport genereringsdato -->
{{project.generated_by}}       <!-- Genereret af (bruger navn) -->

<!-- Med fejlhåndtering -->
{{project.description??Ingen beskrivelse tilgængelig}}
{{project.budget??Ikke fastsat}}
{{project.reference_number??N/A}}
```

### Building Variabler (i loops)

```html
{{building.building_id}}       <!-- Bygning ID -->
{{building.building_name}}     <!-- Bygning navn -->
{{building.building_number}}   <!-- Bygning nummer -->
{{building.building_type}}     <!-- Bygning type -->
{{building.gross_area}}        <!-- Bruttoareal (m²) -->
{{building.element_count}}     <!-- Antal elementer -->
{{building.total_capex}}       <!-- Total CAPEX for bygning -->
{{building.avg_condition}}     <!-- Gennemsnitlig tilstandsscore -->
{{building.critical_count}}    <!-- Kritiske elementer i bygning -->
{{building.high_count}}        <!-- Høj prioritet i bygning -->

<!-- Med fejlhåndtering -->
{{building.building_type??Ikke angivet}}
{{building.avg_condition??N/A}}
```

### Element Variabler (i loops)

```html
{{element.element_id}}         <!-- Element ID -->
{{element.element_name}}       <!-- Element navn -->
{{element.element_code}}       <!-- Element kode -->
{{element.parent_id}}          <!-- Parent element ID (for hierarki) -->
{{element.capex}}              <!-- CAPEX for element -->
{{element.urgency}}            <!-- Prioritet (critical, high, medium, low) -->
{{element.condition_score}}    <!-- Tilstandsscore (0-10) -->
{{element.red_flag_score}}     <!-- Rød flag score (1-5) -->
{{element.severity}}           <!-- Alvorlighed -->

<!-- Med fejlhåndtering -->
{{element.element_name??Unavngivet Element}}
{{element.capex??0}}
{{element.condition_score??0}}
```

### Red Flag Variabler (i loops)

```html
{{flag.element_name}}          <!-- Element navn -->
{{flag.building_name}}         <!-- Bygning navn -->
{{flag.red_flag_score}}        <!-- Score (1-5) -->
{{flag.severity}}              <!-- Alvorlighed -->
{{flag.capex}}                 <!-- CAPEX -->
{{flag.urgency}}               <!-- Prioritet -->
{{flag.condition_score}}       <!-- Tilstandsscore -->
```

---

## Loops & Iteration

### For Loop Syntax

```html
{{for item in collection}}
  <!-- Content -->
{{endfor}}
```

### Tilgængelige Collections

**1. Buildings Loop:**
```html
{{for building in buildings}}
  <h3>{{building.building_name??Unavngiven Bygning}}</h3>
  <p>CAPEX: {{building.total_capex??0 | currency}}</p>
  <p>Status: {{building.critical_count > 0?⚠️ Kræver handling:✓ OK}}</p>
{{endfor}}
```

**2. Elements Loop:**
```html
{{for element in elements}}
  <tr>
    <td>{{element.element_name??N/A}}</td>
    <td>{{element.flags (red)}}</td>
    <td>{{element.urgency == "critical"?🔴:🟢}}</td>
  </tr>
{{endfor}}
```

**3. Red Flags Loop:**
```html
{{for flag in red_flags}}
  <div class="flag-item">
    <strong>{{flag.element_name}}</strong>
    <span>Score: {{flag.red_flag_score??0}}/5</span>
  </div>
{{endfor}}
```

### Nestede Loops

```html
{{for building in buildings}}
  <h3>{{building.building_name}}</h3>

  <table>
    {{for element in elements}}
    {{if element.building_id == building.building_id}}
    <tr>
      <td>{{element.element_name??N/A}}</td>
      <td>{{element.capex??0 | currency}}</td>
      <td>{{element.flags (red)}}</td>
    </tr>
    {{endif}}
    {{endfor}}
  </table>
{{endfor}}
```

---

## Conditionals

### If/Else Syntax

```html
{{if condition}}
  <!-- Content if true -->
{{endif}}

{{if condition}}
  <!-- Content if true -->
{{else}}
  <!-- Content if false -->
{{endif}}

{{if condition1}}
  <!-- Content if condition1 -->
{{else if condition2}}
  <!-- Content if condition2 -->
{{else}}
  <!-- Content if neither -->
{{endif}}
```

### Conditions with Operators

```html
<!-- Greater than -->
{{if project.critical_count > 10}}
  <p>HØJTESKAL KRITISK prioritet</p>
{{endif}}

<!-- Less than -->
{{if project.element_count < 50}}
  <p>Lille projekt</p>
{{endif}}

<!-- Equals -->
{{if project.critical_count == 0}}
  <p>Ingen kritiske elementer</p>
{{endif}}

<!-- Not equals -->
{{if project.building_count != 1}}
  <p>Flere bygninger</p>
{{endif}}

<!-- Greater than or equal -->
{{if building.avg_condition >= 8}}
  <p>God tilstand</p>
{{endif}}
```

### Nested Conditionals

```html
{{if project.critical_count > 0}}
  {{if project.critical_count > 10}}
    <div class="alert-critical">Meget kritisk!</div>
  {{else}}
    <div class="alert-warning">Moderat kritisk</div>
  {{endif}}
{{else}}
  <div class="alert-success">Alt OK</div>
{{endif}}
```

### Conditionals vs Ternary

**Brug {{if}} når:**
- Blokken indeholder meget HTML
- Du har kompleks logik
- Du har mere end 2 branches

**Brug Ternary når:**
- Simpel true/false check
- Kort inline tekst eller værdi
- CSS classes eller attributes

**Eksempel - {{if}}:**
```html
{{if project.critical_count > 0}}
  <div class="alert alert-danger">
    <h4>OBS! Kritiske Elementer</h4>
    <p>Der er {{project.critical_count}} kritiske elementer.</p>
    <ul>
      <li>Gennemgå alle kritiske elementer</li>
      <li>Prioriter budgetallokering</li>
      <li>Planlæg opfølgende inspektioner</li>
    </ul>
  </div>
{{endif}}
```

**Eksempel - Ternary:**
```html
<span class="badge {{project.critical_count > 0?badge-danger:badge-success}}">
  {{project.critical_count > 0?⚠️ Action Required:✓ All Good}}
</span>
```

---

## Filters

### Pipe Filter Syntax

```html
{{variable | filter}}
{{variable | filter:parameter}}
```

### Tilgængelige Filters

#### 1. Number Filter
**Formatér tal med tusinde-separatorer**

```html
{{project.total_capex | number}}
<!-- Output: 1.234.567 -->

{{building.gross_area | number}}
<!-- Output: 2.500 -->
```

#### 2. Currency Filter
**Formatér som DKK valuta**

```html
{{project.total_capex | currency}}
<!-- Output: kr. 1.234.567 -->

{{element.capex | currency}}
<!-- Output: kr. 50.000 -->

<!-- Med nullish coalescing -->
{{project.budget??0 | currency}}
<!-- Output: kr. 0 (hvis budget er null) -->
```

#### 3. Date Filter
**Formatér dato som dd-mm-yyyy**

```html
{{project.created_at | date}}
<!-- Output: 15-01-2024 -->

{{project.generated_at | date}}
<!-- Output: 21-01-2026 -->
```

#### 4. Text Filters

**Uppercase:**
```html
{{project.name | uppercase}}
<!-- Output: PROJEKT NAVN -->
```

**Lowercase:**
```html
{{project.name | lowercase}}
<!-- Output: projekt navn -->
```

**Capitalize:**
```html
{{element.urgency | capitalize}}
<!-- Output: Critical -->
```

#### 5. Truncate Filter
**Afkort tekst til N tegn**

```html
{{project.description | truncate:100}}
<!-- Output: First 100 characters... -->

{{project.description | truncate:50}}
<!-- Output: First 50 characters... -->
```

### Kombinere Filters med Andre Features

**Med Nullish Coalescing:**
```html
{{project.total_capex??0 | currency}}
{{building.gross_area??0 | number}}
{{project.created_at??today | date}}
```

**I Loops:**
```html
{{for element in elements}}
  <td>{{element.capex??0 | currency}}</td>
  <td>{{element.element_name??N/A | truncate:30}}</td>
{{endfor}}
```

**I Conditionals:**
```html
{{if project.total_capex > 1000000}}
  <p>Stort budget: {{project.total_capex | currency}}</p>
{{endif}}
```

---

## Specielle Funktioner

### Red Flags Display

**Legacy Syntaks:**
```html
{{red_flags_display(element)}}
```

**Ny Syntaks med Highlighting:**
```html
{{element.flags (red)}}      <!-- Highlight kritisk (rød) -->
{{element.flags (orange)}}   <!-- Highlight alvorlig (orange) -->
{{element.flags (yellow)}}   <!-- Highlight moderat (gul) -->
{{element.flags (green)}}    <!-- Highlight mindre (grøn) -->
{{element.flags (blue)}}     <!-- Highlight info (blå) -->
```

**Komplet Eksempel:**
```html
<table class="elements-table">
  <thead>
    <tr>
      <th>Element</th>
      <th>Tilstand</th>
      <th>Flags (Kritiske)</th>
      <th>Flags (Standard)</th>
    </tr>
  </thead>
  <tbody>
    {{for element in elements}}
    <tr>
      <td>{{element.element_name??Unavngivet}}</td>
      <td>{{element.condition_score??0}}/10</td>
      <td>{{element.flags (red)}}</td>
      <td>{{red_flags_display(element)}}</td>
    </tr>
    {{endfor}}
  </tbody>
</table>
```

**Output:**
- Alle 5 flag typer vises som badges
- Highlighted flag har tyk border og højere opacity
- Element's aktuelle score er også highlighted (hvis forskellig fra requested color)

---

## Rapport Viewer

### Interaktiv Sidebar TOC

**Ny feature:** Rapporter vises i en interaktiv viewer med:
- **Venstre Sidebar** med indholdsfortegnelse (TOC)
- **Scroll Progress Bar** der viser læseprogress
- **Auto-highlight** af aktiv sektion
- **Smooth Scroll** til sektioner
- **Print-friendly** (sidebar skjules ved print)
- **Responsive** (mobilvenlig)

### Sådan Bruges Vieweren

**1. Inkluder Viewer HTML:**
```html
<!-- Load viewer -->
<script src="/modules/report_builder/viewer.html"></script>
```

**2. Strukturér Rapport med H2 Headers:**
```html
<h2>Executive Summary</h2>
<p>Content...</p>

<h2>Bygningsoversigt</h2>
<p>Content...</p>

<h2>Budget & Økonomi</h2>
<p>Content...</p>
```

**3. Vieweren Genererer Automatisk:**
- TOC items fra alle `<h2>` headers
- Section IDs for navigation
- Scroll tracking og highlighting
- Progress bar

### Viewer Features

**Actions:**
- 🖨️ **Print** - Print rapport (skjuler sidebar)
- ⬆️ **Scroll to Top** - Hurtig scroll til toppen
- 📥 **Download PDF** - Download som PDF (kommer snart)

**Navigation:**
- Klik på TOC item for smooth scroll til sektion
- Auto-highlight af aktiv sektion ved scroll
- Scroll progress bar øverst

**Responsive:**
- Desktop: Sidebar altid synlig
- Tablet/Mobile: Sidebar collapses, toggle knap vises

### Elementer Udenfor Print

**Sidebar og viewer UI vises KUN i web view:**

```html
<!-- Vises i web view OG print -->
<div class="report-content">
  <h2>Section Title</h2>
  <p>Content that prints...</p>
</div>

<!-- Vises KUN i web view (auto-skjult ved print) -->
<div class="toc-sidebar">
  <!-- Auto-generated TOC -->
</div>

<div class="actions-bar">
  <!-- Print, Scroll, Download buttons -->
</div>
```

**CSS Print Hiding:**
```css
@media print {
  .toc-sidebar,
  .toc-toggle,
  .scroll-progress,
  .actions-bar {
    display: none !important;
  }
}
```

---

## Eksempler

### Eksempel 1: Fejlsikker Projekt Oversigt

```html
<div class="project-overview">
  <h2>{{project.name??Unavngivet Projekt}}</h2>

  <div class="project-meta">
    <p><strong>Kunde:</strong> {{project.customer_name??Ikke angivet}}</p>
    <p><strong>Oprettet:</strong> {{project.created_at??N/A | date}}</p>
    <p><strong>Reference:</strong> {{project.reference_number??N/A}}</p>
  </div>

  <div class="project-stats">
    <div class="stat">
      <span class="label">Bygninger:</span>
      <span class="value">{{project.building_count??0}}</span>
    </div>

    <div class="stat">
      <span class="label">Elementer:</span>
      <span class="value">{{project.element_count??0}}</span>
    </div>

    <div class="stat">
      <span class="label">CAPEX:</span>
      <span class="value">{{project.total_capex??0 | currency}}</span>
    </div>

    <div class="stat">
      <span class="label">Status:</span>
      <span class="value {{project.critical_count > 0?text-danger:text-success}}">
        {{project.critical_count > 0?⚠️ Kræver handling:✓ Alt OK}}
      </span>
    </div>
  </div>

  <!-- Risk Badge -->
  <div class="risk-badge {{project.critical_count > 10?badge-critical:project.critical_count > 0?badge-warning:badge-success}}">
    <strong>Risiko:</strong>
    {{project.critical_count > 10?HØJTESKAL:project.critical_count > 5?MODERAT:project.critical_count > 0?LAV-MODERAT:LAV}}
  </div>
</div>
```

### Eksempel 2: Element Tabel med Alle Nye Features

```html
<h2>Element Analyse</h2>

<table class="elements-table">
  <thead>
    <tr>
      <th>Element</th>
      <th>Kode</th>
      <th>Tilstand</th>
      <th>CAPEX</th>
      <th>Flags</th>
      <th>Status</th>
      <th>Handling</th>
    </tr>
  </thead>
  <tbody>
    {{for element in elements}}
    <tr class="{{element.urgency == "critical"?row-critical:element.urgency == "high"?row-warning:row-normal}}">
      <!-- Nullish coalescing for missing data -->
      <td>{{element.element_name??Unavngivet Element}}</td>
      <td><code>{{element.element_code??N/A}}</code></td>

      <!-- Nullish + filter -->
      <td>{{element.condition_score??0}}/10</td>
      <td>{{element.capex??0 | currency}}</td>

      <!-- Flag highlighting -->
      <td>{{element.flags (red)}}</td>

      <!-- Ternary operators -->
      <td>
        {{element.urgency == "critical"?🔴 KRITISK:element.urgency == "high"?🟡 HØJ:element.urgency == "medium"?🔵 MELLEM:🟢 LAV}}
      </td>

      <td>
        {{element.condition_score < 4?Udskift nu:element.condition_score < 6?Renover snart:element.condition_score < 8?Vedligehold:God stand}}
      </td>
    </tr>
    {{endfor}}
  </tbody>
</table>
```

### Eksempel 3: Bygnings Cards med Kombinerede Features

```html
<h2>Bygningsoversigt</h2>

<div class="buildings-grid">
  {{for building in buildings}}
  <div class="building-card {{building.critical_count > 5?card-critical:building.critical_count > 0?card-warning:card-success}}">
    <!-- Card Header -->
    <div class="card-header">
      <h3>{{building.building_name??Unavngiven Bygning}}</h3>
      <span class="building-number">{{building.building_number??N/A}}</span>
    </div>

    <!-- Card Body -->
    <div class="card-body">
      <div class="metrics">
        <div class="metric">
          <span class="label">Type:</span>
          <span class="value">{{building.building_type??Ikke angivet}}</span>
        </div>

        <div class="metric">
          <span class="label">Areal:</span>
          <span class="value">{{building.gross_area??0 | number}} m²</span>
        </div>

        <div class="metric">
          <span class="label">CAPEX:</span>
          <span class="value">{{building.total_capex??0 | currency}}</span>
        </div>

        <div class="metric">
          <span class="label">Tilstand:</span>
          <span class="value">
            {{building.avg_condition??N/A}}/10
            ({{building.avg_condition > 7?God:building.avg_condition > 4?Acceptabel:Dårlig}})
          </span>
        </div>
      </div>

      <!-- Risk Assessment -->
      <div class="risk-section">
        <strong>Risiko:</strong>
        <span class="risk-level {{building.critical_count > 5?risk-high:building.critical_count > 0?risk-medium:risk-low}}">
          {{building.critical_count > 5?HØJ RISIKO:building.critical_count > 0?MODERAT RISIKO:LAV RISIKO}}
        </span>
      </div>

      <!-- Element Count -->
      {{if building.element_count > 0}}
      <p class="element-summary">
        {{building.element_count}} elementer -
        {{building.critical_count > 5?Mange kritiske:building.critical_count > 0?Nogle kritiske:Ingen kritiske}}
      </p>
      {{else}}
      <p class="no-elements">Ingen elementer registreret</p>
      {{endif}}
    </div>

    <!-- Card Footer -->
    <div class="card-footer">
      <span class="critical-badge">
        {{building.critical_count??0}} kritiske
      </span>
      <span class="high-badge">
        {{building.high_count??0}} høj prioritet
      </span>
    </div>
  </div>
  {{endfor}}
</div>
```

### Eksempel 4: Komplet Rapport med Viewer Support

```html
<div class="complete-report">
  <!-- Section 1: Executive Summary -->
  <h2>Executive Summary</h2>
  <p>
    Rapport for <strong>{{project.name??Projekt}}</strong>
    udarbejdet for {{project.customer_name??kunde}}.
  </p>

  <div class="summary-stats">
    <div class="stat-card {{project.critical_count > 10?card-critical:card-normal}}">
      <div class="stat-value">{{project.critical_count??0}}</div>
      <div class="stat-label">Kritiske Elementer</div>
      <div class="stat-status">
        {{project.critical_count > 10?KRITISK NIVEAU:project.critical_count > 0?OPMÆRKSOMHED PÅKRÆVET:ALT OK}}
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-value">{{project.total_capex??0 | currency}}</div>
      <div class="stat-label">Estimeret CAPEX</div>
    </div>

    <div class="stat-card">
      <div class="stat-value">{{project.building_count??0}}</div>
      <div class="stat-label">Bygninger</div>
    </div>
  </div>

  <!-- Section 2: Bygningsoversigt -->
  <h2>Bygningsoversigt</h2>
  {{for building in buildings}}
  <div class="building-section">
    <h3>{{building.building_name??Bygning}}</h3>
    <p>CAPEX: {{building.total_capex??0 | currency}}</p>
    <p>Status: {{building.critical_count > 0?⚠️ Kræver handling:✓ OK}}</p>
  </div>
  {{endfor}}

  <!-- Section 3: Element Detaljer -->
  <h2>Element Detaljer</h2>
  <table>
    <thead>
      <tr>
        <th>Element</th>
        <th>Flags (Kritiske)</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      {{for element in elements}}
      <tr>
        <td>{{element.element_name??N/A}}</td>
        <td>{{element.flags (red)}}</td>
        <td>{{element.urgency == "critical"?KRITISK:OK}}</td>
      </tr>
      {{endfor}}
    </tbody>
  </table>

  <!-- Section 4: Anbefalinger -->
  <h2>Anbefalinger</h2>
  {{if project.critical_count > 10}}
  <div class="recommendation critical">
    <h4>Øjeblikkelig Handling Påkrævet</h4>
    <p>Med {{project.critical_count}} kritiske elementer...</p>
  </div>
  {{else if project.critical_count > 0}}
  <div class="recommendation moderate">
    <h4>Planlægning Anbefales</h4>
    <p>Håndter {{project.critical_count}} kritiske elementer...</p>
  </div>
  {{else}}
  <div class="recommendation success">
    <h4>God Tilstand</h4>
    <p>Fortsæt normal vedligeholdelse...</p>
  </div>
  {{endif}}
</div>

<!-- Vieweren genererer automatisk TOC fra h2 headers -->
```

---

## Best Practices

### 1. Fejlhåndtering

**✅ Gør:**
```html
<!-- Brug nullish coalescing for alle optional felter -->
{{project.description??Ingen beskrivelse}}
{{element.capex??0 | currency}}
{{building.building_type??Ikke angivet}}
```

**❌ Undgå:**
```html
<!-- Direkte variable uden fallback kan være tomme -->
{{project.description}}
{{element.capex}}
```

### 2. Ternary vs Conditionals

**✅ Gør:**
```html
<!-- Simpel check: Brug ternary -->
<span class="{{project.critical_count > 0?text-danger:text-success}}">
  {{project.critical_count > 0?⚠️:✓}}
</span>

<!-- Kompleks logik: Brug conditionals -->
{{if project.critical_count > 10}}
  <div class="alert-critical">
    <h4>Kritisk Situation</h4>
    <p>Meget detaljeret tekst...</p>
    <ul>...</ul>
  </div>
{{endif}}
```

**❌ Undgå:**
```html
<!-- Meget nestet ternary er svær at læse -->
{{project.critical_count > 10?LEVEL1:project.critical_count > 5?LEVEL2:project.critical_count > 2?LEVEL3:LEVEL4}}

<!-- Brug {{if}} istedet for kompleks logik -->
```

### 3. Kombinere Features

**✅ Gør:**
```html
<!-- Nullish + Filter -->
{{project.total_capex??0 | currency}}

<!-- Nullish + Ternary -->
{{project.critical_count??0 > 0?Ja:Nej}}

<!-- Ternary + CSS Class -->
<div class="badge {{project.critical_count > 0?badge-danger:badge-success}}">
  {{project.critical_count??0}} kritiske
</div>
```

### 4. Viewer-Venlige Templates

**✅ Gør:**
```html
<!-- Brug h2 for hovedsektioner (auto-TOC) -->
<h2>Executive Summary</h2>
<h2>Bygningsoversigt</h2>
<h2>Budget & Økonomi</h2>

<!-- Brug h3/h4 for subsektioner -->
<h2>Bygningsoversigt</h2>
<h3>Bygning 1</h3>
<h4>Detaljer</h4>
```

**❌ Undgå:**
```html
<!-- Ikke h2 headers - ingen auto-TOC -->
<div class="section-title">Executive Summary</div>
<p class="big-text">Bygningsoversigt</p>
```

### 5. Performance

**✅ Gør:**
```html
<!-- Single conditional check -->
{{if project.critical_count > 0}}
  <!-- Multiple uses of project.critical_count -->
  <p>Du har {{project.critical_count}} kritiske elementer</p>
  <p>Prioriter disse {{project.critical_count}} elementer</p>
{{endif}}
```

**❌ Undgå:**
```html
<!-- Repeated conditional checks -->
{{if project.critical_count > 0}}<p>Du har {{project.critical_count}} kritiske</p>{{endif}}
{{if project.critical_count > 0}}<p>Prioriter disse {{project.critical_count}}</p>{{endif}}
```

### 6. Læsbarhed

**✅ Gør:**
```html
<!-- Indentér loops og conditionals -->
{{for building in buildings}}
  <div class="building">
    <h3>{{building.building_name}}</h3>

    {{if building.critical_count > 0}}
      <p>⚠️ {{building.critical_count}} kritiske</p>
    {{endif}}
  </div>
{{endfor}}
```

**❌ Undgå:**
```html
<!-- Ingen indentation - svær at læse -->
{{for building in buildings}}<div class="building"><h3>{{building.building_name}}</h3>{{if building.critical_count > 0}}<p>⚠️ {{building.critical_count}} kritiske</p>{{endif}}</div>{{endfor}}
```

### 7. Kunde-Specifikke Templates

**✅ Gør:**
```html
<!-- Juster sprog og tone for kunde -->
<!-- Intern rapport -->
<h2>Critical Elements Requiring Immediate Action</h2>

<!-- Kunde rapport -->
<h2>Områder der Kræver Opmærksomhed</h2>

<!-- Brug flag highlighting til kunde-specifikke fokus -->
<!-- Intern: Vis alle flags -->
{{red_flags_display(element)}}

<!-- Kunde: Fokus på kritiske (røde) -->
{{element.flags (red)}}
```

### 8. Responsiv Design

**✅ Gør:**
```html
<style>
/* Desktop og print */
.report-content {
  max-width: 1200px;
  margin: auto;
}

/* Mobile */
@media (max-width: 768px) {
  .report-content {
    padding: 16px;
  }

  .metrics {
    grid-template-columns: 1fr;
  }
}

/* Print (skjul viewer UI) */
@media print {
  .toc-sidebar,
  .actions-bar {
    display: none;
  }
}
</style>
```

---

## Cheat Sheet

### Quick Reference

```html
<!-- Fejlhåndtering -->
{{variable??default}}                    <!-- Nullish coalescing -->

<!-- Ternary -->
{{variable?true:false}}                  <!-- Simple ternary -->
{{variable > 5?Yes:No}}                  <!-- With comparison -->
{{var > 10?A:var > 5?B:C}}              <!-- Nested (use sparingly) -->

<!-- Flags -->
{{element.flags (red)}}                  <!-- Highlight red -->
{{element.flags (orange)}}               <!-- Highlight orange -->
{{red_flags_display(element)}}           <!-- Standard -->

<!-- Filters -->
{{value | number}}                       <!-- 1.234.567 -->
{{value | currency}}                     <!-- kr. 1.234.567 -->
{{value | date}}                         <!-- 21-01-2026 -->
{{value | uppercase}}                    <!-- UPPERCASE -->
{{value | lowercase}}                    <!-- lowercase -->
{{value | capitalize}}                   <!-- Capitalize -->
{{value | truncate:100}}                 <!-- Truncate... -->

<!-- Kombinationer -->
{{variable??0 | currency}}               <!-- Nullish + filter -->
{{variable > 0?Yes:No}}                  <!-- Ternary comparison -->
{{variable??default | uppercase}}        <!-- Nullish + filter -->

<!-- Loops -->
{{for item in collection}}...{{endfor}}

<!-- Conditionals -->
{{if condition}}...{{endif}}
{{if condition}}...{{else}}...{{endif}}
{{if cond1}}...{{else if cond2}}...{{else}}...{{endif}}
```

---

## Support

For support eller spørgsmål om template syntax:
- Se eksempler i `/database/seeds/report_templates_advanced.sql`
- Test templates i rapport builder preview
- Læs API dokumentation: `/modules/report_builder/api.php`

**Version:** 2.0
**Sidst opdateret:** 21. januar 2026
