<?php
// ============================================================
// 🗑️ Delete User Property – Works for Admin & User
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($property_id <= 0) {
    $redirect = ($role == 'admin') ? 'admin_user_properties.php' : 'user_properties.php';
    header("Location: $redirect?msg=invalid_id");
    exit;
}

if ($role == 'admin') {
    // ✅ Admin: बिना ownership check के delete करें
    $delete = $pdo->prepare("DELETE FROM user_properties WHERE id = ?");
    $delete->execute([$property_id]);
    header("Location: admin_user_properties.php?msg=deleted");
    exit;
} else {
    // ✅ User: सिर्फ अपनी property delete कर सकता है
    $stmt = $pdo->prepare("SELECT id FROM user_properties WHERE id = ? AND user_id = ?");
    $stmt->execute([$property_id, $user_id]);
    if (!$stmt->fetch()) {
        header("Location: user_properties.php?msg=not_owner");
        exit;
    }
    $delete = $pdo->prepare("DELETE FROM user_properties WHERE id = ? AND user_id = ?");
    $delete->execute([$property_id, $user_id]);
    header("Location: user_properties.php?msg=deleted");
    exit;
}
