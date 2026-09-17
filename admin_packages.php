<?php
// ============================================================
// 🎁 Admin Packages Manager – Per-Package Field Control
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

                // Save fields that are marked visible
                $fields = $pdo->query("SELECT * FROM package_fields WHERE is_active = TRUE")->fetchAll();
                foreach ($fields as $f) {
                    $is_visible = isset($_POST['visible_' . $f['id']]) ? 1 : 0;
                    if (!$is_visible) continue; // Skip hidden fields

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

            // First remove all existing values for this package
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

    // --- Clear Field for a Package (Hide) ---
    if ($_POST['action'] === 'clear_package_field') {
        $package_id = (int)$_POST['package_id'];
        $field_id = (int)$_POST['field_id'];
        try {
            $pdo->prepare("DELETE FROM package_field_values WHERE package_id = ? AND field_id = ?")->execute([$package_id, $field_id]);
            $message = "Field removed from this package only!";
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

// Get all field values with visibility
$fieldValues = [];
$stmt = $pdo->query("SELECT * FROM package_field_values WHERE is_visible = TRUE");
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
        padding: 12px 14px;
        margin-bottom: 8px;
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
    .field-checkbox-row {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 8px;
        border: 1px solid #eef2f6;
    }
    .field-checkbox-row.included {
        background: #ecfdf5;
        border-color: #a7f3d0;
    }
    .form-check-input {
        width: 1.2em;
        height: 1.2em;
        margin-top: 0.15em;
        cursor: pointer;
    }
    .form-check-input:checked {
        background-color: #10b981;
        border-color: #10b981;
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
                    <span><i class="fas fa-columns me-2"></i> Global Fields (Master List)</span>
                    <span class="badge bg-primary"><?= count($fields) ?></span>
                </h5>
                <p class="text-muted" style="font-size: 0.8rem;">
                    ⚠️ यह Master List है। इन Fields को हर Package में अलग-अलग Add/Remove किया जा सकता है।
                </p>

                <div style="max-height: 380px; overflow-y: auto; margin-bottom: 20px;">
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
                                        onclick="renameField(<?= $f['id'] ?>, '<?= htmlspecialchars($f['field_label'], ENT_QUOTES) ?>')" title="Rename">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ यह Field सभी Packages से DELETE हो जाएगा!\n\nक्या आप वाकई Delete करना चाहते हैं?');">
                                    <input type="hidden" name="action" value="delete_field">
                                    <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-sm-custom btn-danger" title="Delete from all">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>

                <h6 class="fw-bold mb-3" style="color: #1e3a8a;">
                    <i class="fas fa-plus-circle me-2"></i> नया Field जोड़ें
                </h6>
                <form method="POST">
                    <input type="hidden" name="action" value="add_field">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Field Label *</label>
                            <input type="text" name="field_label" class="form-control form-control-sm" required placeholder="e.g. Free Parking">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Field Key * (English)</label>
                            <input type="text" name="field_key" class="form-control form-control-sm" required placeholder="e.g. free_parking">
                            <small class="text-muted" style="font-size: 0.62rem;">सिर्फ a-z, 0-9, _</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Type</label>
                            <select name="field_type" class="form-control form-control-sm">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="textarea">Textarea</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Default Value</label>
                            <input type="text" name="default_value" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Display Order</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="<?= count($fields) + 1 ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 btn-sm-custom">
                                <i class="fas fa-plus"></i> Add Field to Master
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
                    <span><i class="fas fa-<?= $editMode ? 'edit' : 'plus' ?> me-2"></i> <?= $editMode ? 'Edit Package: ' . htmlspecialchars($editPackage['name'] ?? '') : 'Add New Package' ?></span>
                    <?php if ($editMode): ?>
                        <a href="admin_packages.php" class="btn btn-sm btn-secondary btn-sm-custom">Cancel Edit</a>
                    <?php endif; ?>
                </h5>

                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editMode ? 'update_package' : 'add_package' ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="package_id" value="<?= $editPackage['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">Package Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= $editMode ? htmlspecialchars($editPackage['name'] ?? '') : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Price (₹) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" required
                                   value="<?= $editMode ? ($editPackage['price'] ?? '') : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Discount</label>
                            <input type="number" name="discount_price" class="form-control" step="0.01"
                                   value="<?= $editMode ? ($editPackage['discount_price'] ?? '') : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Duration</label>
                            <input type="number" name="duration" class="form-control"
                                   value="<?= $editMode ? ($editPackage['duration'] ?? '') : '' ?>">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold small mb-2" style="color: #1e3a8a;">
                        <i class="fas fa-check-square me-1"></i> इस Package में कौन-कौन से Fields रखने हैं?
                    </h6>
                    <p class="text-muted" style="font-size: 0.78rem;">
                        👉 जो Field चाहिए उसका <strong>Checkbox Tick</strong> करें और Value भरें। जो नहीं चाहिए उसका <strong>Tick हटा दें</strong>।
                    </p>

                    <div class="row g-2">
                        <?php foreach ($fields as $f):
                            $isIncluded = $editMode && isset($fieldValues[$editPackage['id']][$f['id']]);
                            $val = $isIncluded
                                ? $fieldValues[$editPackage['id']][$f['id']]
                                : ($f['default_value'] ?? '');
                        ?>
                            <div class="col-md-12">
                                <div class="field-checkbox-row <?= $isIncluded ? 'included' : '' ?>">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <input type="checkbox"
                                               name="visible_<?= $f['id'] ?>"
                                               id="vis_<?= $f['id'] ?>"
                                               class="form-check-input"
                                               <?= $isIncluded ? 'checked' : '' ?>
                                               onchange="toggleFieldRow(this, <?= $f['id'] ?>)">
                                        <label for="vis_<?= $f['id'] ?>" class="form-check-label fw-bold" style="cursor:pointer; font-size: 0.88rem;">
                                            <?= htmlspecialchars($f['field_label']) ?>
                                            <span class="field-key"><?= htmlspecialchars($f['field_key']) ?></span>
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
    <!-- PACKAGE TABLE -->
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
                        <th>Duration</th>
                        <?php foreach ($fields as $f): ?>
                            <th><?= htmlspecialchars($f['field_label']) ?></th>
                        <?php endforeach; ?>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packages)): ?>
                        <tr><td colspan="<?= 5 + count($fields) ?>" class="text-center text-muted py-4">No packages yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($packages as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td>
                                <?php if (!empty($p['discount_price'])): ?>
                                    <span style="text-decoration: line-through; color: #94a3b8; font-size: 0.75rem;">₹ <?= number_format($p['price']) ?></span>
                                    <br><strong style="color: #10b981;">₹ <?= number_format($p['discount_price']) ?></strong>
                                <?php else: ?>
                                    ₹ <?= number_format($p['price'] ?? 0) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['duration'] ?? 0) ?> mo</td>
                            <?php foreach ($fields as $f): ?>
                                <td>
                                    <?php if (isset($fieldValues[$p['id']][$f['id']])): ?>
                                        <span style="color: #0f172a; font-size: 0.78rem;">
                                            <?= htmlspecialchars($fieldValues[$p['id']][$f['id']]) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" title="इस Package में नहीं है">—</span>
                                    <?php endif; ?>
                                </td>
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

<script>
function toggleFieldRow(checkbox, fieldId) {
    const row = document.getElementById('field-input-' + fieldId);
    const parent = checkbox.closest('.field-checkbox-row');
    if (checkbox.checked) {
        row.style.display = '';
        parent.classList.add('included');
    } else {
        row.style.display = 'none';
        parent.classList.remove('included');
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
        <input type="hidden" name="field_label" value="${newLabel}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'footer.php'; ?>
