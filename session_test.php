<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
$_SESSION['test'] = 'working';
echo "Test session set: " . $_SESSION['test'] . "<br>";
echo "<a href='session_test_check.php'>Check session on next page</a>";
?>
