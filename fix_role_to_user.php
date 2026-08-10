<?php
// ============================================================
// 🔄 Convert Sub-Admin to User (for shankarmudra995@gmail.com)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Only admin can run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$target_email = 'shankarmudra995@gmail.com';

// Find the user
$stmt = $pdo->prepare("SELECT id, role, is_super_admin FROM users WHERE email = ?");
$stmt->execute([$target_email]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ User with email '$target_email' not found.");
}

// ---- If form submitted ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Update role to 'user' and set is_super_admin = 0
    $update = $pdo->prepare("UPDATE users SET role = 'user', is_super_admin = 0 WHERE email = ?");
    $update->execute([$target_email]);
    echo "<div style='font-family: Arial; max-width: 700px; margin: 50px auto; padding: 30px; background: #f0fdf4; border-radius: 16px; border: 2px solid #22c55e;'>";
    echo "<h2 style='color: #166534;'>✅ Role Updated Successfully!</h2>";
    echo "<p><strong>$target_email</strong> is now a <strong>User</strong> (not Sub-Admin).</p>";
    echo "<p>Now they can log in and see their downline in <strong>My Team</strong> section.</p>";
    echo "<p><a href='admin_team.php?id=" . $user['id'] . "' style='color: #2563eb;'>👥 View their Team</a> | <a href='users.php' style='color: #2563eb;'>🔙 Back to Users</a></p>";
    echo "</div>";
    exit;
}

// ---- Show confirmation page ----
include 'header.php';
?>

<style>
    .container-card { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    .warning-box { background: #fef3c7; border-left: 5px solid #f59e0b; padding: 16px; border-radius: 8px; margin: 20px 0; }
    .btn-primary { background: #2563eb; color: #fff; padding: 10px 30px; border: none; border-radius: 30px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-secondary { background: #e2e8f0; color: #1e293b; padding: 10px 30px; border: none; border-radius: 30px; font-weight: 600; text-decoration: none; display: inline-block; }
    .btn-secondary:hover { background: #cbd5e1; }
</style>

<div class="container-card">
    <h2><i class="fas fa-exchange-alt me-2"></i>Change Role: Sub-Admin → User</h2>

    <div class="warning-box">
        <strong>⚠️ Action:</strong> This will change the role of <strong><?= htmlspecialchars($target_email) ?></strong> from <strong>Sub-Admin</strong> to <strong>User</strong>.
        <br><br>
        <strong>Current Role:</strong> <?= $user['role'] ?> (is_super_admin: <?= $user['is_super_admin'] ? 'Yes' : 'No' ?>)
        <br>
        <strong>New Role:</strong> User (is_super_admin: No)
    </div>

    <p>After this change, the user will be able to log in as a normal user and see their downline team in the "My Team" section.</p>

    <form method="POST">
        <button type="submit" name="confirm" class="btn-primary" onclick="return confirm('Are you sure you want to change <?= htmlspecialchars($target_email) ?> from Sub-Admin to User?')">
            <i class="fas fa-check"></i> Confirm & Update Role
        </button>
        <a href="users.php" class="btn-secondary" style="margin-left: 10px;">Cancel</a>
    </form>
</div>

<?php include 'footer.php'; ?>
