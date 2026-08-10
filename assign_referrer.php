<?php
// ============================================================
// 🔄 Assign All Users Without Referrer to a Specific User
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ---- Only Admin can run this script ----
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- Target Email (जिसके नीचे सभी Users Map होंगे) ----
$target_email = 'shankarmudra995@gmail.com'; // ✅ आप चाहें तो बदल सकते हैं

// ---- Find the target user ----
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$target_email]);
$target = $stmt->fetch();
if (!$target) {
    die("❌ User with email '$target_email' not found. Please create this user first.");
}
$target_id = $target['id'];

// ---- Count users without referrer ----
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by IS NULL AND id != ?");
$count_stmt->execute([$target_id]);
$total = $count_stmt->fetchColumn();

// ---- If form submitted ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // ---- Update all users without referrer to target user ----
    $update = $pdo->prepare("UPDATE users SET referred_by = ? WHERE referred_by IS NULL AND id != ?");
    $update->execute([$target_id, $target_id]);
    $updated = $update->rowCount();

    echo "<div style='font-family: Arial; max-width: 700px; margin: 50px auto; padding: 30px; background: #f0fdf4; border-radius: 16px; border: 2px solid #22c55e;'>";
    echo "<h2 style='color: #166534;'>✅ Success!</h2>";
    echo "<p style='font-size: 1.2rem;'>$updated users have been assigned to <strong>$target_email</strong>.</p>";
    echo "<p><a href='users.php' style='color: #2563eb;'>🔙 Back to User Management</a></p>";
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
    <h2><i class="fas fa-users me-2"></i>Assign Users Without Referrer</h2>

    <div class="warning-box">
        <strong>⚠️ Action:</strong> All users who <strong>do not have any referrer</strong> (referred_by IS NULL) will be assigned to:
        <br><br>
        <strong>Target User:</strong> <?= htmlspecialchars($target_email) ?> (ID: <?= $target_id ?>)
        <br>
        <strong>Total Users to be affected:</strong> <?= $total ?>
    </div>

    <?php if ($total == 0): ?>
        <div class="alert alert-success">
            🎉 All users already have a referrer. No action needed.
        </div>
        <a href="users.php" class="btn-secondary">Back to Users</a>
    <?php else: ?>
        <p>This will update the referrer for all these users in one go.</p>
        <form method="POST">
            <button type="submit" name="confirm" class="btn-primary" onclick="return confirm('Are you sure you want to assign <?= $total ?> users to <?= htmlspecialchars($target_email) ?>?')">
                <i class="fas fa-check"></i> Confirm & Assign All
            </button>
            <a href="users.php" class="btn-secondary" style="margin-left: 10px;">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
