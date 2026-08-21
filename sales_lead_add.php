<?php
// ============================================================
// sales_lead_add.php – Manual Lead Entry
// ============================================================

require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sales')) {
    header("Location: login.php");
    exit;
}
include 'header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$team_id = null;
if ($role == 'sales') {
    $stmt = $pdo->prepare("SELECT id FROM sales_team WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $team_id = $stmt->fetchColumn();
    if (!$team_id) {
        echo "<div class='alert alert-danger'>You are not assigned to any sales team.</div>";
        include 'footer.php';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $source = $_POST['source'] ?? 'Manual';
    $assigned_to = ($role == 'admin') ? $_POST['assigned_to'] : $team_id;

    $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, address, city, state, lead_source, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email, $address, $city, $state, $source, $assigned_to, $_SESSION['user_id']]);
    echo "<div class='alert alert-success'>✅ Lead added!</div>";
}
?>
<div class="container-fluid mt-4">
    <h1>➕ Add Lead Manually</h1>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6"><label>Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="col-md-6"><label>Phone</label><input type="text" name="phone" class="form-control" required></div>
            <div class="col-md-6"><label>Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label>City</label><input type="text" name="city" class="form-control"></div>
            <div class="col-md-6"><label>State</label><input type="text" name="state" class="form-control"></div>
            <div class="col-md-6"><label>Address</label><textarea name="address" class="form-control"></textarea></div>
            <div class="col-md-6"><label>Source</label><input type="text" name="source" class="form-control" placeholder="e.g., Website, Call, Referral"></div>
            <?php if ($role == 'admin'): ?>
                <div class="col-md-6">
                    <label>Assign to (Sales Person)</label>
                    <select name="assigned_to" class="form-control">
                        <?php
                        $sales_users = $pdo->query("SELECT st.id, u.name FROM sales_team st JOIN users u ON st.user_id = u.id")->fetchAll();
                        foreach ($sales_users as $su): ?>
                            <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12"><button type="submit" class="btn btn-primary">Save Lead</button></div>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
