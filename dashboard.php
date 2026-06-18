<?php 
require_once 'db.php'; 
include 'header.php'; 

// ---- Admin Actions (GET Requests) ----
if(isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $pdo->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
    header("Location: dashboard.php");
    exit;
}
if(isset($_GET['delete_user'])) {
    $id = $_GET['delete_user'];
    if($id != $_SESSION['user_id']) { 
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    }
    header("Location: dashboard.php");
    exit;
}
if(isset($_GET['reset_pass'])) {
    $id = $_GET['reset_pass'];
    $new_pass = bin2hex(random_bytes(4)); 
    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
    $_SESSION['new_pass_display'] = "✅ New Password for User ID $id is: <strong>$new_pass</strong>";
    header("Location: dashboard.php");
    exit;
}

// ---- Stats ----
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<div class="row">
    <div class="col-md-4"><div class="card p-4 shadow-sm"><h5>🏢 Properties</h5><h2><?= $total_props ?></h2></div></div>
    <div class="col-md-4"><div class="card p-4 shadow-sm"><h5>👥 Users</h5><h2><?= $total_users ?></h2></div></div>
</div>

<?php if(isset($_SESSION['new_pass_display'])): ?>
    <div class="alert alert-success mt-3"><?= $_SESSION['new_pass_display']; unset($_SESSION['new_pass_display']); ?></div>
<?php endif; ?>

<?php if($_SESSION['role'] == 'admin'): ?>
<div id="users-section" class="mt-4">
    <div class="card p-4 shadow-sm">
        <h4>👥 Manage Users & Admins</h4>
        <table class="table table-bordered table-hover bg-white mt-2">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php 
            $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
            foreach($users as $u) { 
                $is_self = ($u['id'] == $_SESSION['user_id']);
            ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= $u['email'] ?></td>
                    <td><span class="badge bg-<?= ($u['role']=='admin')?'danger':'info' ?>"><?= $u['role'] ?></span></td>
                    <td><span class="badge bg-<?= ($u['status']=='active')?'success':'secondary' ?>"><?= $u['status'] ?></span></td>
                    <td>
                        <?php if(!$is_self): ?>
                            <a href="?toggle_status=<?= $u['id'] ?>" class="btn btn-sm btn-<?= ($u['status']=='active')?'warning':'success' ?>">
                                <?= ($u['status']=='active')?'Disable':'Enable' ?>
                            </a>
                            <a href="?reset_pass=<?= $u['id'] ?>" class="btn btn-sm btn-info" onclick="return confirm('Reset password?')">Reset Pass</a>
                            <a href="?delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                        <?php else: ?>
                            <span class="text-muted">(You)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
