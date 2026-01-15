<?php
require_once __DIR__ . '/../bootstrap.php';
use Core\Database;

$db = Database::getInstance();

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS project_snapshots (
            id SERIAL PRIMARY KEY,
            project_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            title VARCHAR(255),
            description TEXT,
            total_price DECIMAL(15,2) DEFAULT 0,
            stats_summary TEXT, -- JSON
            db_dump TEXT, -- Large JSON
            files_path VARCHAR(255)
        )
    ");
    $db->execute();
    echo "Table project_snapshots created/verified successfully.<br>";
    echo "<a href='/'>Go Home</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
