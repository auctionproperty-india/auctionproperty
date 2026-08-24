<?php
echo "✅ PHP is working!<br>";
require_once __DIR__ . '/db.php';
echo "✅ Database connected!<br>";
$stmt = $pdo->query("SELECT NOW()");
$row = $stmt->fetch();
echo "Server time: " . $row['now'];
?>
