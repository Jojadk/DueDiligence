<?php
/* Dashboard Module */

$stats = [
    'customers' => db_value("SELECT COUNT(*) FROM customers"),
    'projects' => db_value("SELECT COUNT(*) FROM projects"),
    'buildings' => db_value("SELECT COUNT(*) FROM buildings"),
    'elements' => db_value("SELECT COUNT(*) FROM building_elements"),
    'active_projects' => db_value("SELECT COUNT(*) FROM projects WHERE status = 'active'"),
    'total_capex' => db_value("SELECT COALESCE(SUM(capex), 0) FROM building_elements")
];

$recentProjects = db_query("SELECT p.*, c.name as customer_name FROM projects p LEFT JOIN customers c ON p.customer_id = c.id ORDER BY p.created_at DESC LIMIT 5");
$urgentElements = db_query("SELECT be.*, b.name as building_name FROM building_elements be LEFT JOIN buildings b ON be.building_id = b.id WHERE be.urgency IN ('high', 'critical') ORDER BY CASE be.urgency WHEN 'critical' THEN 1 WHEN 'high' THEN 2 END, be.time_horizon LIMIT 10");

load_template(template_path('dashboard', 'template'), compact('stats', 'recentProjects', 'urgentElements'));
