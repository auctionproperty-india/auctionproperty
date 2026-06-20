<?php
require_once 'db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        data TEXT NOT NULL,
        access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Sessions table created successfully! <br>";
    echo "Now your sessions will persist across deployments.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
