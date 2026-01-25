<h1>Tilstandsrapport - {project.name}</h1>

<h2>Projekt</h2>
<p>
    <strong>{project.name}</strong><br>
    {project.address}, {project.zip} {project.city}<br>
    Kunde: {customer.name}<br>
    Dato: {current_date}
</p>

<h2>Oversigt</h2>
<table>
    <tr>
        <th>Antal bygninger:</th>
        <td>{building_count}</td>
    </tr>
    <tr>
        <th>Samlet areal:</th>
        <td>{total_area} m²</td>
    </tr>
    <tr>
        <th>Estimeret investering:</th>
        <td>{total_budget} DKK</td>
    </tr>
    <tr>
        <th>Kritiske problemer:</th>
        <td>{red_flag_count}</td>
    </tr>
</table>

<h2>Bygninger</h2>
{foreach buildings}
<h3>{building.name}</h3>
<p>Type: {building.type} | Byggeår: {building.year_built} | Areal: {building.total_area} m²</p>
{/foreach}

{if red_flag_count > 0}
<h2>Kritiske Forhold</h2>
<ul>
{foreach red_flags}
    <li><strong>[{flag.priority}]</strong> {flag.description} ({flag.building_name})</li>
{/foreach}
</ul>
{/if}

<h2>Anbefaling</h2>
<p>Se fuld rapport for detaljerede anbefalinger og budget.</p>

<hr>
<p><small>Genereret: {current_date} | DueDiligence v2.0</small></p>
