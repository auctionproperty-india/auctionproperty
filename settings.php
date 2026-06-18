<?php 
require_once 'db.php'; 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

// Update Logic
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_contact'])) {
    $new_contact = trim($_POST['default_contact']);
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'default_contact'")->execute([$new_contact]);
    header("Location: settings.php?updated=1");
    exit;
}

include 'header.php'; 
$current_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
?>

<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success">✅ Default Contact Number Updated!</div>
<?php endif; ?>

<div class="card-premium">
    <h4><i class="fas fa-cog me-2"></i>Global Settings</h4>
    <form method="POST">
        <div class="row">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Default Contact Number (Auto-fill in Property Form)</label>
                <input type="text" name="default_contact" class="form-control" value="<?= htmlspecialchars($current_contact) ?>" required>
                <small class="text-muted">यह नंबर हर नई Property में अपने आप आ जाएगा।</small>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" name="update_contact" class="btn btn-primary w-100">Update Default Contact</button>
            </div>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
