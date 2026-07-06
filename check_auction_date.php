<?php
require_once 'db.php';

echo "<h2>🔍 Check auction_date for properties</h2>";

$props = $pdo->query("SELECT id, title, auction_date, auction_start_time FROM properties ORDER BY id DESC LIMIT 10")->fetchAll();

if (count($props) > 0) {
    echo "<table border='1' cellpadding='8'>
            <tr><th>ID</th><th>Title</th><th>auction_date</th><th>auction_start_time</th></tr>";
    foreach ($props as $p) {
        echo "<tr>
                <td>{$p['id']}</td>
                <td>{$p['title']}</td>
                <td>" . ($p['auction_date'] ? $p['auction_date'] : '<span style="color:red;">NULL</span>') . "</td>
                <td>{$p['auction_start_time']}</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "No properties found.";
}
?>
