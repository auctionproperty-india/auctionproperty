<?php
// ============================================================
// ➕ Sales CRM – Add New Lead
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin');
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $alternate_phone = trim($_POST['alternate_phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $property_type = trim($_POST['property_type'] ?? '');
    $budget_min = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null;
    $budget_max = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null;
    $source = trim($_POST['source'] ?? 'Manual');
    $status = $_POST['status'] ?? 'new';
    $priority = $_POST['priority'] ?? 'medium';
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $assigned_to = $is_admin && !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $user_id;

    if (empty($name) || empty($phone)) {
        $message = "❌ Name and Phone are required!";
        $message_type = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sales_leads 
                (name, email, phone, alternate_phone, city, state, property_type, 
                 budget_min, budget_max, source, status, priority, follow_up_date, 
                 notes, assigned_to, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $email, $phone, $alternate_phone, $city, $state, $property_type,
                $budget_min, $budget_max, $source, $status, $priority, $follow_up_date,
                $notes, $assigned_to, $user_id
            ]);
            $lead_id = $pdo->lastInsertId();

            // Add initial note
            if (!empty($notes)) {
                $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, ?, 'initial', ?)")
                    ->execute([$lead_id, $user_id, $notes]);
            }

            $message = "✅ Lead added successfully! <a href='sales_lead_view.php?id=$lead_id'>View Lead →</a>";
            $message_type = "success";
            $_POST = [];
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

$sales_users = [];
if ($is_admin) {
    $sales_users = $pdo->query("SELECT id, name FROM users WHERE role IN ('sales', 'admin') ORDER BY name")->fetchAll();
}

include 'header.php';
?>

<style>
    .form-card {
        background: #fff;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    .form-card .form-label {
        font-weight: 700;
        font-size: 0.8rem;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }
    .form-card .form-control, .form-card .form-select {
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        padding: 10px 14px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    .form-card .form-control:focus, .form-card .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .section-divider {
        display: flex; align-items: center; gap: 12px;
        margin: 24px 0 16px; padding-bottom: 8px;
        border-bottom: 2px solid #f1f5f9;
    }
    .section-divider i { color: #2563eb; font-size: 1rem; }
    .section-divider span {
        font-weight: 800; color: #0f172a;
        font-size: 0.95rem;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="fas fa-user-plus me-2 text-primary"></i> Add New Lead</h3>
        <a href="sales_leads.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-card">
            <!-- Basic Info -->
            <div class="section-divider"><i class="fas fa-user"></i> <span>Basic Information</span></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone *</label>
                    <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alternate Phone</label>
                    <input type="text" name="alternate_phone" class="form-control" value="<?= htmlspecialchars($_POST['alternate_phone'] ?? '') ?>">
                </div>
            </div>

            <!-- Location -->
            <div class="section-divider"><i class="fas fa-map-marker-alt"></i> <span>Location & Property</span></div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Property Type</label>
                    <select name="property_type" class="form-select">
                        <option value="">Select</option>
                        <?php foreach(['Flat','Plot','Shop','House','Land','Commercial','Office','Villa','Bungalow','Row House'] as $pt): ?>
                            <option value="<?= $pt ?>" <?= (($_POST['property_type'] ?? '')==$pt)?'selected':'' ?>><?= $pt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-select">
                        <?php foreach(['Manual','Website','Referral','Walk-in','Social Media','Cold Call','Event','Other'] as $src): ?>
                            <option value="<?= $src ?>" <?= (($_POST['source'] ?? '')==$src)?'selected':'' ?>><?= $src ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Budget Min (₹)</label>
                    <input type="number" name="budget_min" class="form-control" value="<?= htmlspecialchars($_POST['budget_min'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Budget Max (₹)</label>
                    <input type="number" name="budget_max" class="form-control" value="<?= htmlspecialchars($_POST['budget_max'] ?? '') ?>">
                </div>
            </div>

            <!-- Lead Management -->
            <div class="section-divider"><i class="fas fa-tasks"></i> <span>Lead Management</span></div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="interested">Interested</option>
                        <option value="not_interested">Not Interested</option>
                        <option value="converted">Converted</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Follow-up Date</label>
                    <input type="date" name="follow_up_date" class="form-control" value="<?= htmlspecialchars($_POST['follow_up_date'] ?? '') ?>">
                </div>
                <?php if ($is_admin): ?>
                <div class="col-md-3">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Me (Auto)</option>
                        <?php foreach ($sales_users as $su): ?>
                            <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Notes -->
            <div class="section-divider"><i class="fas fa-comment-alt"></i> <span>Notes</span></div>
            <textarea name="notes" class="form-control" rows="3" placeholder="Initial notes about this lead..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-5 rounded-pill">
                    <i class="fas fa-save me-2"></i> Save Lead
                </button>
                <a href="sales_leads.php" class="btn btn-secondary rounded-pill px-4">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
