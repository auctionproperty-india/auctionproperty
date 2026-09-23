<?php
// ============================================================
// 💼 Admin – Payout Manager (View Wallets, Delete Individual Payouts, Recalculate)
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
// 🔥 ACTION 1: DELETE INDIVIDUAL EARNING ENTRY
// ============================================================
if (isset($_POST['delete_earning']) && isset($_POST['earning_id'])) {
    $earning_id = (int)$_POST['earning_id'];
    try {
        $pdo->beginTransaction();
        
        // 1. Get earning details
        $stmt = $pdo->prepare("SELECT user_id, amount FROM user_earnings WHERE id = ?");
        $stmt->execute([$earning_id]);
        $earning = $stmt->fetch();
        
        if ($earning) {
            // 2. Subtract from wallet balance
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                ->execute([$earning['amount'], $earning['user_id']]);
            
            // 3. Delete from user_earnings
            $pdo->prepare("DELETE FROM user_earnings WHERE id = ?")->execute([$earning_id]);
            
            $message = "✅ Earning #$earning_id deleted and ₹" . number_format($earning['amount'], 2) . " deducted from wallet.";
            $message_type = "success";
        } else {
            $message = "❌ Earning not found.";
            $message_type = "danger";
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 ACTION 2: DELETE ALL EARNINGS FOR A SPECIFIC USER
// ============================================================
if (isset($_POST['delete_user_earnings']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $pdo->beginTransaction();
        
        // 1. Get total amount
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM user_earnings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total = (float)$stmt->fetchColumn();
        
        // 2. Subtract from wallet
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
            ->execute([$total, $user_id]);
        
        // 3. Delete all earnings
        $stmt = $pdo->prepare("DELETE FROM user_earnings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $deleted = $stmt->rowCount();
        
        // 4. Also delete wallet transactions for this user
        $pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$user_id]);
        
        $message = "✅ Deleted <b>$deleted</b> earnings for User #$user_id. Deducted ₹" . number_format($total, 2) . " from wallet.";
        $message_type = "success";
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 ACTION 3: RECALCULATE WALLET BALANCE FOR ALL USERS
// ============================================================
if (isset($_POST['recalculate_wallets'])) {
    try {
        $pdo->beginTransaction();
        
        // Get all users with their computed balances from earnings
        $stmt = $pdo->query("
            SELECT u.id, COALESCE(SUM(e.amount), 0) as total_earnings
            FROM users u
            LEFT JOIN user_earnings e ON u.id = e.user_id
            GROUP BY u.id
        ");
        $users_earned = $stmt->fetchAll();
        
        $count = 0;
        foreach ($users_earned as $u) {
            $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")
                ->execute([$u['total_earnings'], $u['id']]);
            $count++;
        }
        
        $pdo->commit();
        $message = "✅ Recalculated wallet balances for <b>$count</b> users based on their actual earnings.";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 ACTION 4: DELETE ALL TODAY'S EARNINGS
// ============================================================
if (isset($_POST['delete_today_earnings'])) {
    $today = date('Y-m-d');
    try {
        $pdo->beginTransaction();
        
        // 1. Get per-user totals
        $stmt = $pdo->prepare("SELECT user_id, SUM(amount) as total FROM user_earnings WHERE DATE(created_at) = ? GROUP BY user_id");
        $stmt->execute([$today]);
        $per_user = $stmt->fetchAll();
        
        // 2. Adjust wallets
        foreach ($per_user as $pu) {
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                ->execute([$pu['total'], $pu['user_id']]);
        }
        
        // 3. Delete today's earnings
        $del1 = $pdo->prepare("DELETE FROM user_earnings WHERE DATE(created_at) = ?");
        $del1->execute([$today]);
        $deleted_earnings = $del1->rowCount();
        
        // 4. Delete today's wallet transactions
        $del2 = $pdo->prepare("DELETE FROM wallet_transactions WHERE DATE(created_at) = ?");
        $del2->execute([$today]);
        $deleted_tx = $del2->rowCount();
        
        $pdo->commit();
        $message = "✅ Deleted <b>$deleted_earnings</b> earnings and <b>$deleted_tx</b> wallet transactions from today ($today). Wallets adjusted.";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 ACTION 5: CLEAR ALL EARNINGS (Full Reset)
// ============================================================
if (isset($_POST['clear_all_earnings'])) {
    try {
        $pdo->beginTransaction();
        
        $pdo->exec("UPDATE users SET wallet_balance = 0");
        $pdo->exec("DELETE FROM user_earnings");
        $pdo->exec("DELETE FROM wallet_transactions");
        
        $pdo->commit();
        $message = "✅ <b>FULL RESET:</b> All earnings, wallet transactions deleted. All wallets reset to ₹0.00.";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$tab = $_GET['tab'] ?? 'wallets';

// Tab 1: User Wallets
$users_wallets = $pdo->query("
    SELECT u.id, u.name, u.email, u.wallet_balance,
           (SELECT COALESCE(SUM(amount), 0) FROM user_earnings WHERE user_id = u.id) as actual_earnings,
           (SELECT COUNT(*) FROM user_earnings WHERE user_id = u.id) as entry_count
    FROM users u
    ORDER BY u.wallet_balance DESC, u.id ASC
")->fetchAll();

// Tab 2: Today's Earnings
$today = date('Y-m-d');
$today_earnings = $pdo->query("
    SELECT e.id, e.user_id, e.from_user_id, e.amount, e.income_type, e.description, e.created_at, e.batch_id,
           u.name as receiver_name, u.email as receiver_email,
           f.name as from_user_name
    FROM user_earnings e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN users f ON e.from_user_id = f.id
    WHERE DATE(e.created_at) = '$today'
    ORDER BY e.id DESC
")->fetchAll();

// Tab 3: All Earnings
$all_earnings = $pdo->query("
    SELECT e.id, e.user_id, e.from_user_id, e.amount, e.income_type, e.description, e.created_at, e.batch_id,
           u.name as receiver_name, u.email as receiver_email,
           f.name as from_user_name
    FROM user_earnings e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN users f ON e.from_user_id = f.id
    ORDER BY e.id DESC
    LIMIT 500
")->fetchAll();

// Tab 4: Batch Summary
$batches = $pdo->query("
    SELECT batch_id, COUNT(*) as total_entries, SUM(amount) as total_amount, 
           MIN(created_at) as generated_at, MAX(created_at) as last_at
    FROM user_earnings 
    WHERE batch_id IS NOT NULL 
    GROUP BY batch_id 
    ORDER BY batch_id DESC
")->fetchAll();

include 'header.php';
?>

<style>
    .tab-nav .nav-link {
        border-radius: 10px 10px 0 0;
        font-weight: 700;
        color: #475569;
    }
    .tab-nav .nav-link.active {
        background: #1e3a8a;
        color: #fff;
    }
    .wallet-badge { background: #10b981; color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; }
    .wallet-badge-warn { background: #ef4444; }
    .table-sm-custom { font-size: 0.82rem; }
    .table-sm-custom th { background: #1e293b; color: #fff; padding: 8px; font-size: 0.72rem; text-transform: uppercase; }
    .table-sm-custom td { padding: 8px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="fw-bold"><i class="fas fa-wallet me-2 text-primary"></i> Payout Manager</h3>
        <div>
            <a href="debug_payout.php" class="btn btn-outline-info rounded-pill px-3">
                <i class="fas fa-bug me-1"></i> Debug Tool
            </a>
            <a href="admin_payout_backfill.php" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-sync me-1"></i> Backfill Tool
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs tab-nav mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $tab == 'wallets' ? 'active' : '' ?>" href="?tab=wallets">
                <i class="fas fa-wallet me-1"></i> User Wallets (<?= count($users_wallets) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab == 'today' ? 'active' : '' ?>" href="?tab=today">
                <i class="fas fa-calendar-day me-1"></i> Today's Earnings (<?= count($today_earnings) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab == 'all' ? 'active' : '' ?>" href="?tab=all">
                <i class="fas fa-list me-1"></i> All Earnings
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab == 'batches' ? 'active' : '' ?>" href="?tab=batches">
                <i class="fas fa-layer-group me-1"></i> Batch Summary (<?= count($batches) ?>)
            </a>
        </li>
    </ul>

    <!-- ========================================== -->
    <!-- TAB 1: WALLETS                             -->
    <!-- ========================================== -->
    <?php if ($tab == 'wallets'): ?>
        
        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i> 
            यहाँ सभी यूजर्स के वॉलेट बैलेंस दिख रहे हैं। अगर बैलेंस गलत लगे तो <b>"Recalculate Wallets"</b> दबाएं - यह सभी का बैलेंस उनके वास्तविक earnings के हिसाब से सेट कर देगा।
        </div>
        
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <form method="POST" onsubmit="return confirm('यह सभी यूजर्स के वॉलेट को उनके actual earnings के हिसाब से रीसेट कर देगा। जारी रखें?');">
                <button type="submit" name="recalculate_wallets" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-calculator me-2"></i> Recalculate All Wallets (Fix Incorrect Balances)
                </button>
            </form>
            
            <form method="POST" onsubmit="return confirm('⚠️ DANGER: यह सब कुछ डिलीट कर देगा! सभी earnings, transactions और wallets ₹0 हो जाएंगे। यह वापस नहीं हो सकता! क्या आप पूरी तरह से सुनिश्चित हैं?');">
                <button type="submit" name="clear_all_earnings" class="btn btn-danger rounded-pill px-4">
                    <i class="fas fa-bomb me-2"></i> Clear ALL Data (Full Reset)
                </button>
            </form>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm-custom table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th class="text-end">Wallet Balance</th>
                                <th class="text-end">Actual Earnings</th>
                                <th class="text-center">Entries</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_wallets as $u): 
                                $match = abs((float)$u['wallet_balance'] - (float)$u['actual_earnings']) < 0.01;
                            ?>
                                <tr>
                                    <td>#<?= $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="text-end">
                                        <span class="wallet-badge <?= $match ? '' : 'wallet-badge-warn' ?>">
                                            ₹ <?= number_format($u['wallet_balance'], 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">₹ <?= number_format($u['actual_earnings'], 2) ?></td>
                                    <td class="text-center"><?= $u['entry_count'] ?></td>
                                    <td class="text-center">
                                        <?php if ($match): ?>
                                            <span class="badge bg-success">✓ Match</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">✗ Mismatch</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($u['entry_count'] > 0): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('User #<?= $u['id'] ?> के सभी earnings डिलीट करने हैं? वॉलेट भी एडजस्ट होगा।');">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" name="delete_user_earnings" class="btn btn-sm btn-outline-danger" title="Delete all earnings for this user">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- ========================================== -->
    <!-- TAB 2: TODAY'S EARNINGS                    -->
    <!-- ========================================== -->
    <?php elseif ($tab == 'today'): ?>
        
        <div class="alert alert-warning py-2 small">
            <i class="fas fa-exclamation-triangle me-1"></i> 
            यहाँ आज के सभी earnings दिख रहे हैं। गलत entry को <b>Delete</b> बटन से डिलीट करें - वॉलेट से अमाउंट अपने आप कट जाएगा।
        </div>
        
        <?php if (count($today_earnings) > 0): ?>
            <form method="POST" onsubmit="return confirm('आज के सभी earnings डिलीट करने हैं? वॉलेट एडजस्ट होगा।');" class="mb-3">
                <button type="submit" name="delete_today_earnings" class="btn btn-danger rounded-pill px-4">
                    <i class="fas fa-trash me-2"></i> Delete ALL Today's Earnings (<?= count($today_earnings) ?>)
                </button>
            </form>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm-custom table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Receiver</th>
                                <th>From User</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th>Description</th>
                                <th>Time</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($today_earnings)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">आज कोई earnings नहीं बनी।</td></tr>
                            <?php endif; ?>
                            <?php foreach ($today_earnings as $e): ?>
                                <tr>
                                    <td>#<?= $e['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($e['receiver_name']) ?></strong>
                                        <div style="font-size:0.7rem;color:#64748b;">#<?= $e['user_id'] ?> — <?= htmlspecialchars($e['receiver_email']) ?></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($e['from_user_name'] ?? 'N/A') ?>
                                        <div style="font-size:0.7rem;color:#64748b;">#<?= $e['from_user_id'] ?></div>
                                    </td>
                                    <td>
                                        <?php if ($e['income_type'] == 'direct'): ?>
                                            <span class="badge bg-success">Direct</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Team</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">₹ <?= number_format($e['amount'], 2) ?></td>
                                    <td style="font-size:0.75rem;color:#475569;max-width:250px;"><?= htmlspecialchars($e['description']) ?></td>
                                    <td style="font-size:0.75rem;"><?= date('h:i A', strtotime($e['created_at'])) ?></td>
                                    <td class="text-center">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('यह entry डिलीट करनी है? वॉलेट से ₹<?= number_format($e['amount'], 2) ?> कट जाएगा।');">
                                            <input type="hidden" name="earning_id" value="<?= $e['id'] ?>">
                                            <button type="submit" name="delete_earning" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
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

    <!-- ========================================== -->
    <!-- TAB 3: ALL EARNINGS                        -->
    <!-- ========================================== -->
    <?php elseif ($tab == 'all'): ?>
        
        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i> 
            हाल की 500 entries। किसी भी गलत entry को डिलीट कर सकते हैं (वॉलेट अपने आप एडजस्ट होगा)।
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm-custom table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Batch</th>
                                <th>Receiver</th>
                                <th>From User</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th>Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_earnings as $e): ?>
                                <tr>
                                    <td>#<?= $e['id'] ?></td>
                                    <td style="font-size:0.7rem;"><?= htmlspecialchars($e['batch_id'] ?? '—') ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($e['receiver_name']) ?></strong>
                                        <div style="font-size:0.7rem;color:#64748b;">#<?= $e['user_id'] ?></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($e['from_user_name'] ?? 'N/A') ?>
                                        <div style="font-size:0.7rem;color:#64748b;">#<?= $e['from_user_id'] ?></div>
                                    </td>
                                    <td>
                                        <?php if ($e['income_type'] == 'direct'): ?>
                                            <span class="badge bg-success">Direct</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Team</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">₹ <?= number_format($e['amount'], 2) ?></td>
                                    <td style="font-size:0.75rem;"><?= date('d M Y, h:i A', strtotime($e['created_at'])) ?></td>
                                    <td class="text-center">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('यह entry डिलीट करनी है? वॉलेट से ₹<?= number_format($e['amount'], 2) ?> कट जाएगा।');">
                                            <input type="hidden" name="earning_id" value="<?= $e['id'] ?>">
                                            <button type="submit" name="delete_earning" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
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

    <!-- ========================================== -->
    <!-- TAB 4: BATCHES                             -->
    <!-- ========================================== -->
    <?php else: ?>
        
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="fas fa-layer-group me-2"></i> All Batches</h5>
                <div class="table-responsive">
                    <table class="table table-sm-custom table-hover">
                        <thead>
                            <tr>
                                <th>Batch ID</th>
                                <th class="text-center">Entries</th>
                                <th class="text-end">Total Amount</th>
                                <th>Generated At</th>
                                <th>Last Entry At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($batches)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">कोई batch नहीं मिला।</td></tr>
                            <?php endif; ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($b['batch_id']) ?></strong></td>
                                    <td class="text-center"><?= $b['total_entries'] ?></td>
                                    <td class="text-end fw-bold text-success">₹ <?= number_format($b['total_amount'], 2) ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($b['generated_at'])) ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($b['last_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
