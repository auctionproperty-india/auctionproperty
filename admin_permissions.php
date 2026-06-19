<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }
if(!hasPermission('users', $pdo)) { die("Permission Denied."); }

// Update Permissions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $user_id = $_POST['user_id'];
    $perms = [
        'properties' => isset($_POST['perm_properties']),
        'users' => isset($_POST['perm_users']),
        'packages' => isset($_POST['perm_packages']),
        'subscriptions' => isset($_POST['perm_subscriptions']),
        'settings' => isset($_POST['perm_settings'])
    ];
    $json = json_encode($perms);
    $pdo->prepare("UPDATE users SET permissions = ?, role = 'admin' WHERE id = ?")->execute([$json, $user_id]);
    header("Location: admin_permissions.php?saved=1");
    exit;
}

include 'header.php'; 

// Fetch all non-super-admin users
$users = $pdo->query("SELECT * FROM users WHERE is_super_admin = FALSE ORDER BY id DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-user-shield me-2"></i>Manage Sub-Admins & Permissions</h4>
    <?php if(isset($_GET['saved'])) echo "<div class='alert alert-success'>✅ Permissions updated!</div>"; ?>
    <p class="text-muted">Assign specific modules to sub-admins. They will only see the modules you tick.</p>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr>
                <th>User</th>
                <th>Properties</th>
                <th>Users</th>
                <th>Packages</th>
                <th>Subscriptions</th>
                <th>Settings</th>
                <th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach($users as $u): 
                $perms = getUserPermissions($u['id'], $pdo);
            ?>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <tr>
                    <td><?= htmlspecialchars($u['name']) ?> <br><small class="text-muted"><?= $u['email'] ?></small></td>
                    <td><input type="checkbox" name="perm_properties" <?= ($perms['properties'] ?? false) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="perm_users" <?= ($perms['users'] ?? false) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="perm_packages" <?= ($perms['packages'] ?? false) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="perm_subscriptions" <?= ($perms['subscriptions'] ?? false) ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="perm_settings" <?= ($perms['settings'] ?? false) ? 'checked' : '' ?>></td>
                    <td><button type="submit" name="save_permissions" class="btn btn-sm btn-primary">Save</button></td>
                </tr>
            </form>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <small>Note: To make someone a Sub-Admin, ensure their Role is 'admin' and assign permissions here. Super Admin (admin@admin.com) has full access.</small>
</div>
<?php include 'footer.php'; ?>
