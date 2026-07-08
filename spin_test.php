<?php
require_once 'db.php';
require_once 'functions.php';

if(!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];
echo "<h2>Spin Test for User ID: $user_id</h2>";

$result = performSpin($pdo, $user_id);
echo "<pre>";
print_r($result);
echo "</pre>";

// Also show current spin data
$data = getUserSpinData($pdo, $user_id);
echo "<h3>Current Spin Data:</h3>";
print_r($data);
?>
