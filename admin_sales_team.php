<?php
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}
include 'header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$message = '';

// ---- Handle Add/Edit/Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'];
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        // check user not already in sales_team
        $check = $pdo->prepare("SELECT id FROM sales_team WHERE user_id = ?");
        $check->execute([$user_id]);
        if ($check->fetch()) {
            $message = "User is already in sales team.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO sales_team (user_id, role, manager_id) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $role, $manager_id]);
            $message = "Member added.";
            header("Location: admin_sales_team.php?msg=added");
            exit;
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $role = $_POST['role'];
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $stmt = $pdo->prepare("UPDATE sales_team SET role = ?, manager_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$role, $manager_id, $id]);
        $message = "Member updated.";
        header("Location: admin_sales_team.php?msg=updated");
        exit;
    } elseif (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        // ensure not delete self
        $stmt = $pdo->prepare("DELETE FROM sales_team WHERE id = ? AND user_id != ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        header("Location: admin_sales_team.php?msg=deleted");
        exit;
    }
}

// ---- Fetch all sales team with user details ----
$team = $pdo->query("
    SELECT st.*, u.name, u.email, u.phone,
           (SELECT u2.name FROM sales_team st2 JOIN users u2 ON st2.user_id = u2.id WHERE st2.id = st.manager_id) as manager_name
    FROM sales_team st
    JOIN users u ON st.user_id = u.id
    ORDER BY st.id
")->fetchAll();

// ---- Fetch users not in sales_team for dropdown ----
$non_sales = $pdo->query("SELECT id, name, email FROM users WHERE id NOT IN (SELECT user_id FROM sales_team) AND status = 'active'")->fetchAll();

// ---- For edit, fetch current record ----
$edit_record = null;
if ($action == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM sales_team WHERE id = ?");
    $stmt->execute([$id]);
    $edit_record = $stmt->fetch();
}
?>
<div class="container-fluid mt-4">
    <h1>👥 Sales Team Management</h1>
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?= $_GET['msg'] == 'added' ? '✅ Member added' : ($_GET['msg'] == 'updated' ? '✅ Member updated' : '✅ Member deleted') ?></div>
    <?php endif; ?>
    <?php if ($message) echo "<div class='alert alert-info'>$message</div>"; ?>

    <!-- Add/Edit Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <?= $edit_record ? 'Edit Member' : 'Add New Member' ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($edit_record): ?>
                    <input type="hidden" name="id" value="<?= $edit_record['id'] ?>">
                    <input type="hidden" name="edit" value="1">
                <?php else: ?>
                    <input type="hidden" name="add" value="1">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">User</label>
                        <?php if ($edit_record): ?>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($edit_record['user_id']) ?>" disabled>
                            <input type="hidden" name="user_id" value="<?= $edit_record['user_id'] ?>">
                        <?php else: ?>
                            <select name="user_id" class="form-control" required>
                                <option value="">Select User</option>
                                <?php foreach ($non_sales as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name'] . ' (' . $u['email'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="RM" <?= ($edit_record && $edit_record['role']=='RM')?'selected':'' ?>>RM - Relationship Manager</option>
                            <option value="TL" <?= ($edit_record && $edit_record['role']=='TL')?'selected':'' ?>>TL - Team Lead</option>
                            <option value="BM" <?= ($edit_record && $edit_record['role']=='BM')?'selected':'' ?>>BM - Branch Manager</option>
                            <option value="ZM" <?= ($edit_record && $edit_record['role']=='ZM')?'selected':'' ?>>ZM - Zone Manager</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Manager (optional)</label>
                        <select name="manager_id" class="form-control">
                            <option value="">None</option>
                            <?php
                            $managers = $pdo->query("SELECT st.id, u.name FROM sales_team st JOIN users u ON st.user_id = u.id WHERE st.id != " . ($edit_record ? $edit_record['id'] : '0'))->fetchAll();
                            foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($edit_record && $edit_record['manager_id'] == $m['id'])?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?= $edit_record ? 'Update' : 'Add' ?></button>
                        <?php if ($edit_record): ?>
                            <a href="admin_sales_team.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- List Sales Team -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Manager</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($team as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= $row['role'] ?></td>
                    <td><?= $row['manager_name'] ? htmlspecialchars($row['manager_name']) : '-' ?></td>
                    <td>
                        <a href="admin_sales_team.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="admin_sales_team.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
