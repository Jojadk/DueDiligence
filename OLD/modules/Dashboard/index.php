<?php
// Dashboard Content Only
?>
<div class="stats-grid">
    <div class="stat-card">
        <h3>Projects</h3>
        <p class="number"><?= $projectCount ?></p>
    </div>
    <div class="stat-card">
        <h3>Elements</h3>
        <p class="number"><?= $elementCount ?></p>
    </div>
    <div class="stat-card">
        <h3>Users</h3>
        <p class="number"><?= $userCount ?></p>
    </div>
</div>

<div class="panel mt-4">
    <div class="panel-header">System Health</div>
    <div class="panel-body">
        <p>Database Connected: <strong>Yes</strong></p>
        <p>Session ID: <?= session_id() ?></p>
        <p>Server Time: <?= date('Y-m-d H:i:s') ?></p>
    </div>
</div>