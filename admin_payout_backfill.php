<?php
// ============================================================
// 🚀 Admin – Payout Backfill (Generate & Rollback Historical Payouts)
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
// 🔥 1. GENERATE BACKFILL PAYOUTS
// ============================================================
if (isset($_POST['generate_backfill'])) {
    $batch_id_generated = 'BACKFILL_' . date('Ymd_His');
    
    // Fetch all active subscriptions
    $stmt = $pdo->query("
        SELECT s.user_id, s.amount, s.package_id, u.name as user_name 
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.status = 'active' AND s.end_date >= CURRENT_DATE 
        ORDER BY s.id ASC
    ");
    $active_subs = $stmt->fetchAll();
    
    $count = 0;
    try {
        $pdo->beginTransaction();
        foreach ($active_subs as $sub) {
            if ($sub['amount'] > 0) {
                // Call the updated distributeIncome function with the batch_id
                if (function_exists('distributeIncome')) {
                    distributeIncome($pdo, $sub['user_id'], $sub['amount'], $sub['package_id'], $batch_id_generated);
                }
                $count++;
            }
        }
        $pdo->commit();
        $message = "✅ Successfully generated payouts for <b>$count</b> active subscriptions.<br>Batch ID: <b>$batch_id_generated</b>";
        $message_type = "success";
        $_SESSION['last_backfill_batch'] = $batch_id_generated;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error generating payouts: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 2. ROLLBACK BACKFILL PAYOUTS
// ============================================================
if (isset($_POST['rollback_backfill'])) {
    $batch_to_rollback = $_POST['batch_id_to_rollback'] ?? $_SESSION['last_backfill_batch'] ?? '';
    
    if (!empty($batch_to_rollback)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Get all earnings for this batch to adjust wallets
            $earn_stmt = $pdo->prepare("SELECT user_id, SUM(amount) as total_amt FROM user_earnings WHERE batch_id = ? GROUP BY user_id");
            $earn_stmt->execute([$batch_to_rollback]);
            $earnings = $earn_stmt->fetchAll();
            
            // 2. Subtract from user wallets
            foreach ($earnings as $e) {
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$e['total_amt'], $e['user_id']]);
            }
            
            // 3. Delete from user_earnings
            $del1 = $pdo->prepare("DELETE FROM user_earnings WHERE batch_id = ?");
            $del1->execute([$batch_to_rollback]);
            $deleted_earnings = $del1->rowCount();
            
            // 4. Delete from wallet_transactions
            $del2 = $pdo->prepare("DELETE FROM wallet_transactions WHERE batch_id = ?");
            $del2->execute([$batch_to_rollback]);
            $deleted_wallet = $del2->rowCount();
            
            $pdo->commit();
            $message = "✅ Rollback successful! Deleted <b>$deleted_earnings</b> earnings and <b>$deleted_wallet</b> wallet transactions for batch: <b>$batch_to_rollback</b>";
            $message_type = "success";
            unset($_SESSION['last_backfill_batch']);
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Rollback Error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "❌ No batch ID provided to rollback.";
        $message_type = "danger";
    }
}

// ---- Fetch Batches for Display ----
$batches = $pdo->query("
    SELECT batch_id, COUNT(*) as total_entries, SUM(amount) as total_amount, MIN(created_at) as generated_at 
    FROM user_earnings 
    WHERE batch_id IS NOT NULL 
    GROUP BY batch_id 
    ORDER BY batch_id DESC
")->fetchAll();

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-dark fw-bold"><i class="fas fa-sync-alt me-2"></i> Payout Backfill Tool</h3>
        <a href="users.php" class="btn btn-outline-secondary rounded-pill px-4">⬅ Back to Users</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Warning Alert -->
    <div class="alert alert-warning border-warning">
        <h6 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Important Warning</h6>
        <p class="mb-1 small">This tool will generate payouts for <b>ALL Active Subscriptions</b> in the system. Please read carefully:</p>
        <ul class="small mb-0">
            <li>Payouts are calculated based on the <b>current team structure</b> and package percentages. If the team structure changed after the subscription was purchased, the payout might differ from actual history.</li>
            <li>Running this will <b>credit the wallet balances</b> of the uplines.</li>
            <li>If you find any errors, use the <b>Rollback</b> button to delete the generated payouts and restore wallet balances to their previous state.</li>
        </ul>
    </div>

    <div class="row">
        <!-- Generate Section -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i> Generate Historical Payouts</h5>
                </div>
                <div class="card-body text-center py-4">
                    <p class="text-muted">Click the button below to generate payouts for all active subscriptions.</p>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to generate payouts for all active subscriptions? This will credit wallets.');">
                        <button type="submit" name="generate_backfill" class="btn btn-primary btn-lg rounded-pill px-5">
                            <i class="fas fa-cogs me-2"></i> Generate Payouts Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rollback Section -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-danger text-white rounded-top-4">
                    <h5 class="mb-0"><i class="fas fa-undo-alt me-2"></i> Rollback Generated Payouts</h5>
                </div>
                <div class="card-body text-center py-4">
                    <?php if (empty($batches)): ?>
                        <!-- 🔥 Empty State Message -->
                        <div class="alert alert-secondary py-4 mb-0">
                            <i class="fas fa-info-circle fa-2x mb-2 text-muted"></i>
                            <p class="mb-0 fw-bold">No batches generated yet.</p>
                            <small class="text-muted">Please click "Generate Payouts Now" on the left side first.</small>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Select a Batch ID to rollback. This will delete the earnings and subtract from wallets.</p>
                        <form method="POST" onsubmit="return confirm('WARNING: This will DELETE the selected batch payouts and DEDUCT from user wallets. Are you sure?');">
                            <div class="mb-3">
                                <select name="batch_id_to_rollback" class="form-select" required>
                                    <option value="">-- Select Batch ID --</option>
                                    <?php foreach ($batches as $b): ?>
                                        <option value="<?= htmlspecialchars($b['batch_id']) ?>">
                                            <?= htmlspecialchars($b['batch_id']) ?> (₹<?= number_format($b['total_amount'], 2) ?> - <?= date('d M Y H:i', strtotime($b['generated_at'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="rollback_backfill" class="btn btn-danger btn-lg rounded-pill px-5">
                                <i class="fas fa-trash-alt me-2"></i> Rollback Batch
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Generated Batches Table -->
    <div class="card shadow-sm border-0 rounded-4 mt-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-history me-2"></i> Backfill History</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Batch ID</th>
                            <th>Total Entries</th>
                            <th>Total Amount Paid</th>
                            <th>Generated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No backfill batches found yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['batch_id']) ?></strong></td>
                                <td><?= $b['total_entries'] ?> entries</td>
                                <td class="text-success fw-bold">₹ <?= number_format($b['total_amount'], 2) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($b['generated_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Rollback this batch? Wallets will be adjusted.');">
                                        <input type="hidden" name="batch_id_to_rollback" value="<?= htmlspecialchars($b['batch_id']) ?>">
                                        <button type="submit" name="rollback_backfill" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-undo"></i> Rollback
                                        </button>
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

<?php include 'footer.php'; ?>
