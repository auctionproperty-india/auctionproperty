<?php
require_once 'db.php';

echo "<h2>🔧 Fixing auction_date for all properties...</h2>";

try {
    // Step 1: Populate auction_date from auction_start_time where possible
    $properties = $pdo->query("SELECT id, auction_start_time FROM properties WHERE auction_date IS NULL")->fetchAll();
    $updated = 0;
    foreach ($properties as $prop) {
        $date_str = trim($prop['auction_start_time']);
        $new_date = null;
        if ($date_str) {
            if (preg_match('/\d{1,2} \w{3} \d{4}/', $date_str, $matches)) {
                $date_part = $matches[0];
                $date_obj = DateTime::createFromFormat('d M Y', $date_part);
                if ($date_obj) {
                    $new_date = $date_obj->format('Y-m-d');
                }
            }
        }
        if (!$new_date) {
            $new_date = date('Y-m-d');
        }
        $pdo->prepare("UPDATE properties SET auction_date = ? WHERE id = ?")->execute([$new_date, $prop['id']]);
        $updated++;
    }
    echo "✅ Updated $updated properties.<br>";

    // Step 2: Show updated list
    $props = $pdo->query("SELECT id, title, auction_date FROM properties ORDER BY id DESC LIMIT 10")->fetchAll();
    echo "<h4>Updated Properties (last 10):</h4>";
    echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>Title</th><th>auction_date</th></tr>";
    foreach ($props as $p) {
        echo "<tr><td>{$p['id']}</td><td>{$p['title']}</td><td>{$p['auction_date']}</td></tr>";
    }
    echo "</table>";

    echo "<hr><p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
