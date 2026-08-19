<?php
require_once 'db.php';
echo "Session ID: " . session_id() . "<br>";
echo "Test value: " . ($_SESSION['test'] ?? 'NOT SET') . "<br>";
?>
