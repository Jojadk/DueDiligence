<?php
// install.php - Run this once to install options (Postgres Version)
require_once 'index.php'; // Load constants

try {
    // 1. Connect to template1 to check/create database
    $dsnMaster = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=template1";
    $pdoMaster = new PDO($dsnMaster, DB_USER, DB_PASS);
    $pdoMaster->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if DB exists
    $stmt = $pdoMaster->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
    $stmt->execute([DB_NAME]);

    if (!$stmt->fetch()) {
        echo "Creating database " . DB_NAME . "...<br>";
        $pdoMaster->exec("CREATE DATABASE \"" . DB_NAME . "\"");
    } else {
        echo "Database " . DB_NAME . " already exists.<br>";
    }

    // 2. Connect to the actual database
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to Database.<br>";

    // 3. Import Schema
    $sql = file_get_contents(__DIR__ . '/schema_pgsql.sql');

    // Split by semicolon? PDO exec might handle multiple.
    $pdo->exec($sql);

    echo "Schema Imported Successfully.<br>";
    echo "You can now <a href='index.php'>Login</a>.";

} catch (PDOException $e) {
    echo "Installation Failed: " . $e->getMessage();
}
