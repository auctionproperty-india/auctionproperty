<?php require_once 'db.php'; 
if($_SESSION['role'] != 'admin') { die("Access Denied!"); }
include 'header.php'; 

// Role बदलने का Code
if(isset($_GET['make_admin'])) {
    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$_GET['make_admin']]);
    header("Location: users.php");
}
if(isset($_GET['make_user'])) {
    $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$_GET['make_user']]);
    header("Location: users.php");
}
?>
<h5>👥 Manage Users & Sub-Admins</h5>
<table class="table table-bordered bg-white shadow-sm">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead>
    <tbody>
    <?php 
    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
    foreach($users as $u) { ?>
        <tr>
            <td><?= $u['name'] ?></td>
            <td><?= $u['email'] ?></td>
            <td><span class="badge bg-<?= ($u['role']=='admin')?'danger':'info' ?>"><?= $u['role'] ?></span></td>
            <td>
                <?php if($u['role'] != 'admin'): ?>
                    <a href="?make_admin=<?= $u['id'] ?>" class="btn btn-sm btn-warning">Make Admin</a>
                <?php else: ?>
                    <a href="?make_user=<?= $u['id'] ?>" class="btn btn-sm btn-secondary">Remove Admin</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
<?php include 'footer.php'; ?>
