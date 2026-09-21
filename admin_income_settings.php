<?php
// ============================================================
// 💰 Admin Income Settings – Package-wise + Team + Free User
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
    
    // 1. Update Package-wise Direct Income & Team Eligibility
    if ($_POST['action'] === 'update_package_income') {
        $package_ids = $_POST['package_id'] ?? [];
        $percents = $_POST['direct_percent'] ?? [];
        $team_eligibles = $_POST['team_eligible'] ?? []; // Array of IDs that are checked
        
        try {
            foreach ($package_ids as $index => $pkg_id) {
                $pct = (float)$percents[$index];
                // Check if this package ID is in the checked array
                $is_eligible = in_array($pkg_id, $team_eligibles) ? true : false; 
                
                $pdo->prepare("UPDATE packages SET direct_income_percent = ?, is_team_turnover_eligible = ? WHERE id = ?")
                    ->execute([$pct, $is_eligible, $pkg_id]);
            }
            $message = "✅ Package-wise Income & Eligibility updated!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // 2. Save/Update Team Turnover Slabs
    if ($_POST['action'] === 'save_income') {
        $id = (int)($_POST['id'] ?? 0);
        $income_type = $_POST['income_type'];
        $min_turnover = (float)$_POST['min_turnover'];
        $max_turnover = !empty($_POST['max_turnover']) ? (float)$_POST['max_turnover'] : null;
        $percentage = (float)$_POST['percentage'];

        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE income_settings SET income_type=?, min_turnover=?, max_turnover=?, percentage=? WHERE id=?")
                    ->execute([$income_type, $min_turnover, $max_turnover, $percentage, $id]);
                $message = "✅ Team Turnover Setting updated!";
            } else {
                $pdo->prepare("INSERT INTO income_settings (income_type, min_turnover, max_turnover, percentage) VALUES (?, ?, ?, ?)")
                    ->execute([$income_type, $min_turnover, $max_turnover, $percentage]);
                $message = "✅ New Team Turnover Slab added!";
            }
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // 3. Delete Team Turnover Slab
    if ($_POST['action'] === 'delete_income') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM income_settings WHERE id = ?")->execute([$id]);
        $message = "🗑️ Setting deleted!";
        $message_type = "success";
    }

    // 4. Update Free User Settings
    if ($_POST['action'] === 'update_free_user') {
        $status = isset($_POST['free_user_status']) ? 1 : 0;
        $pct = (float)$_POST['free_user_percent'];
        
        try {
            $pdo->prepare("UPDATE income_settings SET percentage = ?, status = ? WHERE income_type = 'free_user_direct'")
                ->execute([$pct, $status]);
            $message = "✅ Free User Settings updated!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ---- FETCH DATA ----
$packages = $pdo->query("SELECT * FROM packages ORDER BY COALESCE(display_order, 999) ASC, id ASC")->fetchAll();
$team_settings = $pdo->query("SELECT * FROM income_settings WHERE income_type = 'team_turnover' ORDER BY min_turnover ASC")->fetchAll();
$free_user_setting = $pdo->query("SELECT * FROM income_settings WHERE income_type = 'free_user_direct' LIMIT 1")->fetch();

include 'header.php';
?>

<div class="container mt-4 mb-5">
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
        <!-- LEFT SIDE: FORMS -->
        <div class="col-md-5">
            
            <!-- SECTION 1: PACKAGE WISE DIRECT INCOME & ELIGIBILITY -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i> Package Direct Income & Team Eligibility</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Set direct commission % for each package. Also, tick "Team Turnover" if users with this package should get Team Turnover Income.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_package_income">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Package Name</th>
                                        <th style="width:100px;">Direct %</th>
                                        <th style="width:100px;">Team Turnover</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $pkg): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                                <input type="hidden" name="package_id[]" value="<?= $pkg['id'] ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="direct_percent[]" class="form-control form-control-sm" 
                                                       value="<?= htmlspecialchars($pkg['direct_income_percent'] ?? 0) ?>" required>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" name="team_eligible[]" value="<?= $pkg['id'] ?>" 
                                                       class="form-check-input" style="width:1.2rem; height:1.2rem;"
                                                       <?= (!empty($pkg['is_team_turnover_eligible'])) ? 'checked' : '' ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-2">Save Package Settings</button>
                    </form>
                </div>
            </div>

            <!-- SECTION 2: FREE USER SETTINGS -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-warning text-dark rounded-top-4">
                    <h5 class="mb-0"><i class="fas fa-user-tag me-2"></i> Free User (Sponsor) Settings</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">If a <b>Free User</b> refers someone, do they get income? Set it here.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_free_user">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="free_user_status" id="free_user_status" 
                                   <?= (!empty($free_user_setting['status']) && $free_user_setting['status'] == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="free_user_status">Enable Income for Free Users</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Free User Direct Percentage (%)</label>
                            <input type="number" step="0.01" name="free_user_percent" class="form-control" 
                                   value="<?= htmlspecialchars($free_user_setting['percentage'] ?? 0) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 rounded-pill">Save Free User Settings</button>
                    </form>
                </div>
            </div>

            <!-- SECTION 3: TEAM TURNOVER SLABS -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-info text-white rounded-top-4">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i> Add Team Turnover Slab</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_income">
                        <input type="hidden" name="income_type" value="team_turnover">
                        <input type="hidden" name="id" id="setting_id" value="0">
                        
                        <div class="mb-2">
                            <label class="form-label fw-bold small">Min Turnover (₹)</label>
                            <input type="number" name="min_turnover" id="min_turnover" class="form-control" value="0" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small">Max Turnover (₹) - Optional</label>
                            <input type="number" name="max_turnover" id="max_turnover" class="form-control" placeholder="No limit">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold small">Percentage (%)</label>
                            <input type="number" name="percentage" id="percentage" class="form-control" step="0.01" required>
                        </div>
                        <button type="submit" class="btn btn-info w-100 rounded-pill">Save Team Slab</button>
                        <button type="button" class="btn btn-secondary w-100 rounded-pill mt-2" onclick="resetForm()">Cancel</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE: TABLES -->
        <div class="col-md-7">
            
            <!-- PACKAGE INCOME TABLE -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Current Package Settings</h5>
                    <table class="table table-sm table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Package</th>
                                <th>Direct Income %</th>
                                <th>Team Turnover Eligible?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $pkg): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pkg['name']) ?></td>
                                    <td><strong><?= htmlspecialchars($pkg['direct_income_percent'] ?? 0) ?>%</strong></td>
                                    <td>
                                        <?php if (!empty($pkg['is_team_turnover_eligible'])): ?>
                                            <span class="badge bg-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TEAM TURNOVER TABLE -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Team Turnover Slabs</h5>
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Min Turnover (₹)</th>
                                <th>Max Turnover (₹)</th>
                                <th>Percentage (%)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($team_settings)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No team slabs found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($team_settings as $s): ?>
                                <tr>
                                    <td><?= number_format($s['min_turnover'], 2) ?></td>
                                    <td><?= $s['max_turnover'] ? number_format($s['max_turnover'], 2) : 'No Limit' ?></td>
                                    <td><strong><?= $s['percentage'] ?>%</strong></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick='editSetting(<?= json_encode($s) ?>)'><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this slab?');">
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

<script>
function editSetting(data) {
    document.getElementById('setting_id').value = data.id;
    document.getElementById('min_turnover').value = data.min_turnover;
    document.getElementById('max_turnover').value = data.max_turnover || '';
    document.getElementById('percentage').value = data.percentage;
}
function resetForm() {
    document.getElementById('setting_id').value = 0;
    document.getElementById('min_turnover').value = 0;
    document.getElementById('max_turnover').value = '';
    document.getElementById('percentage').value = '';
}
</script>

<?php include 'footer.php'; ?>
