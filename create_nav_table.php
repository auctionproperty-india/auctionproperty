<?php
require_once __DIR__ . '/db.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS navigation_items (
        id SERIAL PRIMARY KEY,
        label VARCHAR(100) NOT NULL,
        url VARCHAR(255) NOT NULL,
        icon VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )
");

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

echo "✅ Navigation table ready!";
