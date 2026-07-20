<?php
// ============================================================
// 🔧 Fix PostgreSQL Sequences after manual ID inserts
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Sequences</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Fix PostgreSQL Sequences</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected</div>";

    // List of tables with SERIAL columns
    $tables = [
        'user_activity_log' => 'id',
        'user_spins' => 'id',
        'subscriptions' => 'id',
        'wallet_transactions' => 'id',
        'kyc_documents' => 'id',
        'support_tickets' => 'id',
        'user_properties' => 'id',
        'user_referral_earnings' => 'id',
        'account_entries' => 'id',
        'properties' => 'id',
        'users' => 'id',
        'packages' => 'id',
        'settings' => 'id'
    ];

    echo "<h2>📋 Resetting sequences...</h2>";
    echo "<table><tr><th>Table</th><th>Sequence</th><th>New Start</th><th>Status</th></tr>";

    foreach ($tables as $table => $column) {
        try {
            // Get max ID
            $stmt = $pdo->query("SELECT COALESCE(MAX($column), 0) + 1 FROM $table");
            $max_id = $stmt->fetchColumn();
            if (!$max_id) $max_id = 1;
            
            // Get sequence name
            $seq_stmt = $pdo->prepare("
                SELECT pg_get_serial_sequence(:table, :column)
            ");
            $seq_stmt->execute(['table' => $table, 'column' => $column]);
            $seq_name = $seq_stmt->fetchColumn();
            
            if ($seq_name) {
                // Reset sequence
                $pdo->exec("SELECT setval('$seq_name', $max_id, false)");
                echo "<tr><td>$table</td><td>$seq_name</td><td>$max_id</td><td style='color:green;'>✅</td></tr>";
            } else {
                echo "<tr><td>$table</td><td>No sequence</td><td>-</td><td style='color:orange;'>⚠️</td></tr>";
            }
        } catch (PDOException $e) {
            echo "<tr><td>$table</td><td>Error</td><td>-</td><td style='color:red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td></tr>";
        }
    }
    echo "</table>";

    echo "<div class='success'>🎉 Sequences reset successfully!</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
