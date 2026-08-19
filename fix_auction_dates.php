<?php
require_once 'db.php';

echo "<h1>🔧 Fix Auction Dates (Using PHP strtotime)</h1>";

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
    
    // 2. Fetch all properties with NULL auction_date and non-empty auction_start_time
    $stmt = $pdo->query("
        SELECT id, auction_start_time 
        FROM properties 
        WHERE auction_date IS NULL 
        AND auction_start_time IS NOT NULL 
        AND auction_start_time != ''
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>📋 Found <strong>" . count($rows) . "</strong> properties with auction_start_time to process.</p>";
    
    $updated = 0;
    $failed = 0;
    $skipped = 0;
    
    foreach ($rows as $row) {
        $id = $row['id'];
        $start_time = trim($row['auction_start_time']);
        
        // Remove prefixes like "Time - "
        $start_time = preg_replace('/^Time\s*-\s*/', '', $start_time);
        $start_time = preg_replace('/^[A-Za-z]{3},\s*/', '', $start_time); // Remove "Mon, " etc.
        
        // Try to parse with strtotime
        $timestamp = strtotime($start_time);
        if ($timestamp !== false) {
            $date = date('Y-m-d', $timestamp);
            // Update
            $updateStmt = $pdo->prepare("UPDATE properties SET auction_date = ? WHERE id = ?");
            $updateStmt->execute([$date, $id]);
            $updated++;
        } else {
            // Try alternative: extract date like "13 Jul 2026" using regex
            preg_match('/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/', $start_time, $matches);
            if (count($matches) == 4) {
                $dateStr = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];
                $timestamp2 = strtotime($dateStr);
                if ($timestamp2 !== false) {
                    $date = date('Y-m-d', $timestamp2);
                    $updateStmt = $pdo->prepare("UPDATE properties SET auction_date = ? WHERE id = ?");
                    $updateStmt->execute([$date, $id]);
                    $updated++;
                    continue;
                }
            }
            // If still fails, set a default (e.g., today + 30 days) or log
            $failed++;
            echo "<p style='color:orange;'>⚠️ Could not parse: <code>" . htmlspecialchars($row['auction_start_time']) . "</code> (ID: $id)</p>";
        }
    }
    
    echo "<p style='color:green;'>✅ Updated <strong>$updated</strong> properties.</p>";
    echo "<p style='color:orange;'>⚠️ Failed to parse <strong>$failed</strong> properties.</p>";
    echo "<p style='color:blue;'>ℹ️ Skipped <strong>$skipped</strong> properties (no auction_start_time).</p>";
    
    // 4. Check remaining NULL
    $stmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE auction_date IS NULL");
    $remaining = $stmt->fetchColumn();
    echo "<p>📊 Remaining properties with NULL auction_date: <strong>$remaining</strong></p>";
    
    if ($remaining == 0) {
        echo "<p style='color:green;'>✅ All fixed! <a href='/'>Go to Homepage</a></p>";
    } else {
        echo "<p style='color:orange;'>⚠️ Still $remaining properties have NULL auction_date.</p>";
        echo "<p>You may need to manually set them.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
