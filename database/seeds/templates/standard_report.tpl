<h1>Due Diligence Rapport</h1>

<h2>Projekt Information</h2>
<table>
    <tr>
        <th>Projektnavn:</th>
        <td>{project.name}</td>
    </tr>
    <tr>
        <th>Adresse:</th>
        <td>{project.address}, {project.zip} {project.city}</td>
    </tr>
    <tr>
        <th>BBR-nummer:</th>
        <td>{project.bbr_number}</td>
    </tr>
    <tr>
        <th>Kunde:</th>
        <td>{customer.name}</td>
    </tr>
    <tr>
        <th>Dato:</th>
        <td>{current_date}</td>
    </tr>
</table>

<h2>Executive Summary</h2>
<p>Denne rapport præsenterer resultaterne af due diligence undersøgelsen af {project.name}.</p>

<h3>Nøgletal</h3>
<ul>
    <li>Antal bygninger: {building_count}</li>
    <li>Samlet areal: {total_area} m²</li>
    <li>Estimeret CAPEX: {total_budget} DKK</li>
    <li>Årlig OPEX: {total_opex} DKK</li>
    <li>Antal red flags: {red_flag_count}</li>
</ul>

<h2>Bygninger</h2>
{foreach buildings}
<h3>{building.name}</h3>
<table>
    <tr>
        <th>Type:</th>
        <td>{building.type}</td>
    </tr>
    <tr>
        <th>Byggeår:</th>
        <td>{building.year_built}</td>
    </tr>
    <tr>
        <th>Areal:</th>
        <td>{building.total_area} m²</td>
    </tr>
    <tr>
        <th>Etager:</th>
        <td>{building.floors}</td>
    </tr>
</table>

<h4>Tilstand</h4>
<p>{building.condition_description}</p>
{/foreach}

<h2>Budget Oversigt</h2>
<table>
    <thead>
        <tr>
            <th>Kategori</th>
            <th>Beskrivelse</th>
            <th>Beløb (DKK)</th>
        </tr>
    </thead>
    <tbody>
        {foreach budget_items}
        <tr>
            <td>{item.category}</td>
            <td>{item.description}</td>
            <td align="right">{item.amount}</td>
        </tr>
        {/foreach}
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2">Total CAPEX:</th>
            <th align="right">{total_budget} DKK</th>
        </tr>
    </tfoot>
</table>

<h2>Red Flags</h2>
{if red_flag_count > 0}
<table>
    <thead>
        <tr>
            <th>Prioritet</th>
            <th>Beskrivelse</th>
            <th>Bygning</th>
        </tr>
    </thead>
    <tbody>
        {foreach red_flags}
        <tr class="priority-{flag.priority}">
            <td>{flag.priority}</td>
            <td>{flag.description}</td>
            <td>{flag.building_name}</td>
        </tr>
        {/foreach}
    </tbody>
</table>
{else}
<p>Ingen kritiske problemer identificeret.</p>
{/if}

<h2>Konklusion</h2>
<p>Baseret på denne undersøgelse anbefales følgende:</p>
<ul>
    <li>Gennemfør planlagt vedligeholdelse i henhold til budget</li>
    <li>Prioriter red flags med høj prioritet</li>
    <li>Overvåg udvikling af identificerede problemer</li>
</ul>

<hr>
<p><small>Genereret: {current_date} | DueDiligence v2.0</small></p>
