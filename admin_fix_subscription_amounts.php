<?php
// ============================================================
// 🔧 Admin – Fix Subscription Amounts (Bulk Editable)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

// ============================================================
// Handle bulk update
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $amounts = $_POST['amounts'] ?? [];
    $updated = 0;
    
    try {
        $pdo->beginTransaction();
        foreach ($amounts as $sub_id => $amount) {
            $sub_id = (int)$sub_id;
            $amount = (float)$amount;
            $stmt = $pdo->prepare("UPDATE subscriptions SET amount = ? WHERE id = ?");
            $stmt->execute([$amount, $sub_id]);
            if ($stmt->rowCount() > 0) $updated++;
        }
        $pdo->commit();
        $message = "✅ Successfully updated <b>$updated</b> subscription amounts!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// Handle single update
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_update'])) {
    $sub_id = (int)$_POST['sub_id'];
    $amount = (float)$_POST['amount'];
    
    try {
        $stmt = $pdo->prepare("UPDATE subscriptions SET amount = ? WHERE id = ?");
        $stmt->execute([$amount, $sub_id]);
        $message = "✅ Subscription #$sub_id amount updated to ₹" . number_format($amount, 2);
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// Fetch all subscriptions
// ============================================================
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'active');

$where = [];
$params = [];

if ($status_filter !== 'all') {
    $where[] = "s.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where[] = "(u.name ILIKE ? OR u.email ILIKE ? OR u.id::text = ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = $search;
}

$where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

$sql = "
    SELECT s.id as sub_id, s.user_id, s.amount, s.status, s.start_date, s.end_date, 
           s.package_id, s.created_at, s.utr,
           u.name as user_name, u.email as user_email,
           p.name as package_name, p.price as package_price
    FROM subscriptions s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN packages p ON s.package_id = p.id
    $where_clause
    ORDER BY s.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subs = $stmt->fetchAll();

include 'header.php';
?>

<style>
    .fix-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    .fix-table {
        width: 100%;
        font-size: 0.85rem;
    }
    .fix-table th {
        background: #1e293b;
        color: #fff;
        padding: 12px 10px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        text-align: left;
        position: sticky;
        top: 0;
    }
    .fix-table td {
        padding: 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .fix-table tr:hover { background: #f8fafc; }
    .amount-input {
        width: 110px;
        padding: 6px 10px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: right;
        transition: all 0.2s;
    }
    .amount-input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .amount-input.wrong {
        border-color: #ef4444;
        background: #fef2f2;
    }
    .amount-input.correct {
        border-color: #10b981;
        background: #f0fdf4;
    }
    .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-active { background: #dcfce7; color: #166534; }
    .badge-expired { background: #f1f5f9; color: #64748b; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }
    .badge-pkg { background: #2563eb; color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
    .warn-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 0.85rem; color: #92400e; }
    .sticky-footer {
        position: sticky;
        bottom: 0;
        background: #fff;
        padding: 16px 20px;
        border-top: 2px solid #e2e8f0;
        border-radius: 0 0 16px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.06);
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="fw-bold text-dark"><i class="fas fa-tools me-2 text-primary"></i> Fix Subscription Amounts</h3>
        <div>
            <a href="admin_payout_preview.php" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-eye me-1"></i> Payout Preview
            </a>
            <a href="users.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fas fa-users me-1"></i> Users
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="warn-box">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <strong>उद्देश्य:</strong> यहाँ हर subscription का <b>असली amount</b> set करें (जो user ने वास्तव में pay किया था)। यह amount payout calculation में use होगा। अगर amount गलत है, तो उसके हिसाब से payout भी गलत बनेगा।
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 rounded-4 mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small">Search User</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email or user ID" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending Only</option>
                        <option value="expired" <?= $status_filter == 'expired' ? 'selected' : '' ?>>Expired Only</option>
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="admin_fix_subscription_amounts.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <form method="POST">
        <div class="fix-card">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                <table class="fix-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">Sub ID</th>
                            <th>User</th>
                            <th>Package</th>
                            <th class="text-end">Package Price</th>
                            <th class="text-end" style="width:150px;">Actual Amount Paid *</th>
                            <th>Status</th>
                            <th>Dates</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No subscriptions found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($subs as $sub): 
                            $is_correct = abs((float)$sub['amount'] - (float)$sub['package_price']) < 0.01 && (float)$sub['amount'] > 0;
                            $is_zero = (float)$sub['amount'] == 0;
                            $input_class = $is_zero ? 'wrong' : ($is_correct ? 'correct' : '');
                            $badge_class = 'badge-' . $sub['status'];
                        ?>
                            <tr>
                                <td><strong>#<?= $sub['sub_id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($sub['user_name']) ?></strong>
                                    <div style="font-size: 0.7rem; color: #64748b;">#<?= $sub['user_id'] ?> — <?= htmlspecialchars($sub['user_email']) ?></div>
                                </td>
                                <td>
                                    <?php if ($sub['package_name']): ?>
                                        <span class="badge-pkg"><?= htmlspecialchars($sub['package_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">₹ <?= number_format($sub['package_price'] ?? 0, 2) ?></td>
                                <td class="text-end">
                                    <input type="number" 
                                           name="amounts[<?= $sub['sub_id'] ?>]" 
                                           class="amount-input <?= $input_class ?>" 
                                           value="<?= number_format((float)$sub['amount'], 2, '.', '') ?>" 
                                           step="0.01" min="0">
                                </td>
                                <td>
                                    <span class="badge-status <?= $badge_class ?>">
                                        <?= ucfirst($sub['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.75rem;">
                                    <?php if ($sub['start_date']): ?>
                                        <div>Start: <?= date('d M Y', strtotime($sub['start_date'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($sub['end_date']): ?>
                                        <div>End: <?= date('d M Y', strtotime($sub['end_date'])) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="sticky-footer">
                <div>
                    <strong style="font-size: 0.9rem;"><?= count($subs) ?> subscriptions</strong>
                    <span style="color: #64748b; font-size: 0.82rem;"> — Amount को edit करके नीचे Save दबाएं</span>
                </div>
                <button type="submit" name="bulk_update" class="btn btn-success btn-lg rounded-pill px-5" 
                        onclick="return confirm('Are you sure you want to save all updated amounts?');">
                    <i class="fas fa-save me-2"></i> Save All Changes
                </button>
            </div>
        </div>
    </form>

    <!-- Legend -->
    <div class="mt-4 small text-muted">
        <strong>Colors:</strong> 
        <span class="badge" style="background:#f0fdf4; color:#059669; border:1px solid #10b981;">Green</span> = Amount matches package price 
        <span class="badge" style="background:#fef2f2; color:#dc2626; border:1px solid #ef4444;">Red</span> = Amount is 0.00 (needs fixing)
    </div>
</div>

<?php include 'footer.php'; ?>
