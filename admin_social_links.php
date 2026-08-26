<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// ---- Handle Add/Edit/Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $platform = trim($_POST['platform']);
        $url = trim($_POST['url']);
        $icon_class = trim($_POST['icon_class']);
        $display_order = (int)$_POST['display_order'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO social_links (platform, url, icon_class, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$platform, $url, $icon_class, $display_order, $is_active]);
        header("Location: admin_social_links.php?msg=added");
        exit;
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $platform = trim($_POST['platform']);
        $url = trim($_POST['url']);
        $icon_class = trim($_POST['icon_class']);
        $display_order = (int)$_POST['display_order'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE social_links SET platform = ?, url = ?, icon_class = ?, display_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$platform, $url, $icon_class, $display_order, $is_active, $id]);
        header("Location: admin_social_links.php?msg=updated");
        exit;
    } elseif (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM social_links WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_social_links.php?msg=deleted");
        exit;
    }
}

$social_links = $pdo->query("SELECT * FROM social_links ORDER BY display_order")->fetchAll();
$edit_record = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM social_links WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_record = $stmt->fetch();
}
?>
<div class="container-fluid mt-4">
    <h1><i class="fas fa-share-alt me-2"></i>Social Links</h1>
    <p>Manage social media links displayed in the top navigation sidebar.</p>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?= $_GET['msg'] == 'added' ? '✅ Link added' : ($_GET['msg'] == 'updated' ? '✅ Link updated' : '✅ Link deleted') ?>
        </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <?= $edit_record ? 'Edit Social Link' : 'Add New Social Link' ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($edit_record): ?>
                    <input type="hidden" name="id" value="<?= $edit_record['id'] ?>">
                    <input type="hidden" name="edit" value="1">
                <?php else: ?>
                    <input type="hidden" name="add" value="1">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Platform</label>
                        <input type="text" name="platform" class="form-control" required value="<?= $edit_record ? htmlspecialchars($edit_record['platform']) : '' ?>">
                        <small>e.g., Facebook, Twitter, Instagram</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">URL</label>
                        <input type="url" name="url" class="form-control" required value="<?= $edit_record ? htmlspecialchars($edit_record['url']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Icon Class</label>
                        <input type="text" name="icon_class" class="form-control" required value="<?= $edit_record ? htmlspecialchars($edit_record['icon_class']) : '' ?>">
                        <small>e.g., <code>fab fa-facebook-f</code> (FontAwesome classes)</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="<?= $edit_record ? $edit_record['display_order'] : 0 ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Active</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" <?= $edit_record && $edit_record['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label">Show on site</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?= $edit_record ? 'Update' : 'Add' ?></button>
                        <?php if ($edit_record): ?>
                            <a href="admin_social_links.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- List -->
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Platform</th>
                <th>URL</th>
                <th>Icon</th>
                <th>Order</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($social_links as $link): ?>
            <tr>
                <td><?= $link['id'] ?></td>
                <td><?= htmlspecialchars($link['platform']) ?></td>
                <td><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"><?= htmlspecialchars($link['url']) ?></a></td>
                <td><i class="<?= htmlspecialchars($link['icon_class']) ?>"></i></td>
                <td><?= $link['display_order'] ?></td>
                <td><?= $link['is_active'] ? '✅' : '❌' ?></td>
                <td>
                    <a href="admin_social_links.php?edit=<?= $link['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="admin_social_links.php?delete=<?= $link['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>
