<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php';

$message = '';
$error = '';

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Personal update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_personal'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $pdo->prepare("UPDATE users SET name = ?, phone = ?, city = ?, state = ? WHERE id = ?")->execute([$name, $phone, $city, $state, $user_id]);
    $message = "✅ Personal details updated!";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Bank update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_bank'])) {
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $ifsc = trim($_POST['ifsc']);
    $branch = trim($_POST['branch']);
    $pdo->prepare("UPDATE users SET bank_name = ?, account_number = ?, ifsc = ?, branch = ? WHERE id = ?")->execute([$bank_name, $account_number, $ifsc, $branch, $user_id]);
    $message = "✅ Bank details updated!";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// KYC upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_kyc'])) {
    $doc_type = $_POST['doc_type'];
    if(isset($_FILES['kyc_file']) && $_FILES['kyc_file']['error'] == 0) {
        $upload_dir = 'uploads/kyc/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['kyc_file']['name'], PATHINFO_EXTENSION);
        $filename = 'kyc_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['kyc_file']['tmp_name'], $upload_dir . $filename);
        $pdo->prepare("INSERT INTO kyc_documents (user_id, doc_type, file_path, status) VALUES (?, ?, ?, 'pending')")->execute([$user_id, $doc_type, $upload_dir . $filename]);
        $message = "✅ KYC document uploaded successfully!";
    } else {
        $error = "❌ Please select a file to upload.";
    }
}

// Fetch KYC docs
$kyc_docs = $pdo->prepare("SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$kyc_docs->execute([$user_id]);
$kyc_docs = $kyc_docs->fetchAll();
?>
<style>
    .profile-section { background: white; border-radius: 24px; padding: 25px; margin-bottom: 25px; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04); }
    .profile-section h5 { font-weight: 700; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
</style>
<div class="container-fluid">
    <h4 class="mb-4"><i class="fas fa-user-circle me-2"></i>My Profile</h4>
    <?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <!-- Personal Details -->
    <div class="profile-section">
        <h5><i class="fas fa-user me-2"></i>Personal Details</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Email (Read Only)</label><input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">State</label><input type="text" name="state" class="form-control" value="<?= htmlspecialchars($user['state'] ?? '') ?>"></div>
            </div>
            <button type="submit" name="update_personal" class="btn btn-primary mt-3">Update Personal Details</button>
        </form>
    </div>

    <!-- Bank Details -->
    <div class="profile-section">
        <h5><i class="fas fa-university me-2"></i>Bank Details</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($user['bank_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Account Number</label><input type="text" name="account_number" class="form-control" value="<?= htmlspecialchars($user['account_number'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">IFSC Code</label><input type="text" name="ifsc" class="form-control" value="<?= htmlspecialchars($user['ifsc'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Branch</label><input type="text" name="branch" class="form-control" value="<?= htmlspecialchars($user['branch'] ?? '') ?>"></div>
            </div>
            <button type="submit" name="update_bank" class="btn btn-primary mt-3">Update Bank Details</button>
        </form>
    </div>

    <!-- KYC Upload -->
    <div class="profile-section">
        <h5><i class="fas fa-id-card me-2"></i>KYC Upload</h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Document Type</label>
                    <select name="doc_type" class="form-control" required>
                        <option value="aadhar">Aadhar Card</option>
                        <option value="pan">PAN Card</option>
                        <option value="bank_proof">Bank Proof</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Choose File</label><input type="file" name="kyc_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                <div class="col-md-2 d-flex align-items-end"><button type="submit" name="upload_kyc" class="btn btn-success w-100">Upload</button></div>
            </div>
        </form>
        <div class="mt-4">
            <h6>Uploaded Documents</h6>
            <?php if(count($kyc_docs) > 0): ?>
                <table class="table table-sm">
                    <thead><tr><th>Type</th><th>File</th><th>Status</th><th>Uploaded</th></tr></thead>
                    <tbody>
                    <?php foreach($kyc_docs as $doc): 
                        $status_class = $doc['status'] == 'approved' ? 'success' : ($doc['status'] == 'pending' ? 'warning' : 'danger');
                    ?>
                        <tr>
                            <td><?= ucfirst($doc['doc_type']) ?></td>
                            <td><a href="<?= $doc['file_path'] ?>" target="_blank">View</a></td>
                            <td><span class="badge bg-<?= $status_class ?>"><?= ucfirst($doc['status']) ?></span></td>
                            <td><?= date('d M Y', strtotime($doc['uploaded_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No documents uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
