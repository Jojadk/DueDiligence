<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Oversigt - {project.name}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 1.5cm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10pt;
            color: #2c3e50;
            line-height: 1.4;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
            font-weight: 300;
        }
        .header .project-info {
            margin-top: 10px;
            opacity: 0.9;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        .summary-value {
            font-size: 20pt;
            font-weight: 600;
            color: #3498db;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 9pt;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 9pt;
        }
        th {
            background: #2c3e50;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 9pt;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        tr:hover {
            background: #e8f4f8;
        }
        .category-header {
            background: #ecf0f1 !important;
            font-weight: 600;
            color: #2c3e50;
        }
        .total-row {
            background: #3498db !important;
            color: white;
            font-weight: 600;
            font-size: 10pt;
        }
        .priority-high {
            color: #e74c3c;
            font-weight: 600;
        }
        .priority-medium {
            color: #f39c12;
            font-weight: 600;
        }
        .priority-low {
            color: #95a5a6;
        }
        .chart-container {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bar-label {
            width: 150px;
            font-weight: 600;
            font-size: 9pt;
        }
        .bar-track {
            flex: 1;
            height: 25px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            padding-left: 10px;
            color: white;
            font-weight: 600;
            font-size: 9pt;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            font-size: 8pt;
            color: #777;
        }
        @media print {
            .summary-grid {
                page-break-inside: avoid;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Budget Oversigt</h1>
        <div class="project-info">
            {project.name} • {customer.name} • {current_date}
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-value">{total_budget_millions}</div>
            <div class="summary-label">Mio. DKK CAPEX</div>
        </div>
        <div class="summary-card">
            <div class="summary-value">{total_opex_thousands}</div>
            <div class="summary-label">Tsd. DKK/år OPEX</div>
        </div>
        <div class="summary-card">
            <div class="summary-value">{budget_item_count}</div>
            <div class="summary-label">Budget Poster</div>
        </div>
        <div class="summary-card">
            <div class="summary-value">{building_count}</div>
            <div class="summary-label">Bygninger</div>
        </div>
        <div class="summary-card">
            <div class="summary-value">{avg_cost_per_sqm}</div>
            <div class="summary-label">DKK/m²</div>
        </div>
    </div>

    <h2 style="margin-top: 30px;">CAPEX - Kapitaludgifter (Investeringer)</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Nr.</th>
                <th style="width: 15%;">Kategori</th>
                <th style="width: 30%;">Beskrivelse</th>
                <th style="width: 15%;">Bygning</th>
                <th style="width: 10%; text-align: center;">Prioritet</th>
                <th style="width: 10%; text-align: center;">Tidsramme</th>
                <th style="width: 15%; text-align: right;">Beløb (DKK)</th>
            </tr>
        </thead>
        <tbody>
        {foreach budget_items}
            <tr>
                <td>{item.number}</td>
                <td>{item.category}</td>
                <td>{item.description}</td>
                <td>{item.building_name}</td>
                <td style="text-align: center;" class="priority-{item.priority_level}">
                    {item.priority}
                </td>
                <td style="text-align: center;">{item.timeframe}</td>
                <td style="text-align: right; font-weight: 600;">
                    {item.amount_formatted}
                </td>
            </tr>
        {/foreach}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6"><strong>TOTAL CAPEX:</strong></td>
                <td style="text-align: right;"><strong>{total_budget_formatted} DKK</strong></td>
            </tr>
        </tfoot>
    </table>

    <h2 style="margin-top: 40px;">Budget Fordeling pr. Kategori</h2>

    <div class="chart-container">
        <div class="bar-chart">
        {foreach budget_categories}
            <div class="bar-item">
                <div class="bar-label">{category.name}</div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: {category.percentage}%;">
                        {category.amount_formatted} DKK ({category.percentage}%)
                    </div>
                </div>
            </div>
        {/foreach}
        </div>
    </div>

    <h2 style="margin-top: 40px;">Budget Fordeling pr. Bygning</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 40%;">Bygning</th>
                <th style="width: 15%; text-align: right;">Areal (m²)</th>
                <th style="width: 15%; text-align: right;">Antal poster</th>
                <th style="width: 15%; text-align: right;">Budget (DKK)</th>
                <th style="width: 15%; text-align: right;">DKK/m²</th>
            </tr>
        </thead>
        <tbody>
        {foreach building_budgets}
            <tr>
                <td><strong>{building.name}</strong></td>
                <td style="text-align: right;">{building.area}</td>
                <td style="text-align: right;">{building.item_count}</td>
                <td style="text-align: right; font-weight: 600;">{building.budget_formatted}</td>
                <td style="text-align: right;">{building.cost_per_sqm}</td>
            </tr>
        {/foreach}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td style="text-align: right;"><strong>{total_area}</strong></td>
                <td style="text-align: right;"><strong>{total_items}</strong></td>
                <td style="text-align: right;"><strong>{total_budget_formatted} DKK</strong></td>
                <td style="text-align: right;"><strong>{avg_cost_per_sqm}</strong></td>
            </tr>
        </tfoot>
    </table>

    <h2 style="margin-top: 40px;">OPEX - Driftsudgifter (Årlige omkostninger)</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Kategori</th>
                <th style="width: 40%;">Beskrivelse</th>
                <th style="width: 20%;">Bygning</th>
                <th style="width: 20%; text-align: right;">Årligt beløb (DKK)</th>
            </tr>
        </thead>
        <tbody>
        {foreach opex_items}
            <tr>
                <td>{item.category}</td>
                <td>{item.description}</td>
                <td>{item.building_name}</td>
                <td style="text-align: right; font-weight: 600;">{item.annual_amount_formatted}</td>
            </tr>
        {/foreach}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3"><strong>TOTAL ÅRLIG OPEX:</strong></td>
                <td style="text-align: right;"><strong>{total_opex_formatted} DKK</strong></td>
            </tr>
            <tr style="background: #34495e; color: white; font-weight: 600;">
                <td colspan="3"><strong>10-ÅRIG OPEX:</strong></td>
                <td style="text-align: right;"><strong>{opex_10_years_formatted} DKK</strong></td>
            </tr>
        </tfoot>
    </table>

    <h2 style="margin-top: 40px;">Total Cost of Ownership (TCO)</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Omkostningstype</th>
                <th style="width: 25%; text-align: right;">5 år (DKK)</th>
                <th style="width: 25%; text-align: right;">10 år (DKK)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>CAPEX (Investeringer)</strong></td>
                <td style="text-align: right;">{total_budget_formatted}</td>
                <td style="text-align: right;">{total_budget_formatted}</td>
            </tr>
            <tr>
                <td><strong>OPEX (Drift)</strong></td>
                <td style="text-align: right;">{opex_5_years_formatted}</td>
                <td style="text-align: right;">{opex_10_years_formatted}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>TOTAL TCO:</strong></td>
                <td style="text-align: right;"><strong>{tco_5_years_formatted} DKK</strong></td>
                <td style="text-align: right;"><strong>{tco_10_years_formatted} DKK</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>
            <strong>Rapport genereret:</strong> {current_date} |
            <strong>System:</strong> DueDiligence v2.0 |
            <strong>Projekt:</strong> {project.name} |
            <strong>Kunde:</strong> {customer.name}
        </p>
        <p style="margin-top: 10px;">
            <em>Alle beløb er ekskl. moms. Budgettet er baseret på erfaringstal og bør verificeres med detaljerede tilbud.</em>
        </p>
    </div>
</body>
</html>
