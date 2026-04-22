<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo = get_pdo();
    $sql = "ALTER TABLE feedback 
        ADD COLUMN age INT DEFAULT NULL AFTER phone,
        ADD COLUMN bug_date DATE DEFAULT NULL AFTER subject,
        ADD COLUMN source VARCHAR(100) DEFAULT NULL AFTER message,
        ADD COLUMN rating INT DEFAULT 5 AFTER source,
        ADD COLUMN interests VARCHAR(255) DEFAULT NULL AFTER rating,
        ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER interests,
        ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER country;";
    $pdo->exec($sql);
    echo "Columns added to feedback table successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
