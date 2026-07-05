<?php
require_once 'db.php';

try {
    $pdo->exec("DEALLOCATE ALL");
    echo "✅ All cached plans cleared. Now refresh your home page.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
