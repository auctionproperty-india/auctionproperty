<?php
// ============================================================
// 💰 User – Wallet Payment History (UTR + Date)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$user_stmt = $pdo->prepare("SELECT name, email, wallet_balance FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch();
if (!$user_info) die("User not found.");

// Fetch payouts
$stmt = $pdo->prepare("
    SELECT * FROM wallet_payouts 
    WHERE user_id = ? 
    ORDER BY id DESC
");
$stmt->execute([$user_id]);
$payouts = $stmt->fetchAll();

$total_paid = 0;
foreach ($payouts as $p) $total_paid += (float)$p['amount'];

include 'header.php';
?>

<style>
    .wh-wrapper {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px 15px 40px;
    }
    .wh-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);
        color: #fff;
        border-radius: 18px;
        padding: 28px 32px;
        margin-bottom: 24px;
        box-shadow: 0 12px 40px rgba(15,23,42,0.15);
    }
    .wh-header h2 {
        margin: 0;
        font-weight: 800;
        font-size: 1.5rem;
        letter-spacing: 0.3px;
    }
    .wh-header p {
        margin: 6px 0 0;
        opacity: 0.85;
        font-size: 0.88rem;
    }
    .wh-balance-box {
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 12px;
        padding: 14px 20px;
        margin-top: 16px;
        display: inline-block;
    }
    .wh-balance-box .lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        opacity: 0.8;
        letter-spacing: 1px;
        font-weight: 700;
    }
    .wh-balance-box .val {
        font-size: 1.5rem;
        font-weight: 800;
        margin-top: 3px;
    }

    .wh-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }
    .wh-sc {
        background: #fff;
        border-radius: 14px;
        padding: 16px 18px;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .wh-sc .icon {
        width: 42px; height: 42px;
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
    }
    .wh-sc.paid { border-color: #6ee7b7; background: #f0fdf4; }
    .wh-sc.paid .icon { background: #d1fae5; color: #065f46; }
    .wh-sc.paid .val { color: #059669; }
    .wh-sc.count { border-color: #93b5e8; background: #eff6ff; }
    .wh-sc.count .icon { background: #dbeafe; color: #1e40af; }
    .wh-sc.count .val { color: #1e40af; }
    .wh-sc .lbl {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .wh-sc .val {
        font-size: 1.25rem;
        font-weight: 800;
        margin-top: 2px;
    }

    .wh-table-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        overflow: hidden;
    }
    .wh-table-header {
        background: #f8fafc;
        padding: 16px 24px;
        border-bottom: 2px solid #e2e8f0;
    }
    .wh-table-header h5 {
        margin: 0;
        font-weight: 800;
        color: #0f172a;
        font-size: 1rem;
    }
    .wh-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .wh-table th {
        background: #1e293b;
        color: #fff;
        font-size: 0.68rem;
        text-transform: uppercase;
        padding: 12px 14px;
        letter-spacing: 0.5px;
        text-align: left;
        font-weight: 700;
    }
    .wh-table td {
        padding: 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .wh-table tr:hover { background: #f8fafc; }
    .wh-table .amount-cell {
        font-weight: 800;
        color: #059669;
        font-size: 0.95rem;
        text-align: right;
    }
    .utr-box {
        font-family: 'Courier New', monospace;
        background: #eff6ff;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        border: 1px solid #bfdbfe;
        color: #1e40af;
        font-weight: 700;
        display: inline-block;
    }
    .badge-paid {
        background: #dcfce7;
        color: #166534;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .empty-box {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    .empty-box .ico {
        font-size: 3rem;
        opacity: 0.25;
        margin-bottom: 12px;
    }
    .empty-box h5 {
        font-weight: 700;
        color: #64748b;
        font-size: 1rem;
        margin-bottom: 6px;
    }

    @media print {
        .sidebar, .top-nav, .top-bar, footer, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        .wh-wrapper { max-width: 100% !important; padding: 0 !important; }
        .wh-header { border-radius: 0 !important; }
    }

    @media (max-width: 768px) {
        .wh-table { font-size: 0.75rem; }
        .wh-table th, .wh-table td { padding: 10px 8px; }
        .wh-header { padding: 20px; }
        .wh-header h2 { font-size: 1.2rem; }
    }
</style>

<div class="wh-wrapper">
    <!-- Header -->
    <div class="wh-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2><i class="fas fa-receipt me-2"></i> Wallet Payment History</h2>
                <p>आपके wallet में भेजे गए सभी payments का रिकॉर्ड</p>
            </div>
            <a href="my_earnings.php" class="btn btn-light rounded-pill px-4 no-print" style="color:#1e3a8a; font-weight:700;">
                <i class="fas fa-file-invoice-dollar me-1"></i> My Earnings
            </a>
        </div>
        <div class="wh-balance-box">
            <div class="lbl">Current Wallet Balance</div>
            <div class="val">₹ <?= indianCurrencyFormat($user_info['wallet_balance']) ?></div>
        </div>
    </div>

    <!-- Summary -->
    <div class="wh-summary">
        <div class="wh-sc paid">
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="lbl">Total Received</div>
                <div class="val">₹ <?= indianCurrencyFormat($total_paid) ?></div>
            </div>
        </div>
        <div class="wh-sc count">
            <div class="icon"><i class="fas fa-list"></i></div>
            <div>
                <div class="lbl">Total Payments</div>
                <div class="val"><?= count($payouts) ?></div>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="wh-table-card">
        <div class="wh-table-header">
            <h5><i class="fas fa-history me-2 text-primary"></i> Payment History (<?= count($payouts) ?>)</h5>
        </div>

        <?php if (empty($payouts)): ?>
            <div class="empty-box">
                <div class="ico"><i class="fas fa-inbox"></i></div>
                <h5>No Payments Yet</h5>
                <p>अभी तक आपके wallet में कोई payment नहीं आया है।</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="wh-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>UTR / Transaction No.</th>
                            <th>Bank Details</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payouts as $p): ?>
                        <tr>
                            <td style="white-space:nowrap; font-weight:600;">
                                <?= date('d M Y', strtotime($p['payment_date'])) ?>
                            </td>
                            <td>
                                <span class="utr-box"><?= htmlspecialchars($p['utr_no'] ?: 'N/A') ?></span>
                            </td>
                            <td style="font-size:0.78rem;">
                                <?php if (!empty($p['bank_name'])): ?>
                                    <strong><?= htmlspecialchars($p['bank_name']) ?></strong>
                                    <?php if (!empty($p['account_number'])): ?>
                                        <div style="color:#64748b; font-size:0.72rem;">
                                            A/c: ****<?= htmlspecialchars(substr($p['account_number'], -4)) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem; color:#475569;">
                                <?= htmlspecialchars($p['notes'] ?: '—') ?>
                            </td>
                            <td class="amount-cell">+ ₹ <?= indianCurrencyFormat($p['amount']) ?></td>
                            <td class="text-center">
                                <span class="badge-paid">
                                    <i class="fas fa-check-circle"></i> Paid
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
