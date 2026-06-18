<?php require_once 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') die("Access Denied");
$id = $_GET['id'] ?? 0;
if($id) {
    $pdo->prepare("DELETE FROM properties WHERE id = ?")->execute([$id]);
}
header("Location: properties.php");
exit;
?>
