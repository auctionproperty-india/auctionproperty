<?php
session_start();
echo "<pre>";
echo "SESSION DUMP:\n";
print_r($_SESSION);
echo "</pre>";

// Login status
if (isset($_SESSION['user_id'])) {
    echo "✅ User ID: " . $_SESSION['user_id'] . "<br>";
    echo "✅ Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
} else {
    echo "❌ No user logged in.\n";
}
?>
