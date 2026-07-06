<?php
require_once 'db.php';
require_once 'functions.php';

if(!isset($_SESSION['user_id'])) exit;

$text = $_POST['text'] ?? '';
if ($text) {
    logActivity($pdo, $_SESSION['user_id'], 'copy_data', 'Copied: ' . substr($text, 0, 500));
}
?>
