<?php
// ============================================================
// ✏️ Edit User – With Referrer Search + Admin Password Confirmation
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

// ---- Fetch user data ----
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
           s.start_date, s.end_date, s.id as sub_id
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

// ---- Get all packages ----
$packages = $pdo->query("SELECT id, name, duration_months FROM packages ORDER BY id")->fetchAll();

// ---- Get all users for referrer dropdown ----
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();

// ---- Safe Date Format ----
function safeDateFormat($dateStr) {
    if (empty($dateStr) || strtotime($dateStr) === false) {
        return '';
    }
    return date('Y-m-d', strtotime($dateStr));
}

// ---- Handle Update ----
$error = '';
$success = '';
$new_referrer_name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $admin_password = $_POST['admin_password'] ?? '';

    // ---- Verify admin password ----
    $admin_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($admin_password, $admin['password'])) {
        $error = "❌ Invalid admin password. Changes not saved.";
    } else {
        // ---- Collect form data ----
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $registration_date = $_POST['registration_date'] ?: null;
        $activation_date = $_POST['activation_date'] ?: null;
        $package_id = $_POST['package_id'] ? (int)$_POST['package_id'] : null;
        $status = $_POST['status'];
        $new_password = trim($_POST['new_password']);
        $new_referrer_id = isset($_POST['new_referrer']) ? (int)$_POST['new_referrer'] : null;

        // ---- Get new referrer name for success message ----
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
            // ---- Update user ----
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

            // ---- Update password if provided ----
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
            }

            // ============================================================
            // 🔥 FIX: Update subscription with new activation_date & package
            // ============================================================
            if ($package_id) {
                // Get package duration
                $duration = 0;
                foreach ($packages as $pkg) {
                    if ($pkg['id'] == $package_id) {
                        $duration = (int)$pkg['duration_months'];
                        break;
                    }
                }

                if ($sub_id) {
                    // Existing active subscription – update package, start_date, end_date
                    $new_start = $activation_date ?: $sub_info['start_date'] ?? date('Y-m-d');
                    if ($duration > 0) {
                        $new_end = date('Y-m-d', strtotime("$new_start + $duration months"));
                    } else {
                        $new_end = null; // if no duration, keep null
                    }
                    $stmt = $pdo->prepare("
                        UPDATE subscriptions 
                        SET package_id = ?, start_date = ?, end_date = ?,
                            amount = (SELECT price FROM packages WHERE id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$package_id, $new_start, $new_end, $package_id, $sub_id]);
                } else {
                    // No active subscription – create a new one
                    if (!empty($activation_date) && $duration > 0) {
                        $new_end = date('Y-m-d', strtotime("$activation_date + $duration months"));
                        $stmt = $pdo->prepare("
                            INSERT INTO subscriptions (user_id, package_id, amount, status, start_date, end_date, created_at)
                            VALUES (?, ?, (SELECT price FROM packages WHERE id = ?), 'active', ?, ?, NOW())
                        ");
                        $stmt->execute([$user_id, $package_id, $package_id, $activation_date, $new_end]);
                        // Fetch the new sub_id for expiry display
                        $sub_id = $pdo->lastInsertId();
                        $sub_info = $pdo->prepare("SELECT start_date, end_date FROM subscriptions WHERE id = ?")
                            ->execute([$sub_id]) ? $sub_info = $pdo->fetch() : null;
                    } else {
                        // No activation date or no duration – create a pending subscription or just skip
                        // We'll create a pending one with no dates.
                        $stmt = $pdo->prepare("
                            INSERT INTO subscriptions (user_id, package_id, amount, status, created_at)
                            VALUES (?, ?, (SELECT price FROM packages WHERE id = ?), 'pending', NOW())
                        ");
                        $stmt->execute([$user_id, $package_id, $package_id]);
                    }
                }
            } else {
                // If package_id is empty (Free), we might want to deactivate or remove subscription?
                // Usually we would set subscription status to 'cancelled' or leave as is.
                // For simplicity, we'll keep existing subscription but update package to null? 
                // But we'll just leave it.
            }

            $pdo->commit();

            // ---- Update referrer name for display ----
            $referrer_name = $new_referrer_name;

            // ---- Refresh user data ----
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // Refresh subscription info
            $sub_stmt->execute([$user_id]);
            $sub_info = $sub_stmt->fetch();
            $pkg_expiry = $sub_info['end_date'] ?? null;

            $success = "✅ User updated successfully!";
            if (!empty($new_referrer_name) && $new_referrer_name != 'None') {
                $success .= " New Referrer: <strong>" . htmlspecialchars($new_referrer_name) . "</strong>";
            } else {
                $success .= " Referrer removed.";
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
                            <input type="date" name="activation_date" class="form-control" value="<?= safeDateFormat($user['activation_date']) ?>">
                            <small class="text-muted">If set, subscription start date will be updated.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Package</label>
                            <select name="package_id" class="form-control">
                                <option value="">Free</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" <?= ($current_pkg == $pkg['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pkg['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Package Expiry Date</label>
                            <input type="text" class="form-control" value="<?= $pkg_expiry ? date('d M Y', strtotime($pkg_expiry)) : 'No active subscription' ?>" readonly>
                            <small class="text-muted">Auto-calculated from activation date + package duration</small>
                        </div>

                        <!-- ====== REFERRAL SECTION ====== -->
                        <div class="col-md-12">
                            <div class="referral-card">
                                <h6><i class="fas fa-link me-2"></i>Referrer Management</h6>

                                <!-- Current Referrer Display -->
                                <div class="mb-3">
                                    <label class="form-label">Current Referrer</label>
                                    <div class="current-referrer">
                                        <strong><?= htmlspecialchars($referrer_name) ?></strong>
                                    </div>
                                </div>

                                <!-- Search Box -->
                                <div class="mb-2">
                                    <label class="form-label">Search Referrer</label>
                                    <input type="text" id="referrerSearch" class="form-control" placeholder="Type name or email to filter...">
                                </div>

                                <!-- Dropdown to Change Referrer -->
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
                                <small class="text-muted">
                                    Changing the referrer will move this user and their entire downline team to the new referrer.
                                </small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="text" name="new_password" class="form-control" placeholder="Enter new password">
                            <small class="text-muted">Minimum 6 characters recommended.</small>
                        </div>

                        <!-- ====== ADMIN PASSWORD CONFIRMATION ====== -->
                        <div class="col-md-12 password-confirm">
                            <label class="form-label">Admin Password <span class="text-danger">*</span></label>
                            <input type="password" name="admin_password" class="form-control" required placeholder="Enter your admin password to confirm changes">
                            <small class="text-muted">Admin password is required to save any changes.</small>
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
// JavaScript to filter the dropdown options based on search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('referrerSearch');
    const select = document.getElementById('referrerSelect');
    const options = select.querySelectorAll('option');

    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();
        options.forEach(opt => {
            const text = opt.textContent.toLowerCase();
            if (text.includes(filter) || filter === '') {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
