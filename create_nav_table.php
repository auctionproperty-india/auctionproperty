<?php
require_once __DIR__ . '/db.php';

// Create table
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

// Insert default items only if they don't exist
$defaults = [
    ['Home', '/', 'fa-solid fa-house', 1],
    ['Auctions', '/auctions.php', 'fa-solid fa-gavel', 2],
    ['Properties', '/properties.php', 'fa-solid fa-building', 3],
    ['About', '/about.php', 'fa-solid fa-circle-info', 4],
    ['FAQ', '/faq.php', 'fa-solid fa-circle-question', 5],
    ['Contact', '/contact.php', 'fa-solid fa-envelope', 6]
];

foreach ($defaults as $item) {
    $stmt = $pdo->prepare("
        INSERT INTO navigation_items (label, url, icon, display_order, is_active)
        SELECT ?, ?, ?, ?, TRUE
        WHERE NOT EXISTS (SELECT 1 FROM navigation_items WHERE label = ?)
    ");
    $stmt->execute([$item[0], $item[1], $item[2], $item[3], $item[0]]);
}

echo "✅ Navigation table created and default items inserted!";
?>
