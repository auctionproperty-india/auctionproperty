<?php
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

if ($role == 'admin') {
    $sales_users = $pdo->query("SELECT st.id, u.name FROM sales_team st JOIN users u ON st.user_id = u.id")->fetchAll();
}

$message = '';
$count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lead_file'])) {
    $file = $_FILES['lead_file']['tmp_name'];
    $assigned_to = $_POST['assigned_to'] ?? $team_id;
    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE) {
            list($name_col, $phone, $email, $address, $city, $state, $source) = array_pad($data, 7, '');
            if (empty($name_col) || empty($phone)) continue;
            $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, address, city, state, lead_source, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name_col, $phone, $email, $address, $city, $state, $source ?: 'CSV Upload', $assigned_to, $_SESSION['user_id']]);
            $count++;
        }
        fclose($handle);
        $message = "✅ $count leads uploaded successfully!";
    } else {
        $message = "❌ Failed to open CSV file.";
    }
}
?>
<div class="container-fluid mt-4">
    <h1>📥 Upload Leads (CSV only)</h1>
    <p>Upload a CSV file with columns: <strong>Name, Phone, Email, Address, City, State, Source</strong></p>
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Choose CSV File</label>
                <input type="file" name="lead_file" class="form-control" accept=".csv" required>
            </div>
            <?php if ($role == 'admin'): ?>
            <div class="col-md-6">
                <label class="form-label">Assign to (Sales Person)</label>
                <select name="assigned_to" class="form-control">
                    <?php foreach ($sales_users as $su): ?>
                        <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="assigned_to" value="<?= $team_id ?>">
            <?php endif; ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Upload & Import</button>
            </div>
        </div>
    </form>
    <hr>
    <p>You can also manually add a lead:</p>
    <a href="sales_lead_add.php" class="btn btn-success">➕ Add Lead Manually</a>
</div>
<?php include 'footer.php'; ?>
