<?php
require_once __DIR__ . '/db.php';

// ✅ Drop existing table to avoid conflicts (safe for first run)
$pdo->exec("DROP TABLE IF EXISTS navigation_items CASCADE");

// ✅ Create table with UNIQUE constraint on label
$pdo->exec("
    CREATE TABLE navigation_items (
        id SERIAL PRIMARY KEY,
        label VARCHAR(100) NOT NULL UNIQUE,
        url VARCHAR(255) NOT NULL,
        icon VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )
");

// ✅ Insert default items – ON CONFLICT now works
$pdo->exec("
    INSERT INTO navigation_items (label, url, icon, display_order, is_active) VALUES
    ('Home', '/', 'fa-solid fa-house', 1, TRUE),
    ('Auctions', '/auctions.php', 'fa-solid fa-gavel', 2, TRUE),
    ('Properties', '/properties.php', 'fa-solid fa-building', 3, TRUE),
    ('About', '/about.php', 'fa-solid fa-circle-info', 4, TRUE),
    ('FAQ', '/faq.php', 'fa-solid fa-circle-question', 5, TRUE),
    ('Contact', '/contact.php', 'fa-solid fa-envelope', 6, TRUE)
    ON CONFLICT (label) DO NOTHING;
");

echo "✅ Navigation table created with default items!";
?>
