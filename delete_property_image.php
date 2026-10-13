<?php
// ============================================================
// 🗑️ Delete Individual Property Image
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$image_id = (int)($_GET['id'] ?? 0);
$property_id = (int)($_GET['prop'] ?? 0);

if ($image_id <= 0 || $property_id <= 0) {
    header("Location: properties.php?edit=" . $property_id);
    exit;
}

try {
    // Get image path
    $stmt = $pdo->prepare("SELECT image_url FROM property_images WHERE id = ? AND property_id = ?");
    $stmt->execute([$image_id, $property_id]);
    $img = $stmt->fetchColumn();

    if ($img) {
        // Delete file from disk
        $full_path = __DIR__ . '/' . ltrim($img, '/');
        if (file_exists($full_path) && is_file($full_path)) {
            @unlink($full_path);
        }

        // Delete DB row
        $pdo->prepare("DELETE FROM property_images WHERE id = ?")->execute([$image_id]);

        // Update main image_url if it was the one deleted
        $pdo->prepare("
            UPDATE properties 
            SET image_url = (
                SELECT image_url FROM property_images 
                WHERE property_id = ? 
                ORDER BY display_order ASC, id ASC LIMIT 1
            ) 
            WHERE id = ?
        ")->execute([$property_id, $property_id]);
    }

    header("Location: properties.php?edit=" . $property_id . "&msg=image_deleted");
    exit;

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
