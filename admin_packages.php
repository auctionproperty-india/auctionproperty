<?php
// ============================================================
// 🎁 Admin Packages Manager – Package Selector + Edit Panel
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
// HANDLE ACTIONS
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

    // --- Delete Field (globally) ---
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

        if (empty($name) || $price <= 0) {
            $message = "Package Name और Price ज़रूरी हैं!";
            $message_type = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO packages (name, price, discount_price, duration) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $price, $discount_price, $duration]);
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

        try {
            $stmt = $pdo->prepare("UPDATE packages SET name = ?, price = ?, discount_price = ?, duration = ? WHERE id = ?");
            $stmt->execute([$name, $price, $discount_price, $duration, $id]);

            // Remove all existing values
            $pdo->prepare("DELETE FROM package_field_values WHERE package_id = ?")->execute([$id]);

            // Re-insert based on checkboxes
            $fields = $pdo->query("SELECT * FROM package_fields")->fetchAll();
            foreach ($fields as $f) {
                $is_visible = isset($_POST['visible_' . $f['id']]) ? 1 : 0;
                if (!$is_visible) continue;
                $val = trim($_POST['field_' . $f['id']] ?? '');
                $stmt = $pdo->prepare("INSERT INTO package_field_values (package_id, field_id, field_value, is_visible) VALUES (?, ?, ?, TRUE)");
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

// Field values map
$fieldValues = [];
$stmt = $pdo->query("SELECT * FROM package_field_values WHERE is_visible = TRUE");
while ($row = $stmt->fetch()) {
    $fieldValues[$row['package_id']][$row['field_id']] = $row['field_value'];
}

// Selected Package (default: first)
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
    .pkg-card {
        background: #fff;
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e8edf4;
        margin-bottom: 20px;
    }
    .pkg-card h5 {
        font-weight: 800;
        color: #1e3a8a;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 2px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1.05rem;
    }
    .pkg-selector {
        background: #fff;
        border-radius: 16px;
        padding: 14px 18px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e8edf4;
        margin-bottom: 20px;
    }
    .pkg-selector select {
        border-radius: 10px;
        padding: 10px 16px;
        border: 1px solid #cbd5e1;
        font-size: 1rem;
        font-weight: 600;
        color: #1e3a8a;
        min-width: 250px;
    }
    .pkg-selector select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
    }
    .field-row {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #eef2f6;
    }
    .field-key {
        font-family: monospace;
        background: #dbeafe;
        color: #1e40af;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
    }
    .field-type {
        background: #dcfce7;
        color: #166534;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.68rem;
        font-weight: 700;
        margin-left: 6px;
    }
    .btn-sm-custom {
        padding: 4px 10px;
        font-size: 0.75rem;
        border-radius: 8px;
    }
    .field-checkbox-row {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 8px;
        border: 2px solid #eef2f6;
        transition: all 0.2s;
    }
    .field-checkbox-row.included {
        background: #ecfdf5;
        border-color: #10b981;
    }
    .form-check-input {
        width: 1.3em;
        height: 1.3em;
        cursor: pointer;
    }
    .form-check-input:checked {
        background-color: #10b981;
        border-color: #10b981;
    }
    .form-check-label {
        cursor: pointer;
        font-size: 0.9rem;
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

    <!-- ============================================================ -->
    <!-- PACKAGE SELECTOR DROPDOWN -->
    <!-- ============================================================ -->
    <div class="pkg-selector">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <label class="fw-bold mb-0" style="color: #1e3a8a; font-size: 1rem;">
                <i class="fas fa-box me-2"></i> Package चुनें:
            </label>
            <form method="GET" class="d-flex align-items-center gap-2" id="pkgSelectForm">
                <select name="pkg" onchange="document.getElementById('pkgSelectForm').submit();">
                    <?php foreach ($packages as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($selectedPackage && $selectedPackage['id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (₹<?= number_format($p['price'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <a href="?add_new=1" class="btn btn-success btn-sm rounded-pill px-3" style="margin-left:auto;">
                <i class="fas fa-plus me-1"></i> नया Package जोड़ें
            </a>

            <button type="button" class="btn btn-info btn-sm rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#fieldMaster">
                <i class="fas fa-columns me-1"></i> Global Fields (Master)
            </button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- GLOBAL FIELD MANAGER (Collapsible) -->
    <!-- ============================================================ -->
    <div class="collapse mb-4" id="fieldMaster">
        <div class="pkg-card">
            <h5>
                <span><i class="fas fa-columns me-2"></i> Global Fields (Master List)</span>
                <span class="badge bg-primary"><?= count($fields) ?></span>
            </h5>
            <p class="text-muted" style="font-size: 0.82rem;">
                ⚠️ यह Master List है। हर Package में इन Fields को Checkbox से Add/Remove कर सकते हैं।
            </p>

            <div class="row">
                <div class="col-md-7">
                    <div style="max-height: 320px; overflow-y: auto;">
                        <?php foreach ($fields as $f): ?>
                            <div class="field-row">
                                <div>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem;">
                                        <?= htmlspecialchars($f['field_label']) ?>
                                        <span class="field-type"><?= htmlspecialchars($f['field_type']) ?></span>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #64748b; margin-top: 2px;">
                                        Key: <span class="field-key"><?= htmlspecialchars($f['field_key']) ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 4px;">
                                    <button type="button" class="btn btn-sm btn-sm-custom btn-info"
                                            onclick="renameField(<?= $f['id'] ?>, '<?= htmlspecialchars($f['field_label'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ यह Field सभी Packages से DELETE हो जाएगा!');">
                                        <input type="hidden" name="action" value="delete_field">
                                        <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-sm-custom btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-md-5">
                    <h6 class="fw-bold mb-2" style="color: #1e3a8a;">नया Field जोड़ें</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_field">
                        <div class="mb-2">
                            <input type="text" name="field_label" class="form-control form-control-sm" required placeholder="Field Label (e.g. Free Parking)">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="field_key" class="form-control form-control-sm" required placeholder="field_key (e.g. free_parking)">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <select name="field_type" class="form-control form-control-sm">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="textarea">Textarea</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="number" name="display_order" class="form-control form-control-sm" value="<?= count($fields) + 1 ?>" placeholder="Order">
                            </div>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="default_value" class="form-control form-control-sm" placeholder="Default Value (Optional)">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-plus me-1"></i> Add Field
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SELECTED PACKAGE EDIT PANEL -->
    <!-- ============================================================ -->
    <?php if ($selectedPackage): ?>
        <div class="pkg-card">
            <h5>
                <span><i class="fas fa-edit me-2"></i> <?= htmlspecialchars($selectedPackage['name']) ?> – Edit करें</span>
                <span class="badge bg-warning text-dark">ID #<?= $selectedPackage['id'] ?></span>
            </h5>

            <form method="POST">
                <input type="hidden" name="action" value="update_package">
                <input type="hidden" name="package_id" value="<?= $selectedPackage['id'] ?>">

                <!-- Basic Fields -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Package Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($selectedPackage['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Price (₹) *</label>
                        <input type="number" name="price" class="form-control" step="0.01" required
                               value="<?= $selectedPackage['price'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Discount Price</label>
                        <input type="number" name="discount_price" class="form-control" step="0.01"
                               value="<?= $selectedPackage['discount_price'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Duration (महीने)</label>
                        <input type="number" name="duration" class="form-control"
                               value="<?= $selectedPackage['duration'] ?? 0 ?>">
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold mb-2" style="color: #1e3a8a;">
                    <i class="fas fa-check-square me-1"></i> इस Package में कौन-कौन से Fields रखने हैं?
                </h6>
                <p class="text-muted mb-3" style="font-size: 0.82rem;">
                    👉 जो Field चाहिए उसका <strong>Checkbox Tick</strong> करें और Value भरें। जो नहीं चाहिए उसका <strong>Tick हटा दें</strong>।
                </p>

                <div class="row g-2">
                    <?php foreach ($fields as $f):
                        $isIncluded = isset($fieldValues[$selectedPackage['id']][$f['id']]);
                        $val = $isIncluded
                            ? $fieldValues[$selectedPackage['id']][$f['id']]
                            : ($f['default_value'] ?? '');
                    ?>
                        <div class="col-md-6">
                            <div class="field-checkbox-row <?= $isIncluded ? 'included' : '' ?>" id="row-<?= $f['id'] ?>">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="checkbox"
                                           name="visible_<?= $f['id'] ?>"
                                           id="vis_<?= $f['id'] ?>"
                                           class="form-check-input"
                                           <?= $isIncluded ? 'checked' : '' ?>
                                           onchange="toggleFieldRow(this, <?= $f['id'] ?>)">
                                    <label for="vis_<?= $f['id'] ?>" class="form-check-label fw-bold mb-0">
                                        <?= htmlspecialchars($f['field_label']) ?>
                                    </label>
                                </div>
                                <div id="field-input-<?= $f['id'] ?>" style="<?= $isIncluded ? '' : 'display:none;' ?>">
                                    <?php if ($f['field_type'] === 'textarea'): ?>
                                        <textarea name="field_<?= $f['id'] ?>" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($val) ?></textarea>
                                    <?php elseif ($f['field_type'] === 'number'): ?>
                                        <input type="number" name="field_<?= $f['id'] ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($val) ?>">
                                    <?php else: ?>
                                        <input type="text" name="field_<?= $f['id'] ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($val) ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> Update Package
                    </button>
                    <a href="admin_packages.php" class="btn btn-secondary rounded-pill px-4">Cancel</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ क्या आप वाकई इस Package को DELETE करना चाहते हैं?');">
                        <input type="hidden" name="action" value="delete_package">
                        <input type="hidden" name="package_id" value="<?= $selectedPackage['id'] ?>">
                        <button type="submit" class="btn btn-danger rounded-pill px-4">
                            <i class="fas fa-trash me-2"></i> Delete Package
                        </button>
                    </form>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- ADD NEW PACKAGE FORM (Hidden, shown when ?add_new=1) -->
    <!-- ============================================================ -->
    <?php if (isset($_GET['add_new'])): ?>
        <div class="pkg-card">
            <h5><i class="fas fa-plus-circle me-2"></i> नया Package जोड़ें</h5>

            <form method="POST">
                <input type="hidden" name="action" value="add_package">

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Package Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Platinum">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Price (₹) *</label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Discount Price</label>
                        <input type="number" name="discount_price" class="form-control" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Duration (महीने)</label>
                        <input type="number" name="duration" class="form-control" value="0">
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold mb-2" style="color: #1e3a8a;">इस Package में Fields चुनें:</h6>

                <div class="row g-2">
                    <?php foreach ($fields as $f): ?>
                        <div class="col-md-6">
                            <div class="field-checkbox-row" id="new-row-<?= $f['id'] ?>">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="checkbox"
                                           name="visible_<?= $f['id'] ?>"
                                           id="new_vis_<?= $f['id'] ?>"
                                           class="form-check-input"
                                           onchange="toggleNewFieldRow(this, <?= $f['id'] ?>)">
                                    <label for="new_vis_<?= $f['id'] ?>" class="form-check-label fw-bold mb-0">
                                        <?= htmlspecialchars($f['field_label']) ?>
                                    </label>
                                </div>
                                <div id="new-field-input-<?= $f['id'] ?>" style="display:none;">
                                    <?php if ($f['field_type'] === 'textarea'): ?>
                                        <textarea name="field_<?= $f['id'] ?>" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($f['default_value'] ?? '') ?></textarea>
                                    <?php elseif ($f['field_type'] === 'number'): ?>
                                        <input type="number" name="field_<?= $f['id'] ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($f['default_value'] ?? '') ?>">
                                    <?php else: ?>
                                        <input type="text" name="field_<?= $f['id'] ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($f['default_value'] ?? '') ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> Save Package
                    </button>
                    <a href="admin_packages.php" class="btn btn-secondary rounded-pill px-4 ms-2">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleFieldRow(checkbox, fieldId) {
    const inputDiv = document.getElementById('field-input-' + fieldId);
    const row = document.getElementById('row-' + fieldId);
    if (checkbox.checked) {
        inputDiv.style.display = '';
        row.classList.add('included');
    } else {
        inputDiv.style.display = 'none';
        row.classList.remove('included');
    }
}

function toggleNewFieldRow(checkbox, fieldId) {
    const inputDiv = document.getElementById('new-field-input-' + fieldId);
    const row = document.getElementById('new-row-' + fieldId);
    if (checkbox.checked) {
        inputDiv.style.display = '';
        row.classList.add('included');
    } else {
        inputDiv.style.display = 'none';
        row.classList.remove('included');
    }
}

function renameField(fieldId, currentLabel) {
    const newLabel = prompt('Enter new label:', currentLabel);
    if (!newLabel || newLabel === currentLabel) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="rename_field">
        <input type="hidden" name="field_id" value="${fieldId}">
        <input type="hidden" name="field_label" value="${newLabel.replace(/"/g, '&quot;')}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'footer.php'; ?>
