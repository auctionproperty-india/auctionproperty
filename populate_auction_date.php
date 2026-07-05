<?php
require_once 'db.php';

echo "<h2>🔧 Populating auction_date from auction_start_time...</h2>";

try {
    // First, ensure column exists
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_date DATE");
    echo "✅ Column 'auction_date' added (if not existed).<br>";

    // Fetch all properties with auction_start_time not null/empty
    $stmt = $pdo->query("SELECT id, auction_start_time FROM properties WHERE auction_start_time IS NOT NULL AND auction_start_time != ''");
    $properties = $stmt->fetchAll();

    $updated = 0;
    foreach ($properties as $prop) {
        $date_str = trim($prop['auction_start_time']);
        // Example format: "Thu, 02 Jul 2026 12:00 AM"
        // Extract date part like "02 Jul 2026"
        if (preg_match('/\d{1,2} \w{3} \d{4}/', $date_str, $matches)) {
            $date_part = $matches[0];
            // Parse with DateTime
            $date_obj = DateTime::createFromFormat('d M Y', $date_part);
            if ($date_obj) {
                $formatted = $date_obj->format('Y-m-d');
                // Update only if not already set
                $pdo->prepare("UPDATE properties SET auction_date = ? WHERE id = ? AND auction_date IS NULL")->execute([$formatted, $prop['id']]);
                $updated++;
            }
        }
    }

    echo "✅ Updated $updated properties with auction_date.<br>";

    // Show sample
    $sample = $pdo->query("SELECT id, title, auction_start_time, auction_date FROM properties LIMIT 5")->fetchAll();
    echo "<h4>Sample Data:</h4>";
    echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>Title</th><th>auction_start_time</th><th>auction_date</th></tr>";
    foreach ($sample as $row) {
        echo "<tr><td>{$row['id']}</td><td>{$row['title']}</td><td>{$row['auction_start_time']}</td><td>{$row['auction_date']}</td></tr>";
    }
    echo "</table>";

    echo "<hr><p style='color:green; font-weight:bold;'>Now you can use Today's Auctions correctly.</p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
