<?php
// ============================================================
// 🔧 Add all incentive & feature columns to 'packages' table
// ============================================================

require_once __DIR__ . '/db.php';

$columns = [
    'validity' => 'VARCHAR(100)',
    'property_search' => 'VARCHAR(100)',
    'company_support' => 'VARCHAR(100)',
    'sales_team_support' => 'VARCHAR(100)',
    'self_refer_incentive' => 'VARCHAR(100)',
    'team_refer_incentive' => 'VARCHAR(100)',
    'property_sale_incentive' => 'VARCHAR(100)',
    'team_sale_incentive' => 'VARCHAR(100)'
];

try {
    foreach ($columns as $col => $type) {
        $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS $col $type");
    }
    echo "<div style='font-family:Arial; max-width:700px; margin:50px auto; padding:30px; background:#dcfce7; border-radius:12px; border:2px solid #22c55e;'>";
    echo "<h2 style='color:#166534;'>✅ All columns added successfully!</h2>";
    echo "<ul>";
    foreach (array_keys($columns) as $col) {
        echo "<li><strong>$col</strong> added.</li>";
    }
    echo "</ul>";
    echo "<p><a href='admin_packages.php' style='color:#2563eb;'>➡️ Go to Admin Packages</a></p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file after running.</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='font-family:Arial; max-width:700px; margin:50px auto; padding:30px; background:#fee2e2; border-radius:12px; border:2px solid #dc2626;'>";
    echo "<h2 style='color:#dc2626;'>❌ Error: " . $e->getMessage() . "</h2>";
    echo "</div>";
}
?>
