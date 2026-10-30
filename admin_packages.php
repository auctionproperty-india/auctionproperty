<?php
// ============================================================
// 🎁 Admin Packages Manager – With Add/Edit Checkboxes & Access Type
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- Add New Field ---
    if ($_POST['action'] === 'add_field') {
        $raw_key = trim($_POST['field_key'] ?? '');
        $field_key = preg_replace('/[^a-z0-9_]/', '_', strtolower($raw_key));
        if (empty($field_key) || preg_match('/^[0-9_]+$/', $field_key)) {
            $field_key = strtolower(trim($_POST['field_label'] ?? ''));
            $field_key = preg_replace('/[^a-z0-9]+/', '_', $field_key);
            $field_key = trim($field_key, '_');
        }
        if (empty($field_key)) $field_key = 'field_' . time();

        $original_key = $field_key;
        $counter = 1;
        while (true) {
            $check = $pdo->prepare("SELECT id FROM package_fields WHERE field_key = ?");
            $check->execute([$field_key]);
            if ($check->rowCount() === 0) break;
            $field_key = $original_key . '_' . $counter;
            $counter++;
            if ($counter > 999) { $field_key = $original_key . '_' . time(); break; }
        }

        $field_label = trim($_POST['field_label'] ?? '');
        $field_type = trim($_POST['field_type'] ?? 'text');
        $default_value = trim($_POST['default_value'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 0);

        if (empty($field_label)) {
            $message = "❌ Field Label ज़रूरी है!";
            $message_type = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO package_fields (field_key, field_label, field_type, default_value, display_order, is_active) VALUES (?, ?, ?, ?, ?, TRUE)");
                $stmt->execute([$field_key, $field_label, $field_type, $default_value, $display_order]);
                $message = "✅ Field '$field_label' added! (Key: $field_key)";
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
            $message = "Field deleted from all packages!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // --- Rename Field ---
    if ($_POST['action'] === 'rename_field') {
        $field_id = (int)$_POST['field_id'];
        $new_label = trim($_POST['field_label'] ?? '');
        if (!empty($new_label)) {
            $pdo->prepare("UPDATE package_fields SET field_label = ? WHERE id = ?")->execute([$new_label, $field_id]);
            $message = "Field renamed to '$new_label'";
            $message_type = "success";
        }
    }

    // --- Add New Package ---
    if ($_POST['action'] === 'add_package') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $duration = (int)($_POST['duration'] ?? 0);
        $display_order = (int)($_POST['display_order'] ?? 0);
        $access_type = trim($_POST['access_type'] ?? 'all_india');
        
        $is_user = isset($_POST['is_user_package']) ? 1 : 0;
        $is_ba = isset($_POST['is_ba_package']) ? 1 : 0;

        if (empty($name) || $price <= 0) {
            $message = "Package Name और Price ज़रूरी हैं!";
            $message_type = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO packages (name, price, discount_price, duration, display_order, is_user_package, is_ba_package, access_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $price, $discount_price, $duration, $display_order, $is_user, $is_ba, $access_type]);
                $package_id = $pdo->lastInsertId();

                $fields = $pdo->query("SELECT * FROM package_fields WHERE is_active = TRUE")->fetchAll();
                foreach ($fields as $f) {
                    $is_visible = isset($_POST['visible_' . $f['id']]) ? 1 : 0;
                    if (!$is_visible) continue;
                    $val = trim($_POST['field_' . $f['id']] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO package_field_values (package_id, field_id, field_value, is_visible) VALUES (?, ?, ?, TRUE)");
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
        $display_order = (int)($_POST['display_order'] ?? 0);
        $access_type = trim($_POST['access_type'] ?? 'all_india');
        
        $is_user = isset($_POST['is_user_package']) ? 1 : 0;
        $is_ba = isset($_POST['is_ba_package']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE packages SET name = ?, price = ?, discount_price = ?, duration = ?, display_order = ?, is_user_package = ?, is_ba_package = ?, access_type = ? WHERE id = ?");
            $stmt->execute([$name, $price, $discount_price, $duration, $display_order, $is_user, $is_ba, $access_type, $id]);

            $fields = $pdo->query("SELECT * FROM package_fields")->fetchAll();
            foreach ($fields as $f) {
                $is_visible = isset($_POST['visible_' . $f['id']]) ? 1 : 0;
                if ($is_visible) {
                    $val = trim($_POST['field_' . $f['id']] ?? '');
                    $stmt = $pdo->prepare("
                        INSERT INTO package_field_values (package_id, field_id, field_value, is_visible) 
                        VALUES (?, ?, ?, TRUE)
                        ON CONFLICT (package_id, field_id) 
                        DO UPDATE SET field_value = EXCLUDED.field_value, is_visible = TRUE
                    ");
                    $stmt->execute([$id, $f['id'], $val]);
                } else {
                    $stmt = $pdo->prepare("UPDATE package_field_values SET is_visible = FALSE WHERE package_id = ? AND field_id = ?");
                    $stmt->execute([$id, $f['id']]);
                }
            }

            $message = "✅ Package '$name' updated successfully!";
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
            $message = "✅ Package deleted!";
            $message_type = "success";
            header("Location: admin_packages.php");
            exit;
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

$fields = $pdo->query("SELECT * FROM package_fields ORDER BY display_order ASC, id ASC")->fetchAll();
$packages = $pdo->query("SELECT * FROM packages ORDER BY COALESCE(display_order, 999) ASC, id ASC")->fetchAll();

$fieldValues = [];
$stmt = $pdo->query("SELECT * FROM package_field_values WHERE COALESCE(is_visible, TRUE) = TRUE");
while ($row = $stmt->fetch()) {
    $fieldValues[$row['package_id']][$row['field_id']] = $row['field_value'];
}

$selectedId = isset($_GET['pkg']) && is_numeric($_GET['pkg']) ? (int)$_GET['pkg'] : 0;
$selectedPackage = null;
if ($selectedId > 0) {
    foreach ($packages as $p) {
        if ($p['id'] == $selectedId) { $selectedPackage = $p; break; }
    }
}
if (!$selectedPackage && !empty($packages)) {
    $selectedPackage = $packages[0];
}

include 'header.php';
?>

<style>
    .pkg-card { background: #fff; border-radius: 20px; padding: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e8edf4; margin-bottom: 20px; }
    .pkg-card h5 { font-weight: 800; color: #1e3a8a; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #eef2f6; display: flex; justify-content: space-between; align-items: center; font-size: 1.05rem; }
    .pkg-selector { background: #fff; border-radius: 16px; padding: 14px 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e8edf4; margin-bottom: 20px; }
    .pkg-selector select { border-radius: 10px; padding: 10px 16px; border: 1px solid #cbd5e1; font-size: 1rem; font-weight: 600; color: #1e3a8a; min-width: 250px; }
    .field-row { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eef2f6; }
    .field-checkbox-row { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; border: 2px solid #eef2f6; transition: all 0.2s; }
    .field-checkbox-row.included { background: #ecfdf5; border-color: #10b981; }
    .pkg-table { font-size: 0.85rem; margin-bottom: 0; }
    .pkg-table th { background: #1e293b; color: #fff; font-size: 0.72rem; text-transform: uppercase; padding: 10px 12px; font-weight: 700; }
    .pkg-table td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .seq-badge { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 6px 14px; border-radius: 30px; font-size: 0.85rem; font-weight: 800; display: inline-block; min-width: 40px; text-align: center; }
</style>

<div class="container-fluid">
    <h3 class="text-light mb-4"><i class="fas fa-box-open me-2"></i> Package Manager</h3>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- PACKAGE SELECTOR -->
    <div class="pkg-selector">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="fw-bold mb-0" style="color: #1e3a8a; font-size: 1rem;"><i class="fas fa-box me-2"></i> Package चुनें:</label>
            <form method="GET" class="d-flex align-items-center gap-2" id="pkgSelectForm">
                <select name="pkg" onchange="document.getElementById('pkgSelectForm').submit();">
                    <?php foreach ($packages as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($selectedPackage && $selectedPackage['id'] == $p['id']) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($p['display_order'] ?? 0) ?>] <?= htmlspecialchars($p['name']) ?> (₹<?= number_format($p['price'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="?add_new=1" class="btn btn-success btn-sm rounded-pill px-3" style="margin-left:auto;"><i class="fas fa-plus me-1"></i> नया Package जोड़ें</a>
            <button type="button" class="btn btn-info btn-sm rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#fieldMaster"><i class="fas fa-columns me-1"></i> Global Fields</button>
        </div>
    </div>

    <!-- GLOBAL FIELD MANAGER -->
    <div class="collapse mb-4" id="fieldMaster">
        <div class="pkg-card">
            <h5><span><i class="fas fa-columns me-2"></i> Global Fields (Master List)</span> <span class="badge bg-primary"><?= count($fields) ?></span></h5>
            <div class="row">
                <div class="col-md-7">
                    <div style="max-height: 320px; overflow-y: auto;">
                        <?php foreach ($fields as $f): ?>
                            <div class="field-row">
                                <div>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem;"><?= htmlspecialchars($f['field_label']) ?></div>
                                </div>
                                <div style="display: flex; gap: 4px;">
                                    <button type="button" class="btn btn-sm btn-sm-custom btn-info" onclick="renameField(<?= $f['id'] ?>, '<?= htmlspecialchars($f['field_label'], ENT_QUOTES) ?>')"><i class="fas fa-pen"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ यह Field सभी Packages से DELETE हो जाएगा!');">
                                        <input type="hidden" name="action" value="delete_field">
                                        <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-sm-custom btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-5">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_field">
                        <div class="mb-2"><input type="text" name="field_label" class="form-control form-control-sm" required placeholder="Field Label"></div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i> Add Field</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SELECTED PACKAGE EDIT PANEL -->
    <?php if ($selectedPackage): ?>
        <div class="pkg-card">
            <h5><span><i class="fas fa-edit me-2"></i> <?= htmlspecialchars($selectedPackage['name']) ?> – Edit करें</span></h5>

            <form method="POST" id="updateForm">
                <input type="hidden" name="action" value="update_package">
                <input type="hidden" name="package_id" value="<?= $selectedPackage['id'] ?>">

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><label class="form-label fw-bold small">Package Name *</label><input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($selectedPackage['name'] ?? '') ?>"></div>
                    <div class="col-md-2"><label class="form-label fw-bold small">Price (₹) *</label><input type="number" name="price" class="form-control" step="0.01" required value="<?= $selectedPackage['price'] ?? '' ?>"></div>
                    <div class="col-md-2"><label class="form-label fw-bold small">Discount Price</label><input type="number" name="discount_price" class="form-control" step="0.01" value="<?= $selectedPackage['discount_price'] ?? '' ?>"></div>
                    <div class="col-md-2"><label class="form-label fw-bold small">Duration (महीने)</label><input type="number" name="duration" class="form-control" value="<?= $selectedPackage['duration'] ?? 0 ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-bold small">🔢 Sequence No.</label><input type="number" name="display_order" class="form-control" value="<?= $selectedPackage['display_order'] ?? 0 ?>"></div>
                </div>

                <!-- 🔥 ACCESS TYPE DROPDOWN (EDIT) -->
                <div class="row g-3 mb-4 p-3 rounded" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-success small">🌍 Property Access Type</label>
                        <select name="access_type" class="form-control border-success">
                            <option value="all_india" <?= (!isset($selectedPackage['access_type']) || $selectedPackage['access_type'] == 'all_india') ? 'selected' : '' ?>>All India (Full Access)</option>
                            <option value="local_city" <?= (isset($selectedPackage['access_type']) && $selectedPackage['access_type'] == 'local_city') ? 'selected' : '' ?>>Local City Only</option>
                        </select>
                        <small class="text-muted" style="font-size: 0.7rem;">Local City = User can only see properties in their registered city.</small>
                    </div>
                    
                    <div class="col-md-4 border-start">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_user_package" id="edit_is_user" value="1" <?= (!isset($selectedPackage['is_user_package']) || $selectedPackage['is_user_package']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="edit_is_user">Show in User Packages (Search Engine)</label>
                        </div>
                    </div>
                    <div class="col-md-4 border-start">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_ba_package" id="edit_is_ba" value="1" <?= (isset($selectedPackage['is_ba_package']) && $selectedPackage['is_ba_package']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-primary" for="edit_is_ba">Show in BA Packages (Training)</label>
                        </div>
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold mb-2" style="color: #1e3a8a;"><i class="fas fa-check-square me-1"></i> इस Package में Fields:</h6>

                <div class="row g-2">
                    <?php foreach ($fields as $f):
                        $isIncluded = isset($fieldValues[$selectedPackage['id']][$f['id']]);
                        $val = $isIncluded ? $fieldValues[$selectedPackage['id']][$f['id']] : ($f['default_value'] ?? '');
                    ?>
                        <div class="col-md-6">
                            <div class="field-checkbox-row <?= $isIncluded ? 'included' : '' ?>" id="row-<?= $f['id'] ?>">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="checkbox" name="visible_<?= $f['id'] ?>" id="vis_<?= $f['id'] ?>" class="form-check-input" <?= $isIncluded ? 'checked' : '' ?> onchange="toggleFieldRow(this, <?= $f['id'] ?>)">
                                    <label for="vis_<?= $f['id'] ?>" class="form-check-label fw-bold mb-0"><?= htmlspecialchars($f['field_label']) ?></label>
                                </div>
                                <div id="field-input-<?= $f['id'] ?>" style="<?= $isIncluded ? '' : 'display:none;' ?>">
                                    <input type="text" name="field_<?= $f['id'] ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($val) ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-2"></i> Update Package</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- ADD NEW PACKAGE FORM -->
    <?php if (isset($_GET['add_new'])): ?>
        <div class="pkg-card">
            <h5><i class="fas fa-plus-circle me-2"></i> नया Package जोड़ें</h5>

            <form method="POST">
                <input type="hidden" name="action" value="add_package">

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><label class="form-label fw-bold small">Package Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-2"><label class="form-label fw-bold small">Price (₹) *</label><input type="number" name="price" class="form-control" step="0.01" required></div>
                    <div class="col-md-2"><label class="form-label fw-bold small">Discount Price</label><input type="number" name="discount_price" class="form-control" step="0.01"></div>
                    <div class="col-md-2"><label class="form-label fw-bold small">Duration (महीने)</label><input type="number" name="duration" class="form-control" value="0"></div>
                    <div class="col-md-3"><label class="form-label fw-bold small">🔢 Sequence No.</label><input type="number" name="display_order" class="form-control" placeholder="1, 2, 3..." value="0"></div>
                </div>

                <!-- 🔥 ACCESS TYPE DROPDOWN (ADD NEW) -->
                <div class="row g-3 mb-4 p-3 rounded" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-success small">🌍 Property Access Type</label>
                        <select name="access_type" class="form-control border-success">
                            <option value="all_india">All India (Full Access)</option>
                            <option value="local_city">Local City Only</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 border-start">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_user_package" id="add_is_user" value="1" checked>
                            <label class="form-check-label fw-bold" for="add_is_user">Show in User Packages (Search Engine)</label>
                        </div>
                    </div>
                    <div class="col-md-4 border-start">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_ba_package" id="add_is_ba" value="1">
                            <label class="form-check-label fw-bold text-primary" for="add_is_ba">Show in BA Packages (Training)</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success rounded-pill px-4"><i class="fas fa-save me-2"></i> Save Package</button>
                    <a href="admin_packages.php" class="btn btn-secondary rounded-pill px-4 ms-2">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- ALL PACKAGES TABLE -->
    <div class="pkg-card">
        <h5><span><i class="fas fa-table me-2"></i> All Packages</span> <span class="badge bg-primary"><?= count($packages) ?></span></h5>
        <div class="table-responsive">
            <table class="table pkg-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Access</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td>
                                <?php if (!isset($p['is_user_package']) || $p['is_user_package']): ?>
                                    <span class="badge bg-primary px-2 py-1 mb-1">User</span>
                                <?php endif; ?>
                                <?php if (isset($p['is_ba_package']) && $p['is_ba_package']): ?>
                                    <span class="badge bg-success px-2 py-1 mb-1">BA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($p['access_type']) && $p['access_type'] == 'local_city'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-map-marker-alt"></i> Local</span>
                                <?php else: ?>
                                    <span class="badge bg-info"><i class="fas fa-globe"></i> All India</span>
                                <?php endif; ?>
                            </td>
                            <td>₹ <?= number_format($p['price'] ?? 0) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['duration'] ?? 0) ?> mo</span></td>
                            <td style="text-align:right;">
                                <a href="?pkg=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Delete this package?');">
                                    <input type="hidden" name="action" value="delete_package">
                                    <input type="hidden" name="package_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleFieldRow(checkbox, fieldId) {
    const inputDiv = document.getElementById('field-input-' + fieldId);
    const row = document.getElementById('row-' + fieldId);
    if (checkbox.checked) { inputDiv.style.display = ''; row.classList.add('included'); } 
    else { inputDiv.style.display = 'none'; row.classList.remove('included'); }
}
function renameField(fieldId, currentLabel) {
    const newLabel = prompt('Enter new label:', currentLabel);
    if (!newLabel || newLabel === currentLabel) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="action" value="rename_field"><input type="hidden" name="field_id" value="${fieldId}"><input type="hidden" name="field_label" value="${newLabel.replace(/"/g, '&quot;')}">`;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'footer.php'; ?>
