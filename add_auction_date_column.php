<?php
require_once 'db.php';

echo "<h2>🔧 Adding 'auction_date' column to properties table...</h2>";

try {
    // Step 1: Add column if not exists
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_date DATE");
    echo "✅ Column 'auction_date' added successfully.<br>";

    // Step 2: Populate from auction_start_time (extract date part)
    // Example auction_start_time: "Thu, 02 Jul 2026 12:00 AM"
    // We extract "02 Jul 2026" and convert to DATE
    $pdo->exec("
        UPDATE properties 
        SET auction_date = NULLIF(TO_DATE(
            SUBSTRING(auction_start_time FROM '\\d{2} \\w{3} \\d{4}'), 
            'DD Mon YYYY'
        ), '') 
        WHERE auction_start_time IS NOT NULL AND auction_start_time != ''
    ");
    echo "✅ Populated auction_date from existing auction_start_time data.<br>";

    // Step 3: Show sample
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
