<?php
// ============================================================
// 💰 Admin Income Settings – Dynamic Direct & Team Turnover
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Add / Edit Income Setting
    if ($_POST['action'] === 'save_income') {
        $id = (int)($_POST['id'] ?? 0);
        $income_type = $_POST['income_type'];
        $min_turnover = (float)$_POST['min_turnover'];
        $max_turnover = !empty($_POST['max_turnover']) ? (float)$_POST['max_turnover'] : null;
        $percentage = (float)$_POST['percentage'];

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE income_settings SET income_type=?, min_turnover=?, max_turnover=?, percentage=? WHERE id=?");
                $stmt->execute([$income_type, $min_turnover, $max_turnover, $percentage, $id]);
                $message = "✅ Income Setting updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO income_settings (income_type, min_turnover, max_turnover, percentage) VALUES (?, ?, ?, ?)");
                $stmt->execute([$income_type, $min_turnover, $max_turnover, $percentage]);
                $message = "✅ New Income Setting added!";
            }
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // Delete Income Setting
    if ($_POST['action'] === 'delete_income') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM income_settings WHERE id = ?")->execute([$id]);
        $message = "🗑️ Income Setting deleted!";
        $message_type = "success";
    }
}

// ---- FETCH DATA ----
$settings = $pdo->query("SELECT * FROM income_settings ORDER BY income_type ASC, min_turnover ASC")->fetchAll();

include 'header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-dark fw-bold"><i class="fas fa-chart-line me-2"></i> Income & Commission Settings</h3>
        <a href="admin_packages.php" class="btn btn-outline-secondary rounded-pill px-4">⬅ Back to Packages</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add/Edit Form -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0" id="formTitle">Add New Setting</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="incomeForm">
                        <input type="hidden" name="action" value="save_income">
                        <input type="hidden" name="id" id="setting_id" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Income Type</label>
                            <select name="income_type" id="income_type" class="form-select" required>
                                <option value="direct">Direct Referral Income</option>
                                <option value="team_turnover">Team Turnover Income (Slab Wise)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Min Turnover (₹)</label>
                            <input type="number" name="min_turnover" id="min_turnover" class="form-control" value="0" required>
                            <small class="text-muted" style="font-size:0.65rem;">Direct income के लिए 0 रखें</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Max Turnover (₹) - Optional</label>
                            <input type="number" name="max_turnover" id="max_turnover" class="form-control" placeholder="Leave blank for no limit">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Percentage (%)</label>
                            <input type="number" name="percentage" id="percentage" class="form-control" step="0.01" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Save Setting</button>
                        <button type="button" class="btn btn-secondary w-100 rounded-pill mt-2" onclick="resetForm()">Cancel / Reset</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- List Table -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Active Settings</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Type</th>
                                    <th>Min Turnover (₹)</th>
                                    <th>Max Turnover (₹)</th>
                                    <th>Percentage (%)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($settings)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No settings found. Add one!</td></tr>
                                <?php endif; ?>
                                <?php foreach ($settings as $s): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= $s['income_type'] == 'direct' ? 'bg-success' : 'bg-info' ?>">
                                                <?= $s['income_type'] == 'direct' ? 'Direct' : 'Team Turnover' ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($s['min_turnover'], 2) ?></td>
                                        <td><?= $s['max_turnover'] ? number_format($s['max_turnover'], 2) : 'No Limit' ?></td>
                                        <td><strong><?= $s['percentage'] ?>%</strong></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick='editSetting(<?= json_encode($s) ?>)'><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this setting?');">
                                                <input type="hidden" name="action" value="delete_income">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editSetting(data) {
    document.getElementById('setting_id').value = data.id;
    document.getElementById('income_type').value = data.income_type;
    document.getElementById('min_turnover').value = data.min_turnover;
    document.getElementById('max_turnover').value = data.max_turnover || '';
    document.getElementById('percentage').value = data.percentage;
    document.getElementById('formTitle').innerText = 'Edit Setting #' + data.id;
}
function resetForm() {
    document.getElementById('incomeForm').reset();
    document.getElementById('setting_id').value = 0;
    document.getElementById('formTitle').innerText = 'Add New Setting';
}
</script>

<?php include 'footer.php'; ?>
