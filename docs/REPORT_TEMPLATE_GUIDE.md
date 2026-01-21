# Report Template Guide

Guide til at oprette kundespecifikke rapporter med template syntax.

## 📚 Indholdsfortegnelse

1. [Template Syntax](#template-syntax)
2. [Tilgængelige Variabler](#tilgængelige-variabler)
3. [Loops & Iteration](#loops--iteration)
4. [Conditionals](#conditionals)
5. [Filters](#filters)
6. [Specielle Funktioner](#specielle-funktioner)
7. [Eksempler](#eksempler)
8. [Styling](#styling)

---

## Template Syntax

### Basis Variabler

```html
{{variable_name}}
```

Eksempel:
```html
<h1>{{project.name}}</h1>
<p>Kunde: {{project.customer_name}}</p>
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
  <!-- Content med item variabler -->
{{endfor}}
```

### Tilgængelige Collections

#### 1. Bygninger Loop

```html
{{for building in buildings}}
  <h3>{{building.building_name}}</h3>
  <p>Areal: {{building.gross_area}} m²</p>
  <p>CAPEX: {{building.total_capex}}</p>
{{endfor}}
```

#### 2. Elementer Loop

```html
{{for element in elements}}
  <div>
    <h4>{{element.element_name}}</h4>
    <p>Kode: {{element.element_code}}</p>
    <p>Tilstand: {{element.condition_score}}/10</p>
  </div>
{{endfor}}
```

#### 3. Røde Flag Loop

```html
{{for flag in red_flags}}
  <tr>
    <td>{{flag.element_name}}</td>
    <td>{{flag.building_name}}</td>
    <td>{{flag.severity}}</td>
  </tr>
{{endfor}}
```

### Nested Loops

```html
{{for building in buildings}}
  <h3>{{building.building_name}}</h3>

  <!-- Loop gennem elementer der tilhører denne bygning -->
  {{for element in elements}}
  {{if element.building_id == building.building_id}}
    <p>{{element.element_name}}</p>
  {{endif}}
  {{endfor}}
{{endfor}}
```

---

## Conditionals

### If Statement

```html
{{if condition}}
  <!-- Indhold hvis sand -->
{{endif}}
```

### If-Else Statement

```html
{{if condition}}
  <!-- Indhold hvis sand -->
{{else}}
  <!-- Indhold hvis falsk -->
{{endif}}
```

### Conditions med Operatorer

#### Større end (>)

```html
{{if project.critical_count > 10}}
  <div class="alert">Mange kritiske elementer!</div>
{{endif}}
```

#### Mindre end (<)

```html
{{if building.avg_condition < 5}}
  <p>Bygning kræver renovering</p>
{{endif}}
```

#### Større eller lig (>=)

```html
{{if element.condition_score >= 8}}
  <span class="good">God tilstand</span>
{{endif}}
```

#### Mindre eller lig (<=)

```html
{{if element.condition_score <= 3}}
  <span class="critical">Kritisk tilstand</span>
{{endif}}
```

#### Lige med (==)

```html
{{if element.urgency == 'critical'}}
  <span class="badge-critical">Kritisk</span>
{{endif}}
```

#### Ikke lige med (!=)

```html
{{if building.element_count != 0}}
  <p>Bygning har elementer</p>
{{endif}}
```

### Nested Conditionals

```html
{{if project.critical_count > 10}}
  <div class="risk-high">Høj risiko</div>
{{else if project.critical_count > 5}}
  <div class="risk-medium">Moderat risiko</div>
{{else if project.critical_count > 0}}
  <div class="risk-low">Lav risiko</div>
{{else}}
  <div class="risk-none">Ingen risiko</div>
{{endif}}
```

### Conditions i Loops

```html
{{for building in buildings}}
  <h3>{{building.building_name}}</h3>

  {{if building.critical_count > 0}}
    <p class="warning">⚠️ {{building.critical_count}} kritiske elementer</p>
  {{else}}
    <p class="ok">✓ Ingen kritiske elementer</p>
  {{endif}}
{{endfor}}
```

---

## Filters

Filters formaterer værdier ved hjælp af "pipe" syntax: `{{value | filter}}`

### Tilgængelige Filters

#### 1. number - Formatér tal med tusindtalsseparator

```html
{{project.total_capex | number}}
<!-- Output: 1.234.567 -->

{{building.gross_area | number}} m²
<!-- Output: 1.250 m² -->
```

#### 2. currency - Formatér som valuta (DKK)

```html
{{project.total_capex | currency}}
<!-- Output: kr. 1.234.567 -->

{{element.capex | currency}}
<!-- Output: kr. 45.000 -->
```

#### 3. date - Formatér dato

```html
{{project.created_at | date}}
<!-- Input: 2024-01-15 12:30:00 -->
<!-- Output: 15-01-2024 -->

{{project.generated_at | date}}
<!-- Output: 21-01-2026 -->
```

#### 4. uppercase - Store bogstaver

```html
{{project.name | uppercase}}
<!-- Input: Mit Projekt -->
<!-- Output: MIT PROJEKT -->
```

#### 5. lowercase - Små bogstaver

```html
{{building.building_type | lowercase}}
<!-- Input: KONTORBYGNING -->
<!-- Output: kontorbygning -->
```

#### 6. capitalize - Stort forbogstav

```html
{{element.urgency | capitalize}}
<!-- Input: critical -->
<!-- Output: Critical -->
```

#### 7. truncate - Afkort tekst

```html
{{project.description | truncate:100}}
<!-- Afkorter til 100 tegn med "..." -->
```

### Kombinerede Filters i Loops

```html
{{for building in buildings}}
  <h3>{{building.building_name | uppercase}}</h3>
  <p>Type: {{building.building_type | capitalize}}</p>
  <p>CAPEX: {{building.total_capex | currency}}</p>
{{endfor}}
```

### Filters med Conditionals

```html
{{for element in elements}}
  <tr>
    <td>{{element.element_name}}</td>
    <td>{{element.capex | currency}}</td>
    <td>
      {{if element.urgency == 'critical'}}
        {{element.urgency | uppercase}}
      {{else}}
        {{element.urgency | capitalize}}
      {{endif}}
    </td>
  </tr>
{{endfor}}
```

---

## Specielle Funktioner

### 1. red_flags_display() - Vis flag badges

Viser visuelle badges for røde flag med 5 kategorier.

```html
<!-- For enkelte element -->
{{red_flags_display(element)}}
```

**Output:**
- Viser alle 5 flag typer (Kritisk, Alvorlig, Moderat, Mindre, Info)
- Highlighter den valgte kategori baseret på element's red_flag_score
- Inkluderer farver og ikoner

**I loop:**

```html
{{for element in elements}}
  <tr>
    <td>{{element.element_name}}</td>
    <td>{{red_flags_display(element)}}</td>
  </tr>
{{endfor}}
```

**Styling:**

Badges er automatisk stylet med:
- Farver: Rød (kritisk), Orange (alvorlig), Gul (moderat), Grøn (mindre), Blå (info)
- Ikoner: 🚩, ⚠️, ⚡, ℹ️, 💡
- Border highlight på valgt kategori
- Opacity 0.2 på ikke-valgte

### 2. Beregninger i Templates

Du kan lave simple beregninger:

```html
<!-- Division -->
{{building.total_capex / building.gross_area | currency}}/m²

<!-- Multiplikation (vigtigt: brug mellemrum) -->
Estimeret: {{element.capex * 1.2 | currency}} (inkl. 20% overhead)

<!-- Procentberegning -->
{{project.critical_count / project.element_count * 100}}% kritiske
```

---

## Eksempler

### Eksempel 1: Simpel Oversigt

```html
<div class="report">
  <h1>{{project.name}}</h1>
  <p>Kunde: {{project.customer_name}}</p>
  <p>Dato: {{project.generated_at | date}}</p>

  <h2>Nøgletal</h2>
  <ul>
    <li>Bygninger: {{project.building_count}}</li>
    <li>CAPEX: {{project.total_capex | currency}}</li>
    {{if project.critical_count > 0}}
    <li class="warning">Kritiske: {{project.critical_count}}</li>
    {{endif}}
  </ul>
</div>
```

### Eksempel 2: Bygnings Liste med Conditionals

```html
<div class="buildings">
  <h2>Bygninger</h2>

  {{for building in buildings}}
  <div class="building {{if building.critical_count > 0}}has-critical{{endif}}">
    <h3>{{building.building_name}}</h3>

    <table>
      <tr>
        <td>Areal:</td>
        <td>{{building.gross_area | number}} m²</td>
      </tr>
      <tr>
        <td>CAPEX:</td>
        <td>{{building.total_capex | currency}}</td>
      </tr>
      <tr>
        <td>Tilstand:</td>
        <td>
          {{if building.avg_condition >= 8}}
            <span class="good">God ({{building.avg_condition}}/10)</span>
          {{else if building.avg_condition >= 6}}
            <span class="fair">Acceptabel ({{building.avg_condition}}/10)</span>
          {{else}}
            <span class="poor">Dårlig ({{building.avg_condition}}/10)</span>
          {{endif}}
        </td>
      </tr>
    </table>

    {{if building.critical_count > 0}}
    <div class="alert">
      ⚠️ {{building.critical_count}} kritiske elementer kræver opmærksomhed
    </div>
    {{endif}}
  </div>
  {{endfor}}
</div>
```

### Eksempel 3: Element Tabel med Red Flags

```html
<h2>Elementer</h2>

<table>
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
    <tr class="{{if element.urgency == 'critical'}}row-critical{{endif}}">
      <td>{{element.element_name}}</td>
      <td><code>{{element.element_code}}</code></td>
      <td>{{element.condition_score}}/10</td>
      <td>
        {{if element.urgency == 'critical'}}
          🔴 {{element.urgency | uppercase}}
        {{else if element.urgency == 'high'}}
          🟡 {{element.urgency | capitalize}}
        {{else}}
          {{element.urgency | capitalize}}
        {{endif}}
      </td>
      <td>{{element.capex | currency}}</td>
      <td>{{red_flags_display(element)}}</td>
    </tr>
    {{endfor}}
  </tbody>
</table>
```

### Eksempel 4: Komplet Rapport med TOC

```html
<div class="report">
  <!-- Forside -->
  <div class="cover-page">
    <h1>{{project.name}}</h1>
    <p>{{project.customer_name}}</p>
    <p>{{project.generated_at | date}}</p>
  </div>

  <!-- Indholdsfortegnelse -->
  <div class="toc">
    <h2>Indholdsfortegnelse</h2>
    <ol>
      <li><a href="#summary">Opsummering</a></li>
      <li><a href="#buildings">Bygninger</a></li>
      <li><a href="#elements">Elementer</a></li>
      <li><a href="#redflags">Røde Flag</a></li>
    </ol>
  </div>

  <!-- Sektion 1: Opsummering -->
  <div id="summary" class="section">
    <h2>1. Opsummering</h2>
    <p>Projektet omfatter {{project.building_count}} bygninger med i alt
       {{project.element_count}} elementer og estimeret CAPEX på
       {{project.total_capex | currency}}.</p>

    {{if project.critical_count > 10}}
    <div class="alert-critical">
      <strong>Kritisk!</strong> {{project.critical_count}} elementer kræver
      øjeblikkelig handling.
    </div>
    {{else if project.critical_count > 0}}
    <div class="alert-warning">
      <strong>Opmærksomhed!</strong> {{project.critical_count}} elementer
      kræver planlægning.
    </div>
    {{else}}
    <div class="alert-success">
      <strong>God tilstand!</strong> Ingen kritiske elementer.
    </div>
    {{endif}}
  </div>

  <!-- Sektion 2: Bygninger -->
  <div id="buildings" class="section">
    <h2>2. Bygninger</h2>

    {{for building in buildings}}
    <div class="building-section">
      <h3>{{building.building_name}}</h3>
      <p>CAPEX: {{building.total_capex | currency}} |
         Areal: {{building.gross_area | number}} m² |
         Tilstand: {{building.avg_condition}}/10</p>
    </div>
    {{endfor}}
  </div>

  <!-- Sektion 3: Røde Flag -->
  <div id="redflags" class="section">
    <h2>3. Røde Flag</h2>

    {{if project.critical_count > 0}}
    <table>
      <thead>
        <tr>
          <th>Element</th>
          <th>Bygning</th>
          <th>Alvorlighed</th>
          <th>CAPEX</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        {{for flag in red_flags}}
        <tr>
          <td>{{flag.element_name}}</td>
          <td>{{flag.building_name}}</td>
          <td>{{flag.severity | capitalize}}</td>
          <td>{{flag.capex | currency}}</td>
          <td>{{red_flags_display(flag)}}</td>
        </tr>
        {{endfor}}
      </tbody>
    </table>
    {{else}}
    <p>✓ Ingen kritiske røde flag identificeret.</p>
    {{endif}}
  </div>
</div>
```

### Eksempel 5: Kundespecifik Præsentation

```html
<div class="presentation">
  <!-- Slide 1: Forside -->
  <div class="slide cover">
    <h1>{{project.name}}</h1>
    <h2>Due Diligence Præsentation</h2>
    <p>Forberedt for: {{project.customer_name}}</p>
    <p>{{project.generated_at | date}}</p>
  </div>

  <!-- Slide 2: Executive Summary -->
  <div class="slide">
    <h2>Executive Summary</h2>

    <div class="metrics-grid">
      <div class="metric">
        <div class="value">{{project.building_count}}</div>
        <div class="label">Bygninger</div>
      </div>

      <div class="metric">
        <div class="value">{{project.total_capex | currency}}</div>
        <div class="label">CAPEX</div>
      </div>

      <div class="metric {{if project.critical_count > 0}}highlight{{endif}}">
        <div class="value">{{project.critical_count}}</div>
        <div class="label">Kritiske</div>
      </div>
    </div>

    {{if project.critical_count > 5}}
    <div class="risk-badge high">HØJ RISIKO</div>
    {{else if project.critical_count > 0}}
    <div class="risk-badge medium">MODERAT RISIKO</div>
    {{else}}
    <div class="risk-badge low">LAV RISIKO</div>
    {{endif}}
  </div>

  <!-- Slide 3: Bygninger -->
  <div class="slide">
    <h2>Bygnings Oversigt</h2>

    <table>
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
        <tr {{if building.critical_count > 0}}class="attention"{{endif}}>
          <td>{{building.building_name}}</td>
          <td>{{building.element_count}}</td>
          <td>{{building.critical_count}}</td>
          <td>{{building.total_capex | currency}}</td>
        </tr>
        {{endfor}}
      </tbody>
    </table>
  </div>
</div>
```

---

## Styling

### Inline Styles

Du kan inkludere CSS direkte i templaten:

```html
<style>
.report { max-width: 800px; margin: auto; font-family: Arial, sans-serif; }
.alert { padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; }
.critical { color: #dc3545; font-weight: bold; }
table { width: 100%; border-collapse: collapse; }
th { background: #f5f5f5; padding: 10px; text-align: left; }
td { padding: 8px; border-bottom: 1px solid #ddd; }
</style>
```

### Klasser til Conditionals

```html
{{if element.urgency == 'critical'}}
  <span class="badge badge-critical">Kritisk</span>
{{else if element.urgency == 'high'}}
  <span class="badge badge-warning">Høj</span>
{{else}}
  <span class="badge badge-info">Normal</span>
{{endif}}

<style>
.badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
.badge-critical { background: #dc3545; color: white; }
.badge-warning { background: #ffc107; color: #333; }
.badge-info { background: #17a2b8; color: white; }
</style>
```

### Print Styles

```html
<style>
/* Skærm visning */
.report { max-width: 210mm; margin: auto; padding: 20px; }

/* Print visning */
@media print {
  .section { page-break-after: always; }
  .no-print { display: none; }
  body { margin: 0; padding: 0; }
}
</style>
```

### Responsive Grid

```html
<style>
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin: 20px 0;
}

.metric {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}

.metric .value {
  font-size: 32px;
  font-weight: bold;
  color: #333;
}

.metric .label {
  font-size: 14px;
  color: #666;
  text-transform: uppercase;
  margin-top: 5px;
}
</style>
```

---

## Tips & Best Practices

### 1. Brug Semantiske Klasser

```html
<!-- Godt -->
<div class="alert alert-critical">Kritisk problem</div>

<!-- Undgå -->
<div style="background: red; color: white;">Kritisk problem</div>
```

### 2. Konsistent Formatering

```html
<!-- Brug filters konsistent -->
{{project.total_capex | currency}}
{{building.total_capex | currency}}
{{element.capex | currency}}
```

### 3. Defensiv Programmering

```html
<!-- Check for data før loop -->
{{if project.building_count > 0}}
  {{for building in buildings}}
    <!-- Bygning content -->
  {{endfor}}
{{else}}
  <p>Ingen bygninger registreret.</p>
{{endif}}
```

### 4. Brugervenlige Beskeder

```html
{{if project.critical_count > 0}}
  <p>Der er identificeret {{project.critical_count}} kritiske elementer
     der kræver øjeblikkelig opmærksomhed.</p>
{{else}}
  <p>✓ Ingen kritiske elementer identificeret. Projektet er i god stand.</p>
{{endif}}
```

### 5. Overskuelig Struktur

```html
<!-- Opdel i sektioner -->
<div class="report">
  <section id="cover"><!-- Forside --></section>
  <section id="toc"><!-- TOC --></section>
  <section id="summary"><!-- Sammenfatning --></section>
  <section id="details"><!-- Detaljer --></section>
  <section id="appendix"><!-- Appendix --></section>
</div>
```

---

## Kundespecifikke Rapporter

### Sådan opretter du kundespecifikke templates:

1. **Kopier en eksisterende template** som udgangspunkt
2. **Tilpas branding**: Logo, farver, typografi
3. **Vælg relevante sektioner** baseret på kundens behov
4. **Juster detaljeniveau** (executive vs. teknisk)
5. **Test med rigtige data**

### Eksempel: Minimalistisk Kunde Rapport

```html
<div class="minimal-report">
  <style>
    .minimal-report { font-family: "Helvetica Neue", sans-serif; max-width: 800px; margin: auto; }
    .header { text-align: center; padding: 60px 0; border-bottom: 1px solid #e0e0e0; }
    .section { margin: 40px 0; }
    .key-number { font-size: 48px; font-weight: 300; color: #333; }
    .label { font-size: 14px; color: #999; text-transform: uppercase; letter-spacing: 2px; }
  </style>

  <div class="header">
    <h1>{{project.name}}</h1>
    <p class="label">{{project.customer_name}}</p>
  </div>

  <div class="section">
    <div class="key-number">{{project.total_capex | currency}}</div>
    <div class="label">Estimeret Investering</div>
  </div>

  {{if project.critical_count > 0}}
  <div class="section">
    <div class="key-number">{{project.critical_count}}</div>
    <div class="label">Elementer Kræver Handling</div>
  </div>
  {{endif}}
</div>
```

---

## Fejlfinding

### Template vises ikke korrekt?

1. **Check syntax**: Sørg for at alle `{{}}` er lukket korrekt
2. **Verificer variabelnavne**: Brug præcis de navne der er listet i guiden
3. **Test conditionals**: Brug simple conditions først
4. **Valider loops**: Sørg for `{{endfor}}` matches `{{for}}`

### Data vises ikke?

1. **Check filters**: Nogle filters kan returnere tom streng ved fejl
2. **Verificer loop nesting**: Nested loops kræver korrekt scope
3. **Test med simpel template**: Start simpelt og byg op

### Styling fungerer ikke?

1. **Inkluder `<style>` tags**: CSS skal være i `<style></style>`
2. **Brug inline styles** hvis nødvendigt
3. **Test i browser**: Se hvordan det renderer

---

## Support

For hjælp eller spørgsmål:
- Se eksempel templates i systemet (8 forudindstillede templates)
- Check `/api.php?module=report_builder&action=get_variables` for aktuelle variabler
- Test templates med preview funktionen før generering

