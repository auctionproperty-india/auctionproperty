<?php
// ============================================================
// ✏️ Edit User – Separate Page (No Modal Blank Issue)
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

// ---- Get current package ----
$pkg_stmt = $pdo->prepare("
    SELECT s.package_id, p.name as package_name, s.start_date, s.end_date
    FROM subscriptions s
    LEFT JOIN packages p ON s.package_id = p.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.id DESC LIMIT 1
");
$pkg_stmt->execute([$user_id]);
$sub_info = $pkg_stmt->fetch();
$current_pkg = $sub_info['package_id'] ?? null;
$pkg_expiry = $sub_info['end_date'] ?? null;

// ---- Get all packages ----
$packages = $pdo->query("SELECT id, name FROM packages ORDER BY id")->fetchAll();

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $registration_date = $_POST['registration_date'] ?: null;
    $activation_date = $_POST['activation_date'] ?: null;
    $package_id = $_POST['package_id'] ? (int)$_POST['package_id'] : null;
    $status = $_POST['status'];
    $new_password = trim($_POST['new_password']);
    $new_referrer_id = isset($_POST['new_referrer']) ? (int)$_POST['new_referrer'] : null;

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

        if ($package_id) {
            $sub_stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
            $sub_stmt->execute([$user_id]);
            $sub = $sub_stmt->fetch();
            if ($sub) {
                $stmt = $pdo->prepare("UPDATE subscriptions SET package_id = ? WHERE id = ?");
                $stmt->execute([$package_id, $sub['id']]);
                // Update end_date based on new package
                $duration = $pdo->prepare("SELECT duration_months FROM packages WHERE id = ?");
                $duration->execute([$package_id]);
                $months = $duration->fetchColumn();
                if ($months) {
                    $new_end = date('Y-m-d', strtotime("+$months months", strtotime($sub['start_date'])));
                    $pdo->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?")->execute([$new_end, $sub['id']]);
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO subscriptions (user_id, package_id, amount, status, start_date, end_date, created_at)
                    VALUES (?, ?, 0, 'active', CURRENT_DATE, CURRENT_DATE + INTERVAL '30 days', NOW())
                ");
                $stmt->execute([$user_id, $package_id]);
            }
        }

        $pdo->commit();
        header("Location: users.php?msg=updated");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating user: " . $e->getMessage();
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
</style>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card-premium edit-form">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-user-edit me-2"></i>Edit User</h4>
                    <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Users</a>
                </div>

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
                            <small class="text-muted">Expiry is auto-calculated</small>
                        </div>

                        <!-- Referrer Change -->
                        <div class="col-md-12">
                            <div class="referral-card">
                                <h6><i class="fas fa-link me-2"></i>Change Referrer (Team Shift)</h6>
                                <p class="text-muted">
                                    Changing the referrer will move this user and their entire downline team to the new referrer.
                                </p>
                                <div class="mb-2">
                                    <label class="form-label">New Referrer</label>
                                    <select name="new_referrer" class="form-control">
                                        <option value="">— No Referrer (None) —</option>
                                        <?php foreach ($all_users as $u): ?>
                                            <?php if ($u['id'] == $user_id) continue; ?>
                                            <option value="<?= $u['id'] ?>" <?= ($user['referred_by'] == $u['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        Current referrer: <?= $user['referrer_name'] ?? 'None' ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="text" name="new_password" class="form-control" placeholder="Enter new password">
                            <small class="text-muted">Minimum 6 characters recommended.</small>
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

<?php include 'footer.php'; ?>
