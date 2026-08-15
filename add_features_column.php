<?php
// ============================================================
// 🔧 Add 'features' column to 'packages' table – PostgreSQL
// ============================================================

require_once __DIR__ . '/db.php';

try {
    // PostgreSQL में IF NOT EXISTS supported है
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS features TEXT");
    echo "<div style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#dcfce7; border-radius:12px; border:2px solid #22c55e;'>";
    echo "<h2 style='color:#166534;'>✅ Column 'features' added successfully!</h2>";
    echo "<p>Now you can use the admin package management to add features.</p>";
    echo "<p><a href='admin_packages.php' style='color:#2563eb;'>➡️ Go to Admin Packages</a></p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file after running.</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#fee2e2; border-radius:12px; border:2px solid #dc2626;'>";
    echo "<h2 style='color:#dc2626;'>❌ Error: " . $e->getMessage() . "</h2>";
    echo "</div>";
}
?>
