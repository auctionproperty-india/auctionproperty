<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

// ✅ सिर्फ Super Admin ही इस पेज को देख/इस्तेमाल कर सकता है
if(!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    die("<div class='alert alert-danger m-5' style='background:white; padding:20px; border-radius:10px;'>❌ Access Denied. <br> Only Super Admin can manage Sub-Admins.</div>");
}

$error = '';
// ---- Create Sub-Admin ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_subadmin'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $ref_code = generateReferralCode();
    
    $perms = [];
    $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
    foreach($modules as $mod) {
        $view = isset($_POST['view_' . $mod]);
        $edit = isset($_POST['edit_' . $mod]);
        $perms[$mod] = ['view' => $view, 'edit' => $edit];
    }
    
    try {
        $sql = "INSERT INTO users (name, email, password, referral_code, role, permissions, status, is_super_admin) VALUES (?,?,?,?, 'admin', ?, 'active', FALSE)";
        $pdo->prepare($sql)->execute([$name, $email, $password, $ref_code, json_encode($perms)]);
        header("Location: admin_permissions.php?created=1");
        exit;
    } catch(PDOException $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// ---- Update Permissions ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $user_id = $_POST['user_id'];
    $perms = [];
    $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
    foreach($modules as $mod) {
        $view = isset($_POST['view_' . $mod]);
        $edit = isset($_POST['edit_' . $mod]);
        $perms[$mod] = ['view' => $view, 'edit' => $edit];
    }
    $pdo->prepare("UPDATE users SET permissions = ? WHERE id = ?")->execute([json_encode($perms), $user_id]);
    header("Location: admin_permissions.php?saved=1");
    exit;
}

include 'header.php'; 
$users = $pdo->query("SELECT * FROM users WHERE is_super_admin = FALSE ORDER BY id DESC")->fetchAll();
?>
<div class="card-premium mb-4" style="border-left:4px solid #2563eb;">
    <h4><i class="fas fa-user-plus me-2"></i>Create New Sub-Admin</h4>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Full Name" required></div>
            <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
            <div class="col-md-3"><input type="text" name="password" class="form-control" placeholder="Password" required></div>
            <div class="col-md-3"><button type="submit" name="create_subadmin" class="btn btn-success w-100"><i class="fas fa-user-shield"></i> Create</button></div>
        </div>
        <hr>
        <p class="fw-bold">Assign Permissions:</p>
        <div class="row g-2">
            <?php 
            $all_modules = ['properties'=>'🏠 Properties','users'=>'👥 Users','packages'=>'📦 Packages','subscriptions'=>'📋 Subscriptions','settings'=>'⚙️ Settings','referrals'=>'💰 Referrals','accounting'=>'💰 Accounting'];
            foreach($all_modules as $key => $label): ?>
            <div class="col-md-3">
                <div class="card p-2 text-center bg-light">
                    <small class="fw-bold"><?= $label ?></small>
                    <div><input class="form-check-input" type="checkbox" name="view_<?= $key ?>" checked> View</div>
                    <div><input class="form-check-input" type="checkbox" name="edit_<?= $key ?>" checked> Edit</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<div class="card-premium">
    <h4><i class="fas fa-users-cog me-2"></i>Manage Sub-Admins</h4>
    <?php if(isset($_GET['created'])) echo "<div class='alert alert-success'>✅ Created!</div>"; ?>
    <?php if(isset($_GET['saved'])) echo "<div class='alert alert-success'>✅ Saved!</div>"; ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>Sub-Admin</th><th>Properties</th><th>Users</th><th>Packages</th><th>Subscriptions</th><th>Settings</th><th>Referrals</th><th>Accounting</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(count($users)>0) {
                foreach($users as $u): 
                    $perms = getUserPermissions($u['id'], $pdo);
            ?>
                <form method="POST"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?><br><small><?= $u['email'] ?></small></td>
                        <?php $modules = ['properties','users','packages','subscriptions','settings','referrals','accounting']; 
                        foreach($modules as $mod): 
                            $view = $perms[$mod]['view'] ?? false;
                            $edit = $perms[$mod]['edit'] ?? false;
                        ?>
                        <td>
                            <div><input class="form-check-input" type="checkbox" name="view_<?= $mod ?>" <?= $view?'checked':'' ?>> V</div>
                            <div><input class="form-check-input" type="checkbox" name="edit_<?= $mod ?>" <?= $edit?'checked':'' ?>> E</div>
                        </td>
                        <?php endforeach; ?>
                        <td><button type="submit" name="save_permissions" class="btn btn-sm btn-primary">Save</button></td>
                    </tr>
                </form>
            <?php endforeach; } else { echo "<tr><td colspan='9' class='text-center'>No Sub-Admins.</td></tr>"; } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
