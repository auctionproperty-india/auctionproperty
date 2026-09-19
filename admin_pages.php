<?php
// ============================================================
// 📄 Admin – Manage Dynamic Pages
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

// ---- Add Page ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $meta_desc = trim($_POST['meta_description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-file');
    $display_order = (int)($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? true : false;

    if (empty($slug) || empty($title)) {
        $message = "❌ Slug और Title ज़रूरी हैं!";
        $message_type = "danger";
    } else {
        try {
            safeExecute($pdo, "INSERT INTO dynamic_pages (slug, title, content, meta_description, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$slug, $title, $content, $meta_desc, $icon, $display_order, $is_active]);
            $message = "✅ Page '$title' added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ---- Update Page ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)$_POST['page_id'];
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $meta_desc = trim($_POST['meta_description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-file');
    $display_order = (int)($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? true : false;

    try {
        safeExecute($pdo, "UPDATE dynamic_pages SET slug = ?, title = ?, content = ?, meta_description = ?, icon = ?, display_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
            [$slug, $title, $content, $meta_desc, $icon, $display_order, $is_active, $id]);
        $message = "✅ Page updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ---- Delete Page ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['page_id'];
    try {
        safeExecute($pdo, "DELETE FROM dynamic_pages WHERE id = ?", [$id]);
        $message = "✅ Page deleted!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ---- Fetch All Pages ----
$pages = safeFetchAll($pdo, "SELECT * FROM dynamic_pages ORDER BY display_order ASC, id ASC");

// ---- Edit Mode ----
$editMode = false;
$editPage = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editPage = safeFetch($pdo, "SELECT * FROM dynamic_pages WHERE id = ?", [(int)$_GET['edit']]);
    if ($editPage) $editMode = true;
}

include 'header.php';
?>

<style>
    .pages-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    .page-title-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 24px 30px;
        border-radius: 20px;
        margin-bottom: 25px;
        box-shadow: 0 10px 30px rgba(37,99,235,0.25);
    }
    .page-title-header h2 { font-weight: 800; margin: 0; }
    .page-title-header p { margin: 5px 0 0; opacity: 0.85; }
    
    .form-card {
        background: #fff;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e8edf4;
        margin-bottom: 24px;
    }
    .form-card h5 {
        font-weight: 800;
        color: #1e3a8a;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .pages-table {
        background: #fff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e8edf4;
    }
    .pages-table table { margin: 0; }
    .pages-table th {
        background: #1e293b;
        color: #fff;
        font-size: 0.75rem;
        text-transform: uppercase;
        padding: 14px 16px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .pages-table td {
        padding: 14px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }
    .pages-table tr:hover { background: #f8faff; }
    
    .slug-badge {
        background: #dbeafe;
        color: #1e40af;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        font-family: monospace;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 700;
    }
    .status-badge.active { background: #dcfce7; color: #166534; }
    .status-badge.inactive { background: #fee2e2; color: #991b1b; }
    
    .btn-action-sm {
        padding: 5px 10px;
        font-size: 0.75rem;
        border-radius: 8px;
        margin-right: 3px;
    }
    .order-badge {
        background: #f59e0b;
        color: #fff;
        padding: 3px 10px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 800;
    }
    .form-control, .form-select {
        border-radius: 10px;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
    }
    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .icon-hint {
        background: #f0f5ff;
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 0.8rem;
        color: #1e40af;
        margin-top: 6px;
    }
</style>

<div class="pages-container">
    
    <!-- Header -->
    <div class="page-title-header">
        <h2><i class="fas fa-file-alt me-2"></i> Manage Dynamic Pages</h2>
        <p>यहाँ से Sidebar के हर Page का Content जोड़ें / Edit करें</p>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" style="border-radius: 14px;">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- ADD / EDIT FORM -->
    <div class="form-card">
        <h5>
            <span>
                <i class="fas fa-<?= $editMode ? 'edit' : 'plus-circle' ?> me-2"></i>
                <?= $editMode ? 'Edit Page: ' . htmlspecialchars($editPage['title']) : 'Add New Page' ?>
            </span>
            <?php if ($editMode): ?>
                <a href="admin_pages.php" class="btn btn-sm btn-secondary rounded-pill px-3">Cancel</a>
            <?php endif; ?>
        </h5>
        
        <form method="POST" action="admin_pages.php">
            <input type="hidden" name="action" value="<?= $editMode ? 'update' : 'add' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="page_id" value="<?= $editPage['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold small">Slug (URL Name) *</label>
                    <input type="text" name="slug" class="form-control" required
                           value="<?= $editMode ? htmlspecialchars($editPage['slug']) : '' ?>"
                           placeholder="e.g. about-us">
                    <small class="text-muted">सिर्फ a-z, 0-9, - (यह URL में दिखेगा)</small>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold small">Page Title *</label>
                    <input type="text" name="title" class="form-control" required
                           value="<?= $editMode ? htmlspecialchars($editPage['title']) : '' ?>"
                           placeholder="e.g. About Us">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold small">Icon (FontAwesome)</label>
                    <input type="text" name="icon" class="form-control"
                           value="<?= $editMode ? htmlspecialchars($editPage['icon']) : 'fa-file' ?>"
                           placeholder="e.g. fa-home, fa-info-circle">
                    <div class="icon-hint">
                        <i class="fas <?= $editMode ? htmlspecialchars($editPage['icon']) : 'fa-file' ?>"></i>
                        Font Awesome Icon Name (जैसे: fa-home, fa-info-circle, fa-gavel)
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Display Order</label>
                    <input type="number" name="display_order" class="form-control"
                           value="<?= $editMode ? htmlspecialchars($editPage['display_order']) : 0 ?>"
                           placeholder="1, 2, 3...">
                    <small class="text-muted">छोटा नंबर = पहले</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Meta Description (SEO)</label>
                    <input type="text" name="meta_description" class="form-control"
                           value="<?= $editMode ? htmlspecialchars($editPage['meta_description'] ?? '') : '' ?>"
                           placeholder="Short description for search engines">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Status</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                               <?= ($editMode && $editPage['is_active']) ? 'checked' : (!$editMode ? 'checked' : '') ?>>
                        <label class="form-check-label fw-bold" for="isActive">Active (Sidebar में दिखे)</label>
                    </div>
                </div>
                
                <div class="col-12">
                    <label class="form-label fw-bold small">Content (HTML Allowed)</label>
                    <textarea name="content" class="form-control" rows="10"
                              placeholder="<h3>Title</h3><p>Your content here...</p>"><?= $editMode ? htmlspecialchars($editPage['content'] ?? '') : '' ?></textarea>
                    <small class="text-muted">आप HTML Tags Use कर सकते हैं (जैसे: &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;br&gt;)</small>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> <?= $editMode ? 'Update Page' : 'Add Page' ?>
                    </button>
                    <a href="admin_pages.php" class="btn btn-secondary rounded-pill px-4 ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- PAGES TABLE -->
    <div class="pages-table">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order</th>
                    <th>Title</th>
                    <th>Slug (URL)</th>
                    <th>Icon</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No pages yet. Add your first page above.</td></tr>
                <?php endif; ?>
                <?php foreach ($pages as $p): ?>
                    <tr>
                        <td><strong>#<?= $p['id'] ?></strong></td>
                        <td><span class="order-badge"><?= $p['display_order'] ?></span></td>
                        <td>
                            <strong style="color: #0f172a;"><?= htmlspecialchars($p['title']) ?></strong>
                        </td>
                        <td>
                            <span class="slug-badge"><?= htmlspecialchars($p['slug']) ?></span>
                        </td>
                        <td>
                            <i class="fas <?= htmlspecialchars($p['icon']) ?>" style="color: #2563eb; font-size: 1.1rem;"></i>
                        </td>
                        <td>
                            <span class="status-badge <?= $p['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $p['is_active'] ? '✅ Active' : '❌ Inactive' ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; color: #64748b;">
                            <?= date('d M Y', strtotime($p['updated_at'])) ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="page.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-sm btn-info btn-action-sm" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-primary btn-action-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Delete this page permanently?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger btn-action-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
</div>

<?php include 'footer.php'; ?>
