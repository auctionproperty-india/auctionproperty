<?php
// ============================================================
// 📦 Admin: Manage Packages (Laravel Style + Edit Fixed)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

$message = '';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
    $message = "<div class='alert alert-success'>✅ Package deleted.</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $duration_months = (int)$_POST['duration_months'];
    $validity = trim($_POST['validity'] ?? '');
    $property_search = trim($_POST['property_search'] ?? '');
    $company_support = trim($_POST['company_support'] ?? '');
    $sales_team_support = trim($_POST['sales_team_support'] ?? '');
    $self_refer_incentive = trim($_POST['self_refer_incentive'] ?? '');
    $team_refer_incentive = trim($_POST['team_refer_incentive'] ?? '');
    $property_sale_incentive = trim($_POST['property_sale_incentive'] ?? '');
    $team_sale_incentive = trim($_POST['team_sale_incentive'] ?? '');

    if ($id > 0) {
        $sql = "UPDATE packages SET 
            name = ?, price = ?, discount_price = ?, duration_months = ?,
            validity = ?, property_search = ?, company_support = ?, sales_team_support = ?,
            self_refer_incentive = ?, team_refer_incentive = ?, property_sale_incentive = ?, team_sale_incentive = ?
            WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $price, $discount_price, $duration_months,
            $validity, $property_search, $company_support, $sales_team_support,
            $self_refer_incentive, $team_refer_incentive, $property_sale_incentive, $team_sale_incentive, $id]);
        $message = "<div class='alert alert-success'>✅ Package updated.</div>";
    } else {
        $sql = "INSERT INTO packages (name, price, discount_price, duration_months,
            validity, property_search, company_support, sales_team_support,
            self_refer_incentive, team_refer_incentive, property_sale_incentive, team_sale_incentive)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $price, $discount_price, $duration_months,
            $validity, $property_search, $company_support, $sales_team_support,
            $self_refer_incentive, $team_refer_incentive, $property_sale_incentive, $team_sale_incentive]);
        $message = "<div class='alert alert-success'>✅ Package added.</div>";
    }
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
?>

<style>
    .package-form { background: #f8fafc; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
    .package-table th { background: #f1f5f9; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; color: #475569; }
    .package-table td { vertical-align: middle; font-size: 0.9rem; }
    .btn-edit { background: #f59e0b; color: white; border: none; padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; }
    .btn-edit:hover { background: #d97706; color: white; }
    .btn-delete { background: #ef4444; color: white; border: none; padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; }
    .btn-delete:hover { background: #dc2626; color: white; }
    .badge-feature { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; }
</style>

<div class="card-premium">
    <h4><i class="fas fa-boxes me-2"></i>Manage Packages</h4>
    <?= $message ?>

    <!-- Add/Edit Form -->
    <div class="package-form" id="package-form">
        <h5 id="form-title"><i class="fas fa-plus-circle me-2"></i>Add New Package</h5>
        <form method="POST" id="package-form-element">
            <input type="hidden" name="id" id="package-id" value="0">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Package Name</label>
                    <input type="text" name="name" id="pkg-name" class="form-control" required placeholder="e.g. Silver">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Price (₹)</label>
                    <input type="number" step="0.01" name="price" id="pkg-price" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Discount Price</label>
                    <input type="number" step="0.01" name="discount_price" id="pkg-discount" class="form-control" placeholder="Optional">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Duration (months)</label>
                    <input type="number" name="duration_months" id="pkg-duration" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Validity</label>
                    <input type="text" name="validity" id="pkg-validity" class="form-control" placeholder="e.g. 1 month">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Search</label>
                    <input type="text" name="property_search" id="pkg-property_search" class="form-control" placeholder="e.g. All India">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company Support</label>
                    <input type="text" name="company_support" id="pkg-company_support" class="form-control" placeholder="e.g. 1 month">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Sales Team Support</label>
                    <input type="text" name="sales_team_support" id="pkg-sales_team_support" class="form-control" placeholder="e.g. lifetime">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Self Refer Incentive</label>
                    <input type="text" name="self_refer_incentive" id="pkg-self_refer_incentive" class="form-control" placeholder="e.g. Coin">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Team Refer Incentive</label>
                    <input type="text" name="team_refer_incentive" id="pkg-team_refer_incentive" class="form-control" placeholder="e.g. Coin">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Property Sale Incentive</label>
                    <input type="text" name="property_sale_incentive" id="pkg-property_sale_incentive" class="form-control" placeholder="e.g. 1%">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Team Sale Incentive</label>
                    <input type="text" name="team_sale_incentive" id="pkg-team_sale_incentive" class="form-control" placeholder="e.g. 0">
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Package</button>
                    <button type="reset" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-times me-1"></i>Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Package Table -->
    <div class="table-responsive">
        <table class="table package-table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Discount</th>
                    <th>Duration</th>
                    <th>Validity</th>
                    <th>Property Search</th>
                    <th>Company Support</th>
                    <th>Sales Team</th>
                    <th>Self Refer</th>
                    <th>Team Refer</th>
                    <th>Property Sale</th>
                    <th>Team Sale</th>
                    <th style="min-width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td><?= $pkg['id'] ?></td>
                        <td><strong><?= htmlspecialchars($pkg['name']) ?></strong></td>
                        <td>₹<?= number_format($pkg['price'], 2) ?></td>
                        <td><?= $pkg['discount_price'] ? '₹'.number_format($pkg['discount_price'], 2) : '-' ?></td>
                        <td><?= $pkg['duration_months'] ?> mo</td>
                        <td><?= htmlspecialchars($pkg['validity'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['property_search'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['company_support'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['sales_team_support'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['self_refer_incentive'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['team_refer_incentive'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['property_sale_incentive'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pkg['team_sale_incentive'] ?? '') ?></td>
                        <td>
                            <button class="btn-edit" onclick="editPackage(<?= htmlspecialchars(json_encode($pkg)) ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete=<?= $pkg['id'] ?>" class="btn-delete" onclick="return confirm('Delete this package?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editPackage(data) {
    document.getElementById('package-id').value = data.id;
    document.getElementById('pkg-name').value = data.name;
    document.getElementById('pkg-price').value = data.price;
    document.getElementById('pkg-discount').value = data.discount_price || '';
    document.getElementById('pkg-duration').value = data.duration_months;
    document.getElementById('pkg-validity').value = data.validity || '';
    document.getElementById('pkg-property_search').value = data.property_search || '';
    document.getElementById('pkg-company_support').value = data.company_support || '';
    document.getElementById('pkg-sales_team_support').value = data.sales_team_support || '';
    document.getElementById('pkg-self_refer_incentive').value = data.self_refer_incentive || '';
    document.getElementById('pkg-team_refer_incentive').value = data.team_refer_incentive || '';
    document.getElementById('pkg-property_sale_incentive').value = data.property_sale_incentive || '';
    document.getElementById('pkg-team_sale_incentive').value = data.team_sale_incentive || '';
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Package';
    document.getElementById('package-form').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('package-id').value = 0;
    document.getElementById('pkg-name').value = '';
    document.getElementById('pkg-price').value = '';
    document.getElementById('pkg-discount').value = '';
    document.getElementById('pkg-duration').value = '';
    document.getElementById('pkg-validity').value = '';
    document.getElementById('pkg-property_search').value = '';
    document.getElementById('pkg-company_support').value = '';
    document.getElementById('pkg-sales_team_support').value = '';
    document.getElementById('pkg-self_refer_incentive').value = '';
    document.getElementById('pkg-team_refer_incentive').value = '';
    document.getElementById('pkg-property_sale_incentive').value = '';
    document.getElementById('pkg-team_sale_incentive').value = '';
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Package';
}
</script>

<?php include 'footer.php'; ?>
