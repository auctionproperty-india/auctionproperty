<?php
// ============================================================
// 📢 Admin Notification Manager
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

// Fetch existing notification (only one active at a time, but we'll manage the latest)
$stmt = $pdo->query("SELECT * FROM admin_notifications ORDER BY id DESC LIMIT 1");
$notification = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $message_text = trim($_POST['message']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_url = $notification['image_url'] ?? ''; // keep old if not updated

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/admin_notifications/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'notif_' . time() . '.' . $ext;
        $target_path = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_url = $target_path;
        } else {
            $error = "❌ Image upload failed.";
        }
    }

    if (empty($title)) {
        $error = "❌ Title is required.";
    } else {
        if ($notification) {
            // Update existing
            $update = $pdo->prepare("UPDATE admin_notifications SET title = ?, message = ?, image_url = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$title, $message_text, $image_url, $is_active, $notification['id']]);
            $message = "✅ Notification updated successfully!";
        } else {
            // Insert new
            $insert = $pdo->prepare("INSERT INTO admin_notifications (title, message, image_url, is_active) VALUES (?, ?, ?, ?)");
            $insert->execute([$title, $message_text, $image_url, $is_active]);
            $message = "✅ Notification created successfully!";
        }
        // Refresh data
        $stmt = $pdo->query("SELECT * FROM admin_notifications ORDER BY id DESC LIMIT 1");
        $notification = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Notification Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2><i class="fas fa-bullhorn me-2"></i>Manage Dashboard Popup Notification</h2>
        <?php if($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <div class="card p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($notification['title'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="4"><?= htmlspecialchars($notification['message'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Image (optional)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if(!empty($notification['image_url'])): ?>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($notification['image_url']) ?>" style="max-height:150px; border-radius:8px;">
                            <small class="d-block text-muted">Current image (upload new to replace)</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" <?= (isset($notification['is_active']) && $notification['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activeCheck">Active (show on user dashboard)</label>
                </div>
                <button type="submit" class="btn btn-primary">Save Notification</button>
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </form>
        </div>
        <div class="mt-3">
            <p><strong>Preview:</strong></p>
            <?php if($notification && $notification['is_active']): ?>
                <div class="alert alert-info">
                    <h5><?= htmlspecialchars($notification['title']) ?></h5>
                    <p><?= nl2br(htmlspecialchars($notification['message'] ?? '')) ?></p>
                    <?php if(!empty($notification['image_url'])): ?>
                        <img src="<?= htmlspecialchars($notification['image_url']) ?>" style="max-height:200px; border-radius:8px;">
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">No active notification.</div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
