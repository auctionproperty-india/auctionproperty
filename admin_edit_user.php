<?php
// ============================================================
// ✏️ Edit User – Preserves original subscription amount
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header("Location: users.php?msg=invalid_user");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    header("Location: users.php?msg=user_not_found");
    exit;
}

// ---- Get current referrer name ----
$referrer_name = 'None';
if ($user['referred_by']) {
    $ref_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $ref_stmt->execute([$user['referred_by']]);
    $ref = $ref_stmt->fetch();
    if ($ref) {
        $referrer_name = $ref['name'] . ' (' . $ref['email'] . ')';
    }
}

// ---- Get current active subscription ----
$sub_stmt = $pdo->prepare("
    SELECT s.package_id, p.name as package_name, p.duration_months, 
           s.start_date, s.end_date, s.id as sub_id, s.amount as sub_amount
    FROM subscriptions s
    LEFT JOIN packages p ON s.package_id = p.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.id DESC LIMIT 1
");
$sub_stmt->execute([$user_id]);
$sub_info = $sub_stmt->fetch();
$current_pkg = $sub_info['package_id'] ?? null;
$pkg_expiry = $sub_info['end_date'] ?? null;
$sub_id = $sub_info['sub_id'] ?? null;
$sub_amount = $sub_info['sub_amount'] ?? 0;

$packages = $pdo->query("SELECT id, name, duration_months, price FROM packages ORDER BY id")->fetchAll();

$pkg_durations_js = [];
foreach ($packages as $pkg) {
    $pkg_durations_js[$pkg['id']] = (int)$pkg['duration_months'];
}

$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();

function safeDateFormat($dateStr) {
    if (empty($dateStr) || strtotime($dateStr) === false) {
        return '';
    }
    return date('Y-m-d', strtotime($dateStr));
}

$error = '';
$success = '';
$new_referrer_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $admin_password = $_POST['admin_password'] ?? '';

    $admin_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($admin_password, $admin['password'])) {
        $error = "❌ Invalid admin password. Changes not saved.";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $registration_date = $_POST['registration_date'] ?: null;
        $activation_date = $_POST['activation_date'] ?: null;
        $package_id = $_POST['package_id'] ? (int)$_POST['package_id'] : null;
        $custom_duration = !empty($_POST['custom_duration']) ? (int)$_POST['custom_duration'] : 0;
        $status = $_POST['status'];
        $new_password = trim($_POST['new_password']);
        $new_referrer_id = isset($_POST['new_referrer']) && $_POST['new_referrer'] !== '' ? (int)$_POST['new_referrer'] : null;

        if ($new_referrer_id) {
            $ref_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $ref_stmt->execute([$new_referrer_id]);
            $ref = $ref_stmt->fetch();
            if ($ref) {
                $new_referrer_name = $ref['name'] . ' (' . $ref['email'] . ')';
            }
        } else {
            $new_referrer_name = 'None';
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, 
                    created_at = COALESCE(?, created_at),
                    activation_date = COALESCE(?, activation_date),
                    status = ?,
                    referred_by = COALESCE(?, referred_by)
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $phone, $registration_date, $activation_date, $status, $new_referrer_id, $user_id]);

            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
            }

            // ============================================================
            // 🔥 FIX: Update subscription WITHOUT changing amount
            // ============================================================
            if ($package_id) {
                $duration = 0;
                foreach ($packages as $pkg) {
                    if ($pkg['id'] == $package_id) {
                        $duration = (int)$pkg['duration_months'];
                        break;
                    }
                }

                if ($custom_duration > 0) {
                    $duration = $custom_duration;
                }

                if (!empty($activation_date)) {
                    $new_start = $activation_date;
                } elseif (!empty($sub_info['start_date'])) {
                    $new_start = $sub_info['start_date'];
                } elseif (!empty($user['activation_date'])) {
                    $new_start = $user['activation_date'];
                } else {
                    $new_start = date('Y-m-d');
                }

                $new_end = null;
                if ($duration > 0) {
                    $new_end = date('Y-m-d', strtotime("$new_start + $duration months"));
                } elseif (!empty($sub_info['end_date'])) {
                    $new_end = $sub_info['end_date'];
                }

                if ($sub_id) {
                    // 🔥 UPDATE without touching amount column
                    $stmt = $pdo->prepare("
                        UPDATE subscriptions 
                        SET package_id = ?, start_date = ?, end_date = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$package_id, $new_start, $new_end, $sub_id]);
                } else {
                    // 🔥 INSERT new subscription - use package price as default (since there is no prior amount)
                    $pkg_price = 0;
                    foreach ($packages as $pkg) {
                        if ($pkg['id'] == $package_id) {
                            $pkg_price = (float)$pkg['price'];
                            break;
                        }
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO subscriptions (user_id, package_id, amount, status, start_date, end_date, created_at)
                        VALUES (?, ?, ?, 'active', ?, ?, NOW())
                    ");
                    $stmt->execute([$user_id, $package_id, $pkg_price, $new_start, $new_end]);
                }
            }

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $sub_stmt->execute([$user_id]);
            $sub_info = $sub_stmt->fetch();
            $current_pkg = $sub_info['package_id'] ?? null;
            $pkg_expiry = $sub_info['end_date'] ?? null;
            $sub_id = $sub_info['sub_id'] ?? null;
            $sub_amount = $sub_info['sub_amount'] ?? 0;

            $referrer_name = $new_referrer_name;

            $success = "✅ User updated successfully! (Subscription amount preserved: ₹" . number_format($sub_amount, 2) . ")";
            if ($new_referrer_name != 'None') {
                $success .= " New Referrer: <strong>" . htmlspecialchars($new_referrer_name) . "</strong>";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ Error updating user: " . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<style>
    .edit-form .form-label { font-weight: 600; color: #1e293b; }
    .edit-form .form-control, .edit-form .form-select { border-radius: 10px; border: 1px solid #e2e8f0; padding: 10px 14px; }
    .edit-form .form-control:focus, .edit-form .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .referral-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
    .referral-card h6 { color: #1e293b; font-weight: 700; }
    .referral-card .text-muted { color: #64748b !important; font-size: 0.85rem; }
    .current-referrer { background: #eef2ff; border-radius: 8px; padding: 8px 14px; display: inline-block; margin-top: 4px; }
    .current-referrer strong { color: #1e3a8a; }
    #referrerSearch { margin-bottom: 8px; }
    #referrerSelect { max-height: 200px; overflow-y: auto; }
    .password-confirm { border-left: 4px solid #ef4444; background: #fef2f2; padding-left: 12px; border-radius: 4px; }
    .help-box { background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px; padding: 10px 14px; font-size: 0.82rem; color: #1e40af; margin-bottom: 10px; }
    .expiry-preview { background: #ecfdf5; border: 2px dashed #10b981; border-radius: 10px; padding: 12px 16px; text-align: center; }
    .expiry-preview .lbl { font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
    .expiry-preview .val { font-size: 1.2rem; font-weight: 800; color: #059669; margin-top: 2px; }
    .expiry-preview .sub { font-size: 0.72rem; color: #64748b; margin-top: 4px; }
    .warning-box { background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 10px 14px; font-size: 0.82rem; color: #92400e; margin-bottom: 10px; }
    .amount-display { background: #fef3c7; border: 2px solid #f59e0b; border-radius: 10px; padding: 12px 16px; text-align: center; }
    .amount-display .lbl { font-size: 0.7rem; text-transform: uppercase; color: #92400e; font-weight: 700; }
    .amount-display .val { font-size: 1.3rem; font-weight: 800; color: #b45309; margin-top: 2px; }
    .amount-display .sub { font-size: 0.72rem; color: #92400e; margin-top: 4px; }
</style>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card-premium edit-form">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-user-edit me-2"></i>Edit User</h4>
                    <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Users</a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="help-box">
                    <i class="fas fa-shield-alt me-1"></i>
                    <strong>Amount Protected:</strong> जब आप पैकेज या date बदलेंगे, तो सब्सक्रिप्शन का <b>original amount</b> (जो approval के समय दर्ज हुआ था) वैसा ही रहेगा — package price से overwrite नहीं होगा।
                </div>

                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="blocked" <?= $user['status'] == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Registration Date</label>
                            <input type="date" name="registration_date" class="form-control" value="<?= safeDateFormat($user['created_at']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Activation Date</label>
                            <input type="date" name="activation_date" id="activationDate" class="form-control" value="<?= safeDateFormat($user['activation_date']) ?>">
                            <small class="text-muted">इसे बदलने पर Expiry auto-update होगी।</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Package</label>
                            <select name="package_id" id="packageSelect" class="form-control">
                                <option value="">Free</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" <?= ($current_pkg == $pkg['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pkg['name']) ?>
                                        <?php if (!empty($pkg['duration_months'])): ?>
                                            (<?= $pkg['duration_months'] ?> months)
                                        <?php else: ?>
                                            (⚠️ No Duration)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Custom Duration (months) <span class="text-muted">— optional</span></label>
                            <input type="number" name="custom_duration" id="customDuration" class="form-control" 
                                   min="0" max="120" placeholder="Leave empty to use package duration" value="0">
                        </div>

                        <!-- 🔥 SUBSCRIPTION AMOUNT DISPLAY (read-only) -->
                        <div class="col-md-6">
                            <label class="form-label">Subscription Amount (Original)</label>
                            <div class="amount-display">
                                <div class="lbl">Amount Paid by User</div>
                                <div class="val">₹ <?= number_format($sub_amount, 2) ?></div>
                                <div class="sub">This amount is used for payout calculations</div>
                            </div>
                        </div>

                        <!-- 🔥 LIVE EXPIRY PREVIEW -->
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date Preview</label>
                            <div class="expiry-preview">
                                <div class="lbl">New Expiry Date</div>
                                <div class="val" id="expiryPreview">
                                    <?= $pkg_expiry ? date('d M Y', strtotime($pkg_expiry)) : 'No expiry' ?>
                                </div>
                                <div class="sub" id="expiryReason">Existing subscription</div>
                            </div>
                        </div>

                        <div class="col-md-12" id="durationWarning" style="display:none;">
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>ध्यान दें:</strong> इस पैकेज में <b>duration_months = 0</b> है। Expiry calculate करने के लिए ऊपर <b>Custom Duration</b> में महीने भरें या <a href="admin_packages.php" target="_blank">admin_packages.php</a> में जाकर पैकेज का duration सेट करें।
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="referral-card">
                                <h6><i class="fas fa-link me-2"></i>Referrer Management</h6>

                                <div class="mb-3">
                                    <label class="form-label">Current Referrer</label>
                                    <div class="current-referrer">
                                        <strong><?= htmlspecialchars($referrer_name) ?></strong>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Search Referrer</label>
                                    <input type="text" id="referrerSearch" class="form-control" placeholder="Type name or email to filter...">
                                </div>

                                <label class="form-label">Change Referrer (Team Shift)</label>
                                <select name="new_referrer" id="referrerSelect" class="form-control" size="5">
                                    <option value="">— Remove Referrer (None) —</option>
                                    <?php foreach ($all_users as $u): ?>
                                        <?php if ($u['id'] == $user_id) continue; ?>
                                        <option value="<?= $u['id'] ?>" <?= ($user['referred_by'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="text" name="new_password" class="form-control" placeholder="Enter new password">
                        </div>

                        <div class="col-md-12 password-confirm">
                            <label class="form-label">Admin Password <span class="text-danger">*</span></label>
                            <input type="password" name="admin_password" class="form-control" required placeholder="Enter your admin password to confirm changes">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" name="update_user" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="users.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const PKG_DURATIONS = <?= json_encode($pkg_durations_js) ?>;
const EXISTING_END = <?= json_encode($pkg_expiry ? safeDateFormat($pkg_expiry) : '') ?>;

document.addEventListener('DOMContentLoaded', function() {
    const activationInput = document.getElementById('activationDate');
    const packageSelect = document.getElementById('packageSelect');
    const customDurationInput = document.getElementById('customDuration');
    const expiryPreview = document.getElementById('expiryPreview');
    const expiryReason = document.getElementById('expiryReason');
    const durationWarning = document.getElementById('durationWarning');

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return parts[2] + ' ' + months[parseInt(parts[1]) - 1] + ' ' + parts[0];
    }

    function addMonths(dateStr, months) {
        if (!dateStr || months <= 0) return '';
        const d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        const origDay = d.getDate();
        d.setMonth(d.getMonth() + months);
        if (d.getDate() !== origDay) {
            d.setDate(0);
        }
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return yyyy + '-' + mm + '-' + dd;
    }

    function recalculate() {
        const activationDate = activationInput.value;
        const pkgId = packageSelect.value;
        const customDur = parseInt(customDurationInput.value) || 0;
        const pkgDur = PKG_DURATIONS[pkgId] || 0;

        let duration = customDur > 0 ? customDur : pkgDur;

        if (pkgId && pkgDur === 0 && customDur === 0) {
            durationWarning.style.display = 'block';
        } else {
            durationWarning.style.display = 'none';
        }

        if (!pkgId) {
            expiryPreview.textContent = '—';
            expiryReason.textContent = 'Free user (no package)';
            return;
        }

        if (!activationDate) {
            if (EXISTING_END) {
                expiryPreview.textContent = formatDate(EXISTING_END);
                expiryReason.textContent = 'Existing expiry (no activation date)';
            } else {
                expiryPreview.textContent = '—';
                expiryReason.textContent = 'Activation date required';
            }
            return;
        }

        if (duration > 0) {
            const newEnd = addMonths(activationDate, duration);
            expiryPreview.textContent = formatDate(newEnd);
            const label = customDur > 0 ? 'Custom duration' : 'Package duration';
            expiryReason.textContent = `${activationDate} + ${duration} months (${label})`;
        } else {
            if (EXISTING_END) {
                expiryPreview.textContent = formatDate(EXISTING_END);
                expiryReason.textContent = 'No duration set — existing expiry preserved';
            } else {
                expiryPreview.textContent = '—';
                expiryReason.textContent = 'No duration set in package';
            }
        }
    }

    if (activationInput) {
        activationInput.addEventListener('change', recalculate);
        activationInput.addEventListener('input', recalculate);
    }
    if (packageSelect) packageSelect.addEventListener('change', recalculate);
    if (customDurationInput) {
        customDurationInput.addEventListener('input', recalculate);
        customDurationInput.addEventListener('change', recalculate);
    }

    recalculate();
});

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('referrerSearch');
    const select = document.getElementById('referrerSelect');
    if (!searchInput || !select) return;
    
    const options = select.querySelectorAll('option');
    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();
        options.forEach(opt => {
            const text = opt.textContent.toLowerCase();
            opt.style.display = (text.includes(filter) || filter === '') ? '' : 'none';
        });
    });
});
</script>

<?php include 'footer.php'; ?>
