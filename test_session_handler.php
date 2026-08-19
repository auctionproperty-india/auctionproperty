<?php
require_once 'db.php';
echo "Session ID: " . session_id() . "<br>";
$_SESSION['test'] = 'working';
echo "Test value set: " . $_SESSION['test'] . "<br>";
echo "<a href='test.php'>Check on test.php</a>";
?>
