<?php
// ============================================================
// ✅ test_edit.php – Session और get_property.php Test
// ============================================================

require_once __DIR__ . '/db.php';

echo "<h3>🔍 Session Debug</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin') {
    echo "✅ You are logged in as admin.<br>";
    echo "Try editing a property now – it should work.<br>";
} else {
    echo "❌ You are NOT logged in as admin.<br>";
    echo "Please login again and try.<br>";
}
?>
