<?php
require_once 'db.php';

echo "<h2>🔍 All Properties in Database</h2>";

$stmt = $pdo->query("SELECT id, title, status, city, price FROM properties ORDER BY id DESC LIMIT 20");
$props = $stmt->fetchAll();

if(count($props) > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>City</th><th>Price</th></tr>";
    foreach($props as $p) {
        echo "<tr>
                <td>{$p['id']}</td>
                <td>{$p['title']}</td>
                <td><strong>{$p['status']}</strong></td>
                <td>{$p['city']}</td>
                <td>{$p['price']}</td>
              </tr>";
    }
    echo "</table>";
    echo "<p>Total: " . count($props) . " properties shown (last 20).</p>";
} else {
    echo "<p>❌ No properties found in database.</p>";
}

echo "<p>If you see your new property with status <strong>NULL</strong> or not 'available', then we need to fix the INSERT query.</p>";
?>
