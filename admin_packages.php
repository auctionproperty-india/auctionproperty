<?php
// ============================================================
// 🎁 Admin Packages Manager – Dynamic Fields Support
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

// ============================================================
// HANDLE: Add/Delete Field
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Add New Field ---
    if ($_POST['action'] === 'add_field') {
        $field_key = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['field_key'] ?? '')));
        $field_label = trim($_POST['field_label'] ?? '');
        $field_type = trim($_POST['field_type'] ?? 'text');
        $default_value = trim($_POST['default_value'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 0);

        if (empty($field_key) || empty($field_label)) {
            $message = "Field Key और Label ज़रूरी हैं!";
            $message_type = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO package_fields (field_key, field_label, field_type, default_value, display_order, is_active) VALUES (?, ?, ?, ?, ?, TRUE)");
                $stmt->execute([$field_key, $field_label, $field_type, $default_value, $display_order]);
                $message = "✅ Field '$field_label' successfully added!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }

    // --- Delete Field ---
    if ($_POST['action'] === 'delete_field') {
        $field_id = (int)$_POST['field_id'];
        try {
            $pdo->prepare("DELETE FROM package_fields WHERE id = ?")->execute([$field_id]);
            $message = "Field deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // --- Toggle Field Active ---
    if ($_POST['action'] === 'toggle_field') {
        $field_id = (int)$_POST['field_id'];
        $pdo->prepare("UPDATE package_fields SET is_active = NOT is_active WHERE id = ?")->execute([$field_id]);
        $message = "Field status updated!";
        $message_type = "success";
    }

    // --- Add New Package ---
    if ($_POST['action'] === 'add_package') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $duration = (int)($_POST['duration'] ?? 0);

        if (empty($name) || $price <= 0) {
            $message = "Package Name और Price ज़रूरी हैं!";
            $message_type = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO packages (name, price, discount_price, duration) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $price, $discount_price, $duration]);
                $package_id = $pdo->lastInsertId();

                // Save dynamic field values
                $fields = $pdo->query("SELECT * FROM package_fields WHERE is_active = TRUE")->fetchAll();
                foreach ($fields as $f) {
                    $val = trim($_POST['field_' . $f['id']] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO package_field_values (package_id, field_id, field_value) VALUES (?, ?, ?)");
                    $stmt->execute([$package_id, $f['id'], $val]);
                }

                $message = "✅ Package '$name' added successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }

    // --- Update Package ---
    if ($_POST['action'] === 'update_package') {
        $id = (int)$_POST['package_id'];
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $duration = (int)($_POST['duration'] ?? 0);

        try {
            $stmt = $pdo->prepare("UPDATE packages SET name = ?, price = ?, discount_price = ?, duration = ? WHERE id = ?");
            $stmt->execute([$name, $price, $discount_price, $duration, $id]);

            // Update field values
            $fields = $pdo->query("SELECT * FROM package_fields")->fetchAll();
            foreach ($fields as $f) {
                $val = trim($_POST['field_' . $f['id']] ?? '');
                $stmt = $pdo->prepare("
                    INSERT INTO package_field_values (package_id, field_id, field_value) VALUES (?, ?, ?)
                    ON CONFLICT (package_id, field_id) DO UPDATE SET field_value = EXCLUDED.field_value
                ");
                $stmt->execute([$id, $f['id'], $val]);
            }

            $message = "✅ Package updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // --- Delete Package ---
    if ($_POST['action'] === 'delete_package') {
        $id = (int)$_POST['package_id'];
        try {
            $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
            $message = "Package deleted!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$fields = $pdo->query("SELECT * FROM package_fields ORDER BY display_order ASC, id ASC")->fetchAll();

$packages = $pdo->query("SELECT * FROM packages ORDER BY id ASC")->fetchAll();

// Get all field values
$fieldValues = [];
$stmt = $pdo->query("SELECT * FROM package_field_values");
while ($row = $stmt->fetch()) {
    $fieldValues[$row['package_id']][$row['field_id']] = $row['field_value'];
}

// Edit mode
$editMode = false;
$editPackage = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editPackage = $stmt->fetch();
    if ($editPackage) $editMode = true;
}

include 'header.php';
?>

<style>
    .pkg-card {
        background: #fff;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e8edf4;
        margin-bottom: 24px;
    }
    .pkg-card h5 {
        font-weight: 800;
        color: #1e3a8a;
        margin-bottom: 18px;
        padding-bottom: 12px;
        border-bottom: 2px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .field-row {
        background: #f8fafc;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #eef2f6;
        transition: all 0.2s;
    }
    .field-row:hover {
        background: #f0f5ff;
        border-color: #bfdbfe;
    }
    .field-row.inactive {
        opacity: 0.5;
        background: #f8fafc;
    }
    .field-key {
        font-family: monospace;
        background: #dbeafe;
        color: #1e40af;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .field-type {
        background: #dcfce7;
        color: #166534;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        margin-left: 6px;
    }
    .pkg-table {
        font-size: 0.82rem;
        margin-bottom: 0;
        white-space: nowrap;
    }
    .pkg-table th {
        background: #1e293b;
        color: #fff;
        font-size: 0.7rem;
        text-transform: uppercase;
        padding: 10px 8px;
        font-weight: 700;
    }
    .pkg-table td {
        padding: 10px 8px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .btn-sm-custom {
        padding: 4px 10px;
        font-size: 0.72rem;
        border-radius: 6px;
    }
</style>

<div class="container-fluid">
    <h3 class="text-light mb-4"><i class="fas fa-box-open me-2"></i> Package Manager</h3>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- ============================================================ -->
        <!-- LEFT: FIELD MANAGER -->
        <!-- ============================================================ -->
        <div class="col-lg-5">
            <div class="pkg-card">
                <h5>
                    <span><i class="fas fa-columns me-2"></i> Field Manager</span>
                    <span class="badge bg-primary"><?= count($fields) ?></span>
                </h5>

                <!-- Existing Fields -->
                <div style="max-height: 400px; overflow-y: auto; margin-bottom: 20px;">
                    <?php foreach ($fields as $f): ?>
                        <div class="field-row <?= $f['is_active'] ? '' : 'inactive' ?>">
                            <div>
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">
                                    <?= htmlspecialchars($f['field_label']) ?>
                                    <span class="field-type"><?= htmlspecialchars($f['field_type']) ?></span>
                                </div>
                                <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;">
                                    Key: <span class="field-key"><?= htmlspecialchars($f['field_key']) ?></span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_field">
                                    <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-sm-custom <?= $f['is_active'] ? 'btn-warning' : 'btn-success' ?>" title="<?= $f['is_active'] ? 'Disable' : 'Enable' ?>">
                                        <i class="fas fa-<?= $f['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field? It will also remove all data associated with it.');">
                                    <input type="hidden" name="action" value="delete_field">
                                    <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-sm-custom btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>

                <!-- Add New Field Form -->
                <h6 class="fw-bold mb-3" style="color: #1e3a8a;">
                    <i class="fas fa-plus-circle me-2"></i> नया Field जोड़ें
                </h6>
                <form method="POST">
                    <input type="hidden" name="action" value="add_field">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Field Label *</label>
                            <input type="text" name="field_label" class="form-control form-control-sm" required placeholder="e.g. Free Property Visit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Field Key * (English)</label>
                            <input type="text" name="field_key" class="form-control form-control-sm" required placeholder="e.g. free_property_visit">
                            <small class="text-muted" style="font-size: 0.65rem;">सिर्फ a-z, 0-9, _</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Type</label>
                            <select name="field_type" class="form-control form-control-sm">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Dropdown</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Default Value</label>
                            <input type="text" name="default_value" class="form-control form-control-sm" placeholder="e.g. Unlimited">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Display Order</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="<?= count($fields) + 1 ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 btn-sm-custom">
                                <i class="fas fa-plus"></i> Add Field
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- RIGHT: PACKAGE FORM -->
        <!-- ============================================================ -->
        <div class="col-lg-7">
            <div class="pkg-card">
                <h5>
                    <span><i class="fas fa-<?= $editMode ? 'edit' : 'plus' ?> me-2"></i> <?= $editMode ? 'Edit Package' : 'Add New Package' ?></span>
                    <?php if ($editMode): ?>
                        <a href="admin_packages.php" class="btn btn-sm btn-secondary btn-sm-custom">Cancel Edit</a>
                    <?php endif; ?>
                </h5>

                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editMode ? 'update_package' : 'add_package' ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="package_id" value="<?= $editMode ? $editPackage['id'] : '' ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <!-- Basic Fields -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Package Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= $editMode ? htmlspecialchars($editPackage['name'] ?? '') : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Price (₹) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" required
                                   value="<?= $editMode ? ($editPackage['price'] ?? '') : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Discount Price</label>
                            <input type="number" name="discount_price" class="form-control" step="0.01"
                                   value="<?= $editMode ? ($editPackage['discount_price'] ?? '') : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Duration (months)</label>
                            <input type="number" name="duration" class="form-control"
                                   value="<?= $editMode ? ($editPackage['duration'] ?? '') : '' ?>">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold small mb-3" style="color: #1e3a8a;">
                        <i class="fas fa-list me-1"></i> Dynamic Fields
                    </h6>

                    <div class="row g-3">
                        <?php foreach ($fields as $f): 
                            if (!$f['is_active']) continue;
                            $val = $editMode && isset($fieldValues[$editPackage['id']][$f['id']]) 
                                ? $fieldValues[$editPackage['id']][$f['id']] 
                                : ($f['default_value'] ?? '');
                        ?>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small"><?= htmlspecialchars($f['field_label']) ?></label>
                                <?php if ($f['field_type'] === 'textarea'): ?>
                                    <textarea name="field_<?= $f['id'] ?>" class="form-control" rows="2"><?= htmlspecialchars($val) ?></textarea>
                                <?php elseif ($f['field_type'] === 'number'): ?>
                                    <input type="number" name="field_<?= $f['id'] ?>" class="form-control" value="<?= htmlspecialchars($val) ?>">
                                <?php else: ?>
                                    <input type="text" name="field_<?= $f['id'] ?>" class="form-control" value="<?= htmlspecialchars($val) ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-2"></i> <?= $editMode ? 'Update Package' : 'Save Package' ?>
                        </button>
                        <a href="admin_packages.php" class="btn btn-secondary rounded-pill px-4 ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- PACKAGE TABLE (Dynamic Columns) -->
    <!-- ============================================================ -->
    <div class="pkg-card">
        <h5><i class="fas fa-table me-2"></i> All Packages <span class="badge bg-primary"><?= count($packages) ?></span></h5>
        <div class="table-responsive">
            <table class="table pkg-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Discount</th>
                        <th>Duration</th>
                        <?php foreach ($fields as $f): ?>
                            <?php if (!$f['is_active']) continue; ?>
                            <th><?= htmlspecialchars($f['field_label']) ?></th>
                        <?php endforeach; ?>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packages)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No packages yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($packages as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td>₹ <?= number_format($p['price'] ?? 0) ?></td>
                            <td>
                                <?php if (!empty($p['discount_price'])): ?>
                                    <span style="text-decoration: line-through; color: #94a3b8;">₹ <?= number_format($p['price']) ?></span>
                                    <br><strong style="color: #10b981;">₹ <?= number_format($p['discount_price']) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['duration'] ?? 0) ?> mo</td>
                            <?php foreach ($fields as $f): ?>
                                <?php if (!$f['is_active']) continue; ?>
                                <td><?= htmlspecialchars($fieldValues[$p['id']][$f['id']] ?? '—') ?></td>
                            <?php endforeach; ?>
                            <td style="text-align:right;">
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-sm-custom btn-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this package?');">
                                    <input type="hidden" name="action" value="delete_package">
                                    <input type="hidden" name="package_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-sm-custom btn-danger">
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
</div>

<?php include 'footer.php'; ?>
