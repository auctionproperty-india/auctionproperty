<?php
// ============================================================
// 🎯 Navigation Manager (Admin Only)
// ============================================================

// ✅ session_start() only if not already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ अब डेटाबेस और functions include करें
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ✅ अगर admin नहीं है तो redirect करें – इससे पहले कोई output नहीं होना चाहिए
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- बाकी सारा कोड (Add/Edit/Delete) ----
// (यहाँ आपका पिछला admin_navigation.php का पूरा कोड आएगा)
// मैंने नीचे पूरा कोड फिर से दे दिया है – इसे कॉपी करें और अपनी फाइल में replace करें

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("INSERT INTO navigation_items (label, url, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['label'], $_POST['url'], $_POST['icon'], (int)$_POST['display_order'], isset($_POST['is_active']) ? 'TRUE' : 'FALSE']);
        header("Location: admin_navigation.php?msg=added");
        exit;
    }
    if (isset($_POST['update'])) {
        $stmt = $pdo->prepare("UPDATE navigation_items SET label=?, url=?, icon=?, display_order=?, is_active=? WHERE id=?");
        $stmt->execute([$_POST['label'], $_POST['url'], $_POST['icon'], (int)$_POST['display_order'], isset($_POST['is_active']) ? 'TRUE' : 'FALSE', (int)$_POST['id']]);
        header("Location: admin_navigation.php?msg=updated");
        exit;
    }
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM navigation_items WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header("Location: admin_navigation.php?msg=deleted");
        exit;
    }
}

// Get all navigation items
$items = $pdo->query("SELECT * FROM navigation_items ORDER BY display_order")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Navigation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; }
        .container { max-width: 1200px; margin: auto; padding: 30px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 24px; margin-bottom: 20px; }
        .table { color: #e2e8f0; }
        .table th { border-bottom: 1px solid #334155; color: #94a3b8; }
        .table td { border-bottom: 1px solid #1e293b; vertical-align: middle; }
        .btn { border-radius: 8px; font-weight: 600; }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; border: none; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-success { background: #16a34a; border: none; }
        .btn-success:hover { background: #15803d; }
        .badge-active { background: #16a34a; }
        .badge-inactive { background: #dc2626; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; }
        .form-control:focus { background: #0f172a; border-color: #2563eb; color: #e2e8f0; box-shadow: none; }
        .form-label { color: #94a3b8; font-weight: 600; }
        h1 i { color: #fbbf24; }
        .back-link { color: #94a3b8; text-decoration: none; }
        .back-link:hover { color: #fbbf24; }
        .footer-note { border-top: 1px solid #334155; padding-top: 20px; margin-top: 20px; color: #94a3b8; }
        .footer-note a { color: #60a5fa; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-bars"></i> Navigation Manager</h1>
        <a href="admin_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_GET['msg'] == 'added' ? '✅ Navigation item added!' : ($_GET['msg'] == 'updated' ? '✅ Updated!' : '🗑️ Deleted!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add New -->
    <div class="card">
        <h5><i class="fas fa-plus-circle text-success"></i> Add New Navigation Item</h5>
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Label</label>
                <input type="text" name="label" class="form-control" placeholder="e.g. About" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">URL</label>
                <input type="text" name="url" class="form-control" placeholder="/about.php" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Icon (FontAwesome)</label>
                <input type="text" name="icon" class="form-control" placeholder="fa-solid fa-circle-info">
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <input type="number" name="display_order" class="form-control" value="99">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" checked>
                    <label class="form-check-label text-light">Active</label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" name="add" class="btn btn-success"><i class="fas fa-plus"></i> Add</button>
            </div>
        </form>
    </div>

    <!-- List -->
    <div class="card">
        <h5><i class="fas fa-list"></i> Navigation Items</h5>
        <table class="table">
            <thead>
                <tr><th>#</th><th>Icon</th><th>Label</th><th>URL</th><th>Order</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><i class="<?= htmlspecialchars($item['icon'] ?: 'fa-solid fa-link') ?>"></i></td>
                    <td><?= htmlspecialchars($item['label']) ?></td>
                    <td><code><?= htmlspecialchars($item['url']) ?></code></td>
                    <td><?= $item['display_order'] ?></td>
                    <td>
                        <span class="badge <?= $item['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $item['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#edit-<?= $item['id'] ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?delete=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this item?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <tr><td colspan="7">
                    <div class="collapse" id="edit-<?= $item['id'] ?>">
                        <form method="POST" class="row g-3 p-3" style="background: #0f172a; border-radius: 8px;">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <div class="col-md-2">
                                <label class="form-label">Label</label>
                                <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($item['label']) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">URL</label>
                                <input type="text" name="url" class="form-control" value="<?= htmlspecialchars($item['url']) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Icon</label>
                                <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars($item['icon']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?= $item['display_order'] ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" <?= $item['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label text-light">Active</label>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="update" class="btn btn-primary w-100"><i class="fas fa-save"></i> Update</button>
                            </div>
                        </form>
                    </div>
                </td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="text-muted mt-2"><small>💡 Total: <?= count($items) ?> items</small></div>
    </div>

    <!-- How to Use (Footer) -->
    <div class="card footer-note">
        <h6><i class="fas fa-lightbulb text-warning"></i> How to use:</h6>
        <ul>
            <li>Add/Edit navigation items from this page.</li>
            <li>Set <strong>Active</strong> to show/hide items on the frontend.</li>
            <li><strong>Order</strong> determines the display sequence (lower number = earlier).</li>
            <li>Icons use <a href="https://fontawesome.com/icons" target="_blank" style="color:#60a5fa;">FontAwesome</a> classes (e.g. <code>fa-solid fa-house</code>).</li>
            <li>Changes reflect instantly on the website after saving.</li>
        </ul>
        <p class="mb-0 text-muted"><i class="fas fa-info-circle"></i> The top navigation bar is dynamic. Any change here will be visible on all pages.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
