<?php /* Dashboard */ ?>

<header class="page-header">
    <div><h1><?= icon('home', 32) ?> Dashboard</h1><p class="subtitle">Oversigt over dit system</p></div>
</header>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><?= icon('users', 32) ?></div><div class="stat-info"><div class="stat-value"><?= number_format($stats['customers'], 0, ',', '.') ?></div><div class="stat-label">Kunder</div></div><a href="#" onclick="navigate('customer'); return false;" class="stat-link">Se alle →</a></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('folder', 32) ?></div><div class="stat-info"><div class="stat-value"><?= number_format($stats['projects'], 0, ',', '.') ?></div><div class="stat-label">Projekter</div></div><a href="#" onclick="navigate('project'); return false;" class="stat-link">Se alle →</a></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('building', 32) ?></div><div class="stat-info"><div class="stat-value"><?= number_format($stats['buildings'], 0, ',', '.') ?></div><div class="stat-label">Bygninger</div></div><a href="#" onclick="navigate('building'); return false;" class="stat-link">Se alle →</a></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('list', 32) ?></div><div class="stat-info"><div class="stat-value"><?= number_format($stats['elements'], 0, ',', '.') ?></div><div class="stat-label">Bygningsdele</div></div><a href="#" onclick="navigate('building_element'); return false;" class="stat-link">Se alle →</a></div>
</div>

<div class="dashboard-row">
    <div class="dashboard-card">
        <h2><?= icon('clock', 24) ?> Seneste Projekter</h2>
        <?php if (empty($recentProjects)): ?>
        <p class="text-muted">Ingen projekter endnu</p>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($recentProjects as $p): 
            $statusMap = ['planning'=>'Planlægning','active'=>'Aktiv','on_hold'=>'På vent','completed'=>'Afsluttet','archived'=>'Arkiveret'];
            $statusClass = ['planning'=>'badge-warning','active'=>'badge-success','on_hold'=>'badge-secondary','completed'=>'badge-info','archived'=>'badge-muted'];
            ?>
            <a href="#" onclick="navigate('project', {id: <?= $p['id'] ?>}); return false;" class="list-item">
                <div><strong><?= esc_html($p['name']) ?></strong><small class="text-muted"><?= esc_html($p['customer_name']) ?></small></div>
                <span class="badge <?= $statusClass[$p['status']??'badge-secondary'] ?>"><?= $statusMap[$p['status']]??$p['status'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-card">
        <h2><?= icon('alert', 24) ?> Hastende Bygningsdele</h2>
        <?php if (empty($urgentElements)): ?>
        <p class="text-muted">Ingen hastende elementer</p>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($urgentElements as $e): 
            $urgencyMap = ['high'=>'Høj','critical'=>'Kritisk'];
            $urgencyClass = ['high'=>'badge-warning','critical'=>'badge-error'];
            ?>
            <a href="#" onclick="navigate('building_element', {id: <?= $e['id'] ?>}); return false;" class="list-item">
                <div><strong><?= esc_html($e['name']) ?></strong><small class="text-muted"><?= esc_html($e['building_name']) ?> • <?= $e['time_horizon'] ?> år</small></div>
                <span class="badge <?= $urgencyClass[$e['urgency']] ?>"><?= $urgencyMap[$e['urgency']] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px;}
.stat-card {background: white; border-radius: 8px; padding: 24px; border: 1px solid var(--color-gray-200); display: flex; flex-direction: column; gap: 16px;}
.stat-card .stat-icon {color: var(--color-primary);}
.stat-info {flex: 1;}
.stat-value {font-size: 32px; font-weight: 700; color: var(--color-gray-900);}
.stat-label {font-size: 14px; color: var(--color-gray-600);}
.stat-link {color: var(--color-primary); font-size: 14px; text-decoration: none;}
.stat-link:hover {text-decoration: underline;}
.dashboard-row {display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;}
.dashboard-card {background: white; border-radius: 8px; padding: 24px; border: 1px solid var(--color-gray-200);}
.dashboard-card h2 {display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 600; margin-bottom: 16px;}
.list-group {display: flex; flex-direction: column; gap: 8px;}
.list-item {display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--color-gray-50); border-radius: 6px; text-decoration: none; color: inherit; transition: background 0.15s;}
.list-item:hover {background: var(--color-gray-100);}
.list-item strong {display: block; font-weight: 600; margin-bottom: 4px;}
.list-item small {display: block; font-size: 12px;}
</style>
