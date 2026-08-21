<?php
ob_start(); // Prevent "headers already sent" errors

require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}
include 'header.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$message = '';
$error = '';

// ---- Toggle Status (Active/Inactive) ----
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    // Get current status from users table
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current = $stmt->fetchColumn();
    $new_status = ($current == 'active') ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);
    ob_end_clean();
    header("Location: admin_sales_team.php?msg=toggled");
    exit;
}

// ---- Handle Add/Edit/Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $role = $_POST['role'];
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = "❌ Email already registered!";
        } else {
            // Insert user with role 'sales' and status 'active'
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'sales', 'active', NOW())");
            $stmt->execute([$name, $email, $password]);
            $user_id = $pdo->lastInsertId();
            
            // Add to sales_team
            $stmt = $pdo->prepare("INSERT INTO sales_team (user_id, role, manager_id) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $role, $manager_id]);
            
            ob_end_clean();
            header("Location: admin_sales_team.php?msg=added");
            exit;
        }
    } elseif (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $role = $_POST['role'];
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $status = $_POST['status']; // active or inactive
        $new_password = trim($_POST['password'] ?? '');
        
        // Update sales_team
        $stmt = $pdo->prepare("UPDATE sales_team SET role = ?, manager_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$role, $manager_id, $id]);
        
        // Get user_id from sales_team
        $stmt = $pdo->prepare("SELECT user_id FROM sales_team WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();
        
        // Update users table: status and optionally password
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET status = ?, password = ? WHERE id = ?");
            $stmt->execute([$status, $hashed, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
        }
        
        ob_end_clean();
        header("Location: admin_sales_team.php?msg=updated");
        exit;
    } elseif (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        // ensure not delete self
        $stmt = $pdo->prepare("DELETE FROM sales_team WHERE id = ? AND user_id != ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        ob_end_clean();
        header("Location: admin_sales_team.php?msg=deleted");
        exit;
    }
}

// ---- Fetch all sales team with user details ----
$team = $pdo->query("
    SELECT st.*, u.name, u.email, u.phone, u.status as user_status,
           (SELECT u2.name FROM sales_team st2 JOIN users u2 ON st2.user_id = u2.id WHERE st2.id = st.manager_id) as manager_name
    FROM sales_team st
    JOIN users u ON st.user_id = u.id
    ORDER BY st.id
")->fetchAll();

// ---- For edit, fetch current record ----
$edit_record = null;
if ($action == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT st.*, u.name, u.email, u.status as user_status FROM sales_team st JOIN users u ON st.user_id = u.id WHERE st.id = ?");
    $stmt->execute([$id]);
    $edit_record = $stmt->fetch();
}

ob_end_flush();
?>
<div class="container-fluid mt-4">
    <div class="mb-3">
        <a href="admin_permissions.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Sub-Admins</a>
        <a href="sales_leads.php" class="btn btn-success"><i class="fas fa-tasks"></i> View All Leads</a>
    </div>

    <h1>👥 Sales Team Management</h1>
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php 
                if ($_GET['msg'] == 'added') echo '✅ Member added';
                elseif ($_GET['msg'] == 'updated') echo '✅ Member updated';
                elseif ($_GET['msg'] == 'deleted') echo '✅ Member deleted';
                elseif ($_GET['msg'] == 'toggled') echo '✅ Status toggled';
            ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <?= $edit_record ? '✏️ Edit Member' : '➕ Add New Sales Team Member' ?>
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
                    <?php if (!$edit_record): ?>
                        <!-- New User Fields (only for Add) -->
                        <div class="col-md-4">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="Enter email" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="text" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                    <?php else: ?>
                        <!-- Edit Mode: show user info (read-only) -->
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($edit_record['name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($edit_record['email']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="text" name="password" class="form-control" placeholder="Enter new password if changing">
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
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
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= ($edit_record && $edit_record['user_status'] == 'active')?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= ($edit_record && $edit_record['user_status'] == 'inactive')?'selected':'' ?>>Inactive (Blocked)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><?= $edit_record ? 'Update' : 'Create & Add' ?></button>
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
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Manager</th><th>Status</th><th>Actions</th></tr>
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
                        <span class="badge bg-<?= ($row['user_status'] == 'active') ? 'success' : 'danger' ?>">
                            <?= ucfirst($row['user_status']) ?>
                        </span>
                        <a href="admin_sales_team.php?toggle_status=<?= $row['user_id'] ?>" class="btn btn-sm btn-<?= ($row['user_status'] == 'active') ? 'warning' : 'success' ?> ms-1" onclick="return confirm('Toggle status?')">
                            <i class="fas fa-<?= ($row['user_status'] == 'active') ? 'ban' : 'check-circle' ?>"></i>
                        </a>
                    </td>
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
