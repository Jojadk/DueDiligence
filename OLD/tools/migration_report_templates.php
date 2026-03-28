<?php
require_once __DIR__ . '/../bootstrap.php';

use Core\Database;

$db = Database::getInstance();

echo "Creating report_templates table...\n";

// Check if table exists (Postgres specific)
// Or just CREATE TABLE IF NOT EXISTS
$sql = "
CREATE TABLE IF NOT EXISTS report_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT, -- HTML/Template
    css TEXT,     -- CSS styles
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

try {
    $db->query($sql);
    $db->execute();
    echo "Table report_templates created (or exists).\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
