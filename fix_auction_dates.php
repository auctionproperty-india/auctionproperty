<?php
require_once 'db.php';

echo "<h1>🔧 Fix Auction Dates</h1>";

try {
    // 1. Check how many properties have NULL auction_date
    $stmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE auction_date IS NULL");
    $missing = $stmt->fetchColumn();
    echo "<p>📊 Properties with NULL auction_date: <strong>$missing</strong></p>";
    
    if ($missing == 0) {
        echo "<p style='color:green;'>✅ All properties have auction_date set!</p>";
        echo "<p><a href='/'>Go to Homepage</a></p>";
        exit;
    }
    
    // 2. Show sample of missing properties
    $stmt = $pdo->query("SELECT id, title, auction_start_time FROM properties WHERE auction_date IS NULL LIMIT 5");
    $samples = $stmt->fetchAll();
    
    echo "<h2>📋 Sample of missing properties:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>auction_start_time</th></tr>";
    foreach ($samples as $row) {
        echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['title']) . "</td><td>" . htmlspecialchars($row['auction_start_time']) . "</td></tr>";
    }
    echo "</table>";
    
    // 3. Fix: Update auction_date from auction_start_time (where auction_start_time is not null)
    $stmt = $pdo->prepare("
        UPDATE properties 
        SET auction_date = CAST(auction_start_time AS DATE) 
        WHERE auction_date IS NULL 
        AND auction_start_time IS NOT NULL 
        AND auction_start_time != ''
    ");
    $stmt->execute();
    $updated = $stmt->rowCount();
    
    echo "<p style='color:green;'>✅ Updated <strong>$updated</strong> properties with auction_date from auction_start_time!</p>";
    
    // 4. Check remaining NULL
    $stmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE auction_date IS NULL");
    $remaining = $stmt->fetchColumn();
    echo "<p>📊 Remaining properties with NULL auction_date: <strong>$remaining</strong></p>";
    
    if ($remaining == 0) {
        echo "<p style='color:green;'>✅ All fixed! <a href='/'>Go to Homepage</a></p>";
    } else {
        echo "<p style='color:orange;'>⚠️ Still $remaining properties have NULL auction_date. You may need to set them manually.</p>";
        echo "<p>Example: <code>UPDATE properties SET auction_date = '2026-08-30' WHERE id = 1;</code></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
