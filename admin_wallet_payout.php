<?php
// ============================================================
// 💸 Admin – Wallet Payout (Pay user's wallet balance with UTR)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}
if (!hasEditPermission('referrals', $pdo) && !hasEditPermission('accounting', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
}

$message = '';
$message_type = '';

// ============================================================
// 🔥 HANDLE PAYOUT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_wallet'])) {
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $utr_no = trim($_POST['utr_no'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc = trim($_POST['ifsc'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($user_id <= 0 || $amount <= 0 || empty($utr_no)) {
        $message = "❌ User, Amount, और UTR number जरूरी हैं।";
        $message_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // Check current wallet balance
            $bal_stmt = $pdo->prepare("SELECT wallet_balance, name FROM users WHERE id = ?");
            $bal_stmt->execute([$user_id]);
            $user_data = $bal_stmt->fetch();

            if (!$user_data) {
                throw new Exception("User not found.");
            }

            if ($amount > $user_data['wallet_balance']) {
                throw new Exception("Amount ₹" . number_format($amount, 2) . " is greater than wallet balance ₹" . number_format($user_data['wallet_balance'], 2));
            }

            // 1. Insert payout record
            $ins = $pdo->prepare("
                INSERT INTO wallet_payouts (user_id, amount, utr_no, bank_name, account_number, ifsc, payment_date, notes, paid_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$user_id, $amount, $utr_no, $bank_name, $account_number, $ifsc, $payment_date, $notes, $_SESSION['user_id']]);

            // 2. Debit wallet
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                ->execute([$amount, $user_id]);

            // 3. Wallet transaction entry
            $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'debit', ?, ?)")
                ->execute([$user_id, $amount, "Wallet Payout | UTR: $utr_no", null]);

            // 4. Accounting expense entry
            $uname = $user_data['name'];
            $description = "Wallet Payout to $uname (ID: $user_id) - ₹" . indianCurrencyFormat($amount) . " | UTR: $utr_no";
            addAccountEntry($pdo, 'expense', $amount, $description, 'Wallet Payout');

            $pdo->commit();
            $message = "✅ Wallet payout of ₹" . number_format($amount, 2) . " to <b>" . htmlspecialchars($uname) . "</b> recorded successfully! (UTR: $utr_no)";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ============================================================
// 🔥 FETCH DATA
// ============================================================
$search = trim($_GET['search'] ?? '');
$where = "WHERE u.wallet_balance > 0";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.name ILIKE ? OR u.email ILIKE ? OR u.id::text = ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = $search;
}

$users_with_balance = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone, u.wallet_balance,
           u.bank_name, u.account_number, u.ifsc
    FROM users u
    $where
    ORDER BY u.wallet_balance DESC
");
$users_with_balance->execute($params);
$users_with_balance = $users_with_balance->fetchAll();

$payout_history = $pdo->query("
    SELECT wp.*, u.name as user_name, u.email as user_email
    FROM wallet_payouts wp
    JOIN users u ON wp.user_id = u.id
    ORDER BY wp.id DESC
    LIMIT 50
")->fetchAll();

$total_payouts = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_payouts")->fetchColumn();
$total_users = count($users_with_balance);
$total_balance = $pdo->query("SELECT COALESCE(SUM(wallet_balance), 0) FROM users WHERE wallet_balance > 0")->fetchColumn();

include 'header.php';
?>

<style>
    .wp-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        margin-bottom: 25px;
        overflow: hidden;
    }
    .wp-header {
        background: linear-gradient(135deg, #065f46, #10b981);
        color: #fff;
        padding: 18px 24px;
    }
    .wp-header h4 { margin: 0; font-weight: 700; font-size: 1.15rem; }
    .wp-header p { margin: 4px 0 0; opacity: 0.9; font-size: 0.82rem; }
    .wp-body { padding: 22px; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }
    .summary-card {
        background: #fff;
        border-radius: 14px;
        padding: 16px 18px;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .summary-card .sc-icon {
        width: 42px; height: 42px;
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .summary-card .sc-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .summary-card .sc-value {
        font-size: 1.25rem;
        font-weight: 800;
        margin-top: 2px;
        color: #0f172a;
    }
    .sc-users { border-color: #93b5e8; background: #eff6ff; }
    .sc-users .sc-icon { background: #dbeafe; color: #1e40af; }
    .sc-balance { border-color: #fcd34d; background: #fffbeb; }
    .sc-balance .sc-icon { background: #fef3c7; color: #b45309; }
    .sc-paid { border-color: #6ee7b7; background: #f0fdf4; }
    .sc-paid .sc-icon { background: #d1fae5; color: #065f46; }
    .sc-paid .sc-value { color: #059669; }

    .table-users { width: 100%; font-size: 0.85rem; border-collapse: collapse; }
    .table-users th {
        background: #1e293b; color: #fff;
        font-size: 0.68rem; text-transform: uppercase;
        padding: 11px 10px; letter-spacing: 0.5px;
        text-align: left; font-weight: 700;
    }
    .table-users td {
        padding: 12px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table-users tr:hover { background: #f8fafc; }

    .badge-balance {
        background: #fef3c7; color: #b45309;
        padding: 4px 12px; border-radius: 20px;
        font-weight: 800; font-size: 0.9rem;
        display: inline-block;
    }
    .btn-pay {
        background: linear-gradient(135deg, #059669, #10b981);
        color: #fff; border: none;
        padding: 6px 14px; border-radius: 8px;
        font-weight: 700; font-size: 0.78rem;
        cursor: pointer; transition: all 0.2s;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-pay:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(16,185,129,0.35); color: #fff; }

    .history-table { width: 100%; font-size: 0.82rem; border-collapse: collapse; }
    .history-table th {
        background: #475569; color: #fff;
        font-size: 0.65rem; text-transform: uppercase;
        padding: 10px 10px; letter-spacing: 0.5px;
        text-align: left; font-weight: 700;
    }
    .history-table td {
        padding: 10px; border-bottom: 1px solid #f1f5f9;
    }
    .utr-code {
        font-family: 'Courier New', monospace;
        background: #eff6ff; padding: 2px 8px;
        border-radius: 5px; font-size: 0.72rem;
        border: 1px solid #bfdbfe;
        color: #1e40af; font-weight: 700;
    }
    .search-bar {
        background: #f8fafc; padding: 12px 16px;
        border-radius: 12px; margin-bottom: 16px;
        display: flex; gap: 10px; flex-wrap: wrap;
        border: 1px solid #e2e8f0;
    }
    .search-bar input {
        flex: 1; min-width: 200px;
        padding: 8px 14px; border-radius: 30px;
        border: 1px solid #e2e8f0; font-size: 0.85rem;
    }
    .search-bar button {
        padding: 8px 20px; border-radius: 30px;
        background: #1e3a8a; color: #fff; border: none;
        font-weight: 700; cursor: pointer;
    }
    @media (max-width: 768px) {
        .table-users, .history-table { font-size: 0.75rem; }
        .table-users th, .table-users td,
        .history-table th, .history-table td { padding: 8px 6px; }
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold"><i class="fas fa-money-bill-wave me-2 text-success"></i> Wallet Payout Manager</h3>
        <a href="users.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-users me-1"></i> Back to Users
        </a>
    </div>

    <?= $message ?>

    <!-- Summary -->
    <div class="summary-grid">
        <div class="summary-card sc-users">
            <div class="sc-icon"><i class="fas fa-users"></i></div>
            <div>
                <div class="sc-label">Users With Balance</div>
                <div class="sc-value"><?= $total_users ?></div>
            </div>
        </div>
        <div class="summary-card sc-balance">
            <div class="sc-icon"><i class="fas fa-wallet"></i></div>
            <div>
                <div class="sc-label">Total Wallet Balance</div>
                <div class="sc-value">₹ <?= indianCurrencyFormat($total_balance) ?></div>
            </div>
        </div>
        <div class="summary-card sc-paid">
            <div class="sc-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="sc-label">Total Paid Out</div>
                <div class="sc-value">₹ <?= indianCurrencyFormat($total_payouts) ?></div>
            </div>
        </div>
    </div>

    <!-- Pending Wallet Balances -->
    <div class="wp-card">
        <div class="wp-header">
            <h4><i class="fas fa-hourglass-half me-2"></i> Users Pending Wallet Payout (<?= count($users_with_balance) ?>)</h4>
            <p>जिन users का wallet balance जीरो से ज्यादा है, उन्हें यहाँ से pay करें</p>
        </div>
        <div class="wp-body">
            <form method="GET" class="search-bar">
                <input type="text" name="search" placeholder="🔍 Search user name, email or ID..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if (!empty($search)): ?>
                    <a href="admin_wallet_payout.php" class="btn btn-secondary" style="border-radius:30px; padding:8px 20px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <?php if (empty($users_with_balance)): ?>
                <div class="alert alert-info text-center mb-0">
                    <i class="fas fa-check-circle me-1"></i>
                    <?= empty($search) ? 'सभी users के wallets clear हैं। 🎉' : 'कोई user नहीं मिला।' ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-users">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th class="text-end">Wallet Balance</th>
                                <th>Bank Details</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users_with_balance as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($u['name']) ?></strong>
                                    <div style="font-size:0.72rem;color:#64748b;">#<?= $u['id'] ?> — <?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                                <td class="text-end">
                                    <span class="badge-balance">₹ <?= indianCurrencyFormat($u['wallet_balance']) ?></span>
                                </td>
                                <td style="font-size:0.75rem;">
                                    <?php if (!empty($u['bank_name'])): ?>
                                        <strong><?= htmlspecialchars($u['bank_name']) ?></strong><br>
                                        <span style="color:#64748b;">A/c: <?= htmlspecialchars($u['account_number'] ?? '—') ?></span><br>
                                        <span style="color:#64748b;">IFSC: <?= htmlspecialchars($u['ifsc'] ?? '—') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No bank details</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn-pay" onclick="openPayModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', <?= $u['wallet_balance'] ?>, '<?= htmlspecialchars(addslashes($u['bank_name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($u['account_number'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($u['ifsc'] ?? '')) ?>')">
                                        <i class="fas fa-paper-plane"></i> Pay
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payout History -->
    <div class="wp-card">
        <div class="wp-header" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
            <h4><i class="fas fa-history me-2"></i> Wallet Payout History (<?= count($payout_history) ?>)</h4>
            <p>अभी तक किए गए सभी wallet payouts की history</p>
        </div>
        <div class="wp-body">
            <?php if (empty($payout_history)): ?>
                <div class="alert alert-info text-center mb-0">अभी तक कोई wallet payout नहीं हुआ।</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th class="text-end">Amount</th>
                                <th>UTR No.</th>
                                <th>Bank</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payout_history as $p): ?>
                            <tr>
                                <td style="font-size:0.78rem;"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['user_name']) ?></strong>
                                    <div style="font-size:0.7rem;color:#64748b;">#<?= $p['user_id'] ?></div>
                                </td>
                                <td class="text-end text-success fw-bold">₹ <?= indianCurrencyFormat($p['amount']) ?></td>
                                <td><span class="utr-code"><?= htmlspecialchars($p['utr_no']) ?></span></td>
                                <td style="font-size:0.75rem;"><?= htmlspecialchars($p['bank_name'] ?: '—') ?></td>
                                <td style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($p['notes'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PAY MODAL -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #065f46, #10b981); color: #fff;">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i> Pay Wallet Balance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return confirm('Confirm this wallet payout?');">
                <div class="modal-body">
                    <input type="hidden" name="pay_wallet" value="1">
                    <input type="hidden" name="user_id" id="payUserId">

                    <div class="alert alert-success py-2 small mb-3">
                        <strong>Beneficiary:</strong> <span id="payUserName"></span> &nbsp;|&nbsp; 
                        <strong>Available:</strong> <span id="payUserBalance"></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold small">Amount to Pay *</label>
                            <input type="number" step="0.01" name="amount" id="payAmount" class="form-control" required min="1">
                            <small class="text-muted">Maximum: <span id="payMax"></span></small>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">Payment Date *</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">UTR / Transaction No. *</label>
                            <input type="text" name="utr_no" class="form-control" placeholder="e.g. 4477...8921" required>
                            <small class="text-muted">यह UTR user की Payment History में भी दिखेगा</small>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">Bank Name</label>
                            <input type="text" name="bank_name" id="payBankName" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">Account Number</label>
                            <input type="text" name="account_number" id="payAccNo" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">IFSC Code</label>
                            <input type="text" name="ifsc" id="payIfsc" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="fw-bold small">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any additional note..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle me-1"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openPayModal(userId, userName, balance, bankName, accNo, ifsc) {
        document.getElementById('payUserId').value = userId;
        document.getElementById('payUserName').textContent = userName + ' (#' + userId + ')';
        document.getElementById('payUserBalance').textContent = '₹ ' + parseFloat(balance).toFixed(2);
        document.getElementById('payMax').textContent = '₹ ' + parseFloat(balance).toFixed(2);
        document.getElementById('payAmount').value = parseFloat(balance).toFixed(2);
        document.getElementById('payAmount').max = balance;
        document.getElementById('payBankName').value = bankName || '';
        document.getElementById('payAccNo').value = accNo || '';
        document.getElementById('payIfsc').value = ifsc || '';
        new bootstrap.Modal(document.getElementById('payModal')).show();
    }
</script>

<?php include 'footer.php'; ?>
