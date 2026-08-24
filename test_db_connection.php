<?php
require_once __DIR__ . '/db.php';
echo "✅ Database connected!";
$stmt = $pdo->query("SELECT NOW()");
$row = $stmt->fetch();
echo " Server time: " . $row['now'];
?>
