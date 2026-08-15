<?php
// ============================================================
// 🔧 Add 'free_property_visit' column to 'packages' table
// ============================================================

require_once __DIR__ . '/db.php';

try {
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS free_property_visit VARCHAR(100)");
    echo "<div style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#dcfce7; border-radius:12px; border:2px solid #22c55e;'>";
    echo "<h2 style='color:#166534;'>✅ Column 'free_property_visit' added successfully!</h2>";
    echo "<p><a href='admin_packages.php' style='color:#2563eb;'>➡️ Go to Admin Packages</a></p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file after running.</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#fee2e2; border-radius:12px; border:2px solid #dc2626;'>";
    echo "<h2 style='color:#dc2626;'>❌ Error: " . $e->getMessage() . "</h2>";
    echo "</div>";
}
?>
