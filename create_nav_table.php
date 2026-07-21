<?php
// ============================================================
// 📋 Create Navigation Settings Table
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

    // Insert default navigation items
    $pdo->exec("
        INSERT INTO navigation_items (label, url, icon, display_order, is_active) VALUES
        ('Home', '/', 'fa-solid fa-house', 1, TRUE),
        ('Auctions', '/auctions.php', 'fa-solid fa-gavel', 2, TRUE),
        ('Properties', '/properties.php', 'fa-solid fa-building', 3, TRUE),
        ('About', '/about.php', 'fa-solid fa-circle-info', 4, TRUE),
        ('FAQ', '/faq.php', 'fa-solid fa-circle-question', 5, TRUE),
        ('Contact', '/contact.php', 'fa-solid fa-envelope', 6, TRUE),
        ('Blog', '/blog.php', 'fa-solid fa-newspaper', 7, FALSE),
        ('Services', '/services.php', 'fa-solid fa-briefcase', 8, FALSE)
        ON CONFLICT (id) DO NOTHING
    ");

    echo "✅ Navigation table created and default items inserted!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
