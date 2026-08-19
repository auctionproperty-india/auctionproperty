<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Test value: " . ($_SESSION['test'] ?? 'NOT SET') . "<br>";
?>
