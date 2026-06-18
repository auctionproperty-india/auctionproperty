<?php
require_once 'db.php';
try {
    // पुरानी properties में city और type डिफॉल्ट अपडेट करें
    $pdo->exec("UPDATE properties SET city = 'Unknown' WHERE city IS NULL OR city = ''");
    $pdo->exec("UPDATE properties SET type = 'Flat' WHERE type IS NULL OR type = ''");
    echo "✅ पुरानी Properties ठीक कर दी गईं!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
