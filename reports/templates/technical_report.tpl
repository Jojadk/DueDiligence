<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknisk Rapport - {project.name}</title>
    <style>
        @page {
            size: A4;
            margin: 2.5cm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2c3e50;
            font-size: 11pt;
        }
        h1 {
            color: #2c3e50;
            font-size: 28pt;
            margin-bottom: 10px;
            font-weight: 300;
        }
        h2 {
            color: #34495e;
            font-size: 18pt;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #3498db;
        }
        h3 {
            color: #34495e;
            font-size: 14pt;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        h4 {
            color: #555;
            font-size: 12pt;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 10pt;
        }
        th {
            background: #3498db;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .info-box {
            background: #ecf0f1;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .warning-box {
            background: #fff5f5;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border-left: 4px solid #e74c3c;
        }
        .building-section {
            page-break-before: always;
            margin-top: 40px;
        }
        .building-section:first-of-type {
            page-break-before: auto;
        }
        .element-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        .element-card {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 4px;
        }
        .element-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .condition-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
        }
        .condition-1 { background: #2ecc71; color: white; }
        .condition-2 { background: #f39c12; color: white; }
        .condition-3 { background: #e74c3c; color: white; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            font-size: 9pt;
            color: #777;
        }
        .toc {
            page-break-after: always;
        }
        @media print {
            .building-section {
                page-break-before: always;
            }
            .element-grid {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Cover Page -->
    <div style="text-align: center; margin-top: 200px; page-break-after: always;">
        <h1 style="font-size: 36pt; margin-bottom: 20px;">Teknisk Rapport</h1>
        <h2 style="border: none; font-size: 24pt; font-weight: 300;">{project.name}</h2>
        <p style="font-size: 14pt; margin-top: 40px;">
            {project.address}<br>
            {project.zip} {project.city}
        </p>
        <p style="font-size: 12pt; margin-top: 60px; color: #777;">
            Udarbejdet for:<br>
            <strong style="color: #2c3e50;">{customer.name}</strong>
        </p>
        <p style="font-size: 11pt; margin-top: 80px; color: #777;">
            Rapport dato: {current_date}<br>
            DueDiligence v2.0
        </p>
    </div>

    <!-- Table of Contents -->
    <div class="toc">
        <h2>Indholdsfortegnelse</h2>
        <ol>
            <li>Projektinformation</li>
            <li>Executive Summary</li>
            <li>Bygningsbeskrivelser
                <ol style="list-style-type: lower-alpha;">
                {foreach buildings}
                    <li>{building.name}</li>
                {/foreach}
                </ol>
            </li>
            <li>Budget og Investeringsbehov</li>
            <li>Red Flags og Kritiske Forhold</li>
            <li>Anbefalinger</li>
            <li>Bilag</li>
        </ol>
    </div>

    <!-- 1. Project Information -->
    <h2>1. Projektinformation</h2>

    <table>
        <tr>
            <th style="width: 30%;">Felt</th>
            <th>Værdi</th>
        </tr>
        <tr>
            <td><strong>Projektnavn</strong></td>
            <td>{project.name}</td>
        </tr>
        <tr>
            <td><strong>Adresse</strong></td>
            <td>{project.address}, {project.zip} {project.city}</td>
        </tr>
        <tr>
            <td><strong>BBR-nummer</strong></td>
            <td>{project.bbr_number}</td>
        </tr>
        <tr>
            <td><strong>Kunde</strong></td>
            <td>{customer.name}</td>
        </tr>
        <tr>
            <td><strong>CVR-nummer</strong></td>
            <td>{customer.cvr_number}</td>
        </tr>
        <tr>
            <td><strong>Projekttype</strong></td>
            <td>{project.project_type}</td>
        </tr>
        <tr>
            <td><strong>Start dato</strong></td>
            <td>{project.start_date}</td>
        </tr>
        <tr>
            <td><strong>Rapport dato</strong></td>
            <td>{current_date}</td>
        </tr>
    </table>

    <!-- 2. Executive Summary -->
    <h2>2. Executive Summary</h2>

    <div class="info-box">
        <p><strong>Formål:</strong> Denne tekniske rapport dokumenterer fund fra due diligence
        undersøgelsen af {project.name}. Rapporten omfatter detaljerede beskrivelser af
        bygninger, bygningsdele, tilstandsvurderinger og budget.</p>
    </div>

    <h3>Nøgletal</h3>
    <table>
        <tr>
            <th style="width: 40%;">Parameter</th>
            <th style="text-align: right;">Værdi</th>
        </tr>
        <tr>
            <td>Antal bygninger</td>
            <td style="text-align: right;">{building_count} stk</td>
        </tr>
        <tr>
            <td>Samlet bygningsareal</td>
            <td style="text-align: right;">{total_area} m²</td>
        </tr>
        <tr>
            <td>Gennemsnitligt byggeår</td>
            <td style="text-align: right;">{avg_year_built}</td>
        </tr>
        <tr>
            <td>Estimeret CAPEX (5 år)</td>
            <td style="text-align: right;">{total_budget} DKK</td>
        </tr>
        <tr>
            <td>Estimeret årlig OPEX</td>
            <td style="text-align: right;">{total_opex} DKK</td>
        </tr>
        <tr>
            <td>Total Cost of Ownership (10 år)</td>
            <td style="text-align: right;">{tco_10_years} DKK</td>
        </tr>
        <tr>
            <td>Antal identificerede red flags</td>
            <td style="text-align: right;">{red_flag_count} stk</td>
        </tr>
    </table>

    <!-- 3. Building Descriptions -->
    <h2>3. Bygningsbeskrivelser</h2>

    {foreach buildings}
    <div class="building-section">
        <h3>3.{building.index}. {building.name}</h3>

        <h4>Bygningsdata</h4>
        <table>
            <tr>
                <td style="width: 30%;"><strong>Type</strong></td>
                <td>{building.type}</td>
            </tr>
            <tr>
                <td><strong>Byggeår</strong></td>
                <td>{building.year_built}</td>
            </tr>
            <tr>
                <td><strong>Areal</strong></td>
                <td>{building.total_area} m²</td>
            </tr>
            <tr>
                <td><strong>Etager</strong></td>
                <td>{building.floors}</td>
            </tr>
            <tr>
                <td><strong>Samlet tilstand</strong></td>
                <td><span class="condition-badge condition-{building.condition}">{building.condition_text}</span></td>
            </tr>
        </table>

        <h4>Beskrivelse</h4>
        <p>{building.description}</p>

        <h4>Bygningsdele</h4>
        {if building.elements}
        <table>
            <thead>
                <tr>
                    <th>Element</th>
                    <th>Type</th>
                    <th>Tilstand</th>
                    <th style="text-align: right;">Estimeret værdi</th>
                </tr>
            </thead>
            <tbody>
            {foreach building.elements}
                <tr>
                    <td>{element.name}</td>
                    <td>{element.type}</td>
                    <td><span class="condition-badge condition-{element.condition}">{element.condition_text}</span></td>
                    <td style="text-align: right;">{element.value} DKK</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        {else}
        <p><em>Ingen bygningsdele registreret.</em></p>
        {/if}

        <h4>Bemærkninger</h4>
        {if building.notes}
        <p>{building.notes}</p>
        {else}
        <p><em>Ingen særlige bemærkninger.</em></p>
        {/if}
    </div>
    {/foreach}

    <!-- 4. Budget and Investment Needs -->
    <h2>4. Budget og Investeringsbehov</h2>

    <h3>CAPEX (Kapitaludgifter)</h3>
    <table>
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Beskrivelse</th>
                <th>Bygning</th>
                <th style="text-align: right;">Beløb (DKK)</th>
                <th style="text-align: center;">Prioritet</th>
            </tr>
        </thead>
        <tbody>
        {foreach budget_items}
            <tr>
                <td>{item.category}</td>
                <td>{item.description}</td>
                <td>{item.building_name}</td>
                <td style="text-align: right;">{item.amount}</td>
                <td style="text-align: center;">{item.priority}</td>
            </tr>
        {/foreach}
        </tbody>
        <tfoot>
            <tr style="background: #ecf0f1; font-weight: 600;">
                <td colspan="3"><strong>Total CAPEX:</strong></td>
                <td style="text-align: right;"><strong>{total_budget} DKK</strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <h3>OPEX (Driftsudgifter)</h3>
    <table>
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Beskrivelse</th>
                <th style="text-align: right;">Årligt beløb (DKK)</th>
            </tr>
        </thead>
        <tbody>
        {foreach opex_items}
            <tr>
                <td>{item.category}</td>
                <td>{item.description}</td>
                <td style="text-align: right;">{item.annual_amount}</td>
            </tr>
        {/foreach}
        </tbody>
        <tfoot>
            <tr style="background: #ecf0f1; font-weight: 600;">
                <td colspan="2"><strong>Total årlig OPEX:</strong></td>
                <td style="text-align: right;"><strong>{total_opex} DKK</strong></td>
            </tr>
        </tfoot>
    </table>

    <!-- 5. Red Flags -->
    <h2>5. Red Flags og Kritiske Forhold</h2>

    {if red_flag_count > 0}
    <div class="warning-box">
        <p><strong>Vigtigt:</strong> Følgende {red_flag_count} kritiske forhold er identificeret
        og kræver særlig opmærksomhed. Disse bør prioriteres i vedligeholdelsesplanen.</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Prioritet</th>
                <th>Beskrivelse</th>
                <th style="width: 25%;">Bygning</th>
                <th style="width: 15%;">Kategori</th>
            </tr>
        </thead>
        <tbody>
        {foreach red_flags}
            <tr class="{if flag.priority == 'Høj'}style='background:#fff5f5;'{/if}">
                <td><span class="condition-badge condition-{flag.priority_level}">{flag.priority}</span></td>
                <td>{flag.description}</td>
                <td>{flag.building_name}</td>
                <td>{flag.category}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    {else}
    <div class="info-box">
        <p>Der er ikke identificeret kritiske forhold i denne undersøgelse.</p>
    </div>
    {/if}

    <!-- 6. Recommendations -->
    <h2>6. Anbefalinger</h2>

    <h3>Umiddelbare anbefalinger (0-12 måneder)</h3>
    <ol>
        <li>Gennemfør nødvendig akut vedligeholdelse af kritiske bygningsdele</li>
        <li>Adresser alle red flags med høj prioritet</li>
        <li>Etabler systematisk inspektionsplan for bygninger</li>
        <li>Implementer digital vedligeholdelseslog</li>
    </ol>

    <h3>Kortsigtede anbefalinger (1-3 år)</h3>
    <ol>
        <li>Gennemfør planlagt vedligeholdelse i henhold til budget</li>
        <li>Prioriter energioptimeringer med kort tilbagebetalingstid</li>
        <li>Opdater dokumentation og tegninger</li>
        <li>Overvej certificering af bygninger</li>
    </ol>

    <h3>Langsigtede anbefalinger (3-10 år)</h3>
    <ol>
        <li>Planlæg større renoveringer og moderniseringer</li>
        <li>Vurder muligheder for bæredygtige energiløsninger</li>
        <li>Overvej strategiske opgraderinger for at øge ejendommens værdi</li>
        <li>Implementer predictive maintenance strategier</li>
    </ol>

    <!-- Footer -->
    <div class="footer">
        <p>
            <strong>Rapport genereret:</strong> {current_date}<br>
            <strong>System:</strong> DueDiligence v2.0<br>
            <strong>Kunde:</strong> {customer.name}<br>
            <strong>CVR:</strong> {customer.cvr_number}
        </p>
        <p style="margin-top: 20px;">
            <em>Denne rapport er udarbejdet baseret på visuel inspektion og tilgængelig
            dokumentation. For detaljerede tekniske undersøgelser anbefales specialiserede analyser.</em>
        </p>
    </div>
</body>
</html>
