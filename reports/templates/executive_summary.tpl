<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Summary - {project.name}</title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            padding: 40px;
            margin: -20px -20px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 300;
        }
        .header .subtitle {
            margin-top: 10px;
            opacity: 0.9;
            font-size: 18px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .info-item {
            padding: 10px;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 16px;
            color: #2c3e50;
            margin-top: 5px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        .kpi-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .kpi-value {
            font-size: 32px;
            font-weight: 600;
            color: #3498db;
            margin-bottom: 5px;
        }
        .kpi-label {
            font-size: 12px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section {
            margin: 40px 0;
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-top: 40px;
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
        .recommendation {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 0 4px 4px 0;
        }
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #777;
            font-size: 12px;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
        @media print {
            .kpi-grid {
                page-break-inside: avoid;
            }
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Executive Summary</h1>
        <div class="subtitle">{project.name}</div>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Projektnavn</div>
            <div class="info-value">{project.name}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Kunde</div>
            <div class="info-value">{customer.name}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Adresse</div>
            <div class="info-value">{project.address}, {project.zip} {project.city}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Rapport dato</div>
            <div class="info-value">{current_date}</div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value">{building_count}</div>
            <div class="kpi-label">Bygninger</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">{total_area}</div>
            <div class="kpi-label">m² Samlet</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">{total_budget_millions}</div>
            <div class="kpi-label">Mio. DKK CAPEX</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">{red_flag_count}</div>
            <div class="kpi-label">Red Flags</div>
        </div>
    </div>

    <div class="section">
        <h2>Formål</h2>
        <p>
            Denne executive summary præsenterer de væsentligste konklusioner fra due diligence
            undersøgelsen af {project.name}. Rapporten er udarbejdet på vegne af {customer.name}
            med henblik på at give en overordnet vurdering af ejendommens tilstand og
            investeringsbehov.
        </p>
    </div>

    <div class="section">
        <h2>Hovedkonklusioner</h2>
        <ul>
            <li>
                <strong>Overordnet tilstand:</strong> Ejendommen fremstår generelt veldrevet
                med acceptabel vedligeholdelsesstandard for byggeår.
            </li>
            <li>
                <strong>Investeringsbehov:</strong> Der er identificeret et samlet CAPEX-behov
                på {total_budget} DKK over de næste 5-10 år.
            </li>
            <li>
                <strong>Kritiske forhold:</strong> {red_flag_count} områder kræver særlig
                opmærksomhed og prioritering.
            </li>
            <li>
                <strong>OPEX:</strong> Årlige driftsomkostninger estimeres til {total_opex} DKK.
            </li>
        </ul>
    </div>

    {if red_flag_count > 0}
    <div class="section">
        <h2>Kritiske Forhold</h2>
        <p>Følgende forhold kræver umiddelbar opmærksomhed:</p>
        <ul>
        {foreach red_flags limit="5"}
            <li class="priority-{flag.priority}">
                [{flag.priority}] {flag.description} - {flag.building_name}
            </li>
        {/foreach}
        </ul>
    </div>
    {/if}

    <div class="section">
        <h2>Anbefalinger</h2>

        <div class="recommendation">
            <strong>Kortsigtet (0-2 år):</strong>
            <ul>
                <li>Adresser alle red flags med høj prioritet</li>
                <li>Gennemfør akut vedligeholdelse af kritiske bygningsdele</li>
                <li>Etabler systematisk inspektionsplan</li>
            </ul>
        </div>

        <div class="recommendation">
            <strong>Mellemsigtet (2-5 år):</strong>
            <ul>
                <li>Implementer planlagt vedligeholdelse i henhold til budget</li>
                <li>Prioriter energioptimeringer med kort tilbagebetalingstid</li>
                <li>Overvej renovering af ældre bygningsdele</li>
            </ul>
        </div>

        <div class="recommendation">
            <strong>Langsigtet (5-10 år):</strong>
            <ul>
                <li>Planlæg større renoveringer og moderniseringer</li>
                <li>Vurder muligheder for bæredygtige energiløsninger</li>
                <li>Overvej strategiske opgraderinger for at øge ejendommens værdi</li>
            </ul>
        </div>
    </div>

    <div class="section">
        <h2>Næste Skridt</h2>
        <ol>
            <li>Gennemgå denne executive summary med relevante interessenter</li>
            <li>Prioriter indsatsområder baseret på budget og risiko</li>
            <li>Udarbejd detaljeret implementeringsplan</li>
            <li>Etabler overvågning af kritiske områder</li>
            <li>Planlæg opfølgende inspektioner</li>
        </ol>
    </div>

    <div class="footer">
        <p>
            Dette dokument er en del af den samlede due diligence rapport.<br>
            Se fuld rapport for detaljerede fund, billeder og tekniske specifikationer.
        </p>
        <p>
            Genereret: {current_date} | DueDiligence v2.0<br>
            {customer.name}
        </p>
    </div>
</body>
</html>
