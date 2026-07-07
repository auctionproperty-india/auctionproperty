<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// ---- Referral Data ----
$referral_link = getReferralLink($user_id);

// Pending earnings (gross)
$pending_earnings = getReferralEarnings($pdo, $user_id, 'pending');
$total_pending_gross = array_sum(array_column($pending_earnings, 'amount'));

// Paid earnings (gross for summary)
$paid_earnings_all = getReferralEarnings($pdo, $user_id, 'paid');
$total_paid_gross = array_sum(array_column($paid_earnings_all, 'amount'));

// ---- Group paid earnings by UTR (or by paid_at if UTR empty) ----
$grouped_paid = [];
foreach ($paid_earnings_all as $e) {
    $key = !empty($e['utr_no']) ? $e['utr_no'] : $e['paid_at'];
    if (!isset($grouped_paid[$key])) {
        $grouped_paid[$key] = [
            'utr' => $e['utr_no'] ?? 'N/A',
            'paid_at' => $e['paid_at'],
            'total_gross' => 0,
            'total_tds' => 0,
            'total_admin' => 0,
            'total_net' => 0,
            'items' => []
        ];
    }
    $grouped_paid[$key]['total_gross'] += $e['amount'];
    $grouped_paid[$key]['total_tds'] += $e['tds_deducted'] ?? 0;
    $grouped_paid[$key]['total_admin'] += $e['admin_charge_deducted'] ?? 0;
    $grouped_paid[$key]['total_net'] += $e['net_amount'] ?? 0;
    $grouped_paid[$key]['items'][] = $e;
}
// Sort by paid_at descending
usort($grouped_paid, function($a, $b) {
    return strtotime($b['paid_at']) - strtotime($a['paid_at']);
});

// Team members and subscription requests (unchanged)
$team_members = getReferredUsers($pdo, $user_id);
$user_subs = $pdo->prepare("SELECT s.*, p.name as pkg_name FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
$user_subs->execute([$user_id]);
$user_subs = $user_subs->fetchAll();
?>
<style>
    .group-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
        background: #f8fafc;
        transition: 0.2s;
        cursor: pointer;
    }
    .group-card:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
    }
    .group-card .summary {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    .group-card .summary .amount {
        font-weight: 700;
        color: #0f172a;
    }
    .group-card .summary .net {
        color: #10b981;
        font-weight: 800;
    }
    .group-card .summary .utr {
        font-size: 0.85rem;
        color: #64748b;
    }
    .breakdown-item {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px dashed #e2e8f0;
        font-size: 0.9rem;
    }
    .breakdown-item:last-child {
        border-bottom: none;
    }
    .modal-content {
        border-radius: 20px;
    }
    .modal-header {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: white;
        border-radius: 20px 20px 0 0;
    }
    .modal-footer {
        border-top: none;
    }
</style>

<div class="card-premium" style="border:1px solid #10b981; background:#f0fdf4;">
    <h5><i class="fas fa-link me-2" style="color:#10b981;"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" class="form-control border-success" id="refLink" value="<?= $referral_link ?>" readonly>
        <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <div class="mt-2">
        <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($total_pending_gross) ?></span>
        <span class="badge bg-success ms-2">✅ Paid (Gross): ₹ <?= indianCurrencyFormat($total_paid_gross) ?></span>
    </div>
</div>

<!-- ===== Pending Earnings (Gross) ===== -->
<div class="card-premium mt-4">
    <h5><i class="fas fa-clock me-2"></i>Pending Earnings</h5>
    <?php if(count($pending_earnings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Referred User</th><th>Package</th><th>Gross Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach($pending_earnings as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['referred_name']) ?></td>
                        <td><?= htmlspecialchars($e['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($e['amount']) ?></td>
                        <td><span class="badge bg-warning">Pending</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No pending earnings.</p>
    <?php endif; ?>
</div>

<!-- ===== Paid Earnings (Grouped by UTR = Salary Slip Style) ===== -->
<div class="card-premium mt-4">
    <h5><i class="fas fa-history me-2"></i>Payment History</h5>
    <?php if(count($grouped_paid) > 0): ?>
        <?php foreach($grouped_paid as $group): ?>
            <div class="group-card" data-bs-toggle="modal" data-bs-target="#detailModal" 
                 data-utr="<?= htmlspecialchars($group['utr']) ?>"
                 data-paidat="<?= date('d M Y, h:i A', strtotime($group['paid_at'])) ?>"
                 data-totalgross="<?= indianCurrencyFormat($group['total_gross']) ?>"
                 data-totaltds="<?= indianCurrencyFormat($group['total_tds']) ?>"
                 data-totaladmin="<?= indianCurrencyFormat($group['total_admin']) ?>"
                 data-totalnet="<?= indianCurrencyFormat($group['total_net']) ?>"
                 data-items='<?= json_encode($group['items']) ?>'>
                <div class="summary">
                    <div>
                        <span class="badge bg-primary">UTR: <?= htmlspecialchars($group['utr']) ?></span>
                        <span class="badge bg-secondary"><?= date('d M Y', strtotime($group['paid_at'])) ?></span>
                    </div>
                    <div>
                        <span class="amount">Gross: ₹<?= indianCurrencyFormat($group['total_gross']) ?></span>
                        <span class="net">| Net: ₹<?= indianCurrencyFormat($group['total_net']) ?></span>
                        <i class="fas fa-chevron-right ms-2 text-muted"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No paid earnings yet.</p>
    <?php endif; ?>
</div>

<!-- ===== Detail Modal (Salary Slip Style) ===== -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Payment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detailContent">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Team Members (unchanged) ===== -->
<div class="card-premium mt-4">
    <h4><i class="fas fa-users me-2"></i>My Team (<?= count($team_members) ?>)</h4>
    <?php if(count($team_members) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Registered On</th><th>Activation Date</th></tr></thead>
                <tbody>
                <?php foreach($team_members as $tm): ?>
                    <tr>
                        <td><?= htmlspecialchars($tm['name']) ?></td>
                        <td><?= htmlspecialchars($tm['email']) ?></td>
                        <td><?= htmlspecialchars($tm['phone'] ?? 'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($tm['reg_date'])) ?></td>
                        <td><?= $tm['activation_date'] ? date('d M Y', strtotime($tm['activation_date'])) : '<span class="text-muted">Not Activated</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">You haven't referred anyone yet. Share your referral link!</p>
    <?php endif; ?>
</div>

<!-- ===== Subscription Requests (unchanged) ===== -->
<div class="card-premium mt-4">
    <h4><i class="fas fa-history me-2"></i>Your Subscription Requests</h4>
    <?php if(count($user_subs) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>UTR</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
                <tbody>
                <?php foreach($user_subs as $us): ?>
                    <tr>
                        <td><?= htmlspecialchars($us['pkg_name']) ?></td>
                        <td>₹<?= $us['amount'] ?></td>
                        <td><span class="badge bg-<?= $us['status']=='active'?'success':($us['status']=='pending'?'warning':'danger') ?>"><?= $us['status'] ?></span></td>
                        <td><?= $us['payment_method'] ?></td>
                        <td><?= htmlspecialchars($us['utr'] ?? 'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($us['created_at'])) ?></td>
                        <td><?= $us['start_date'] ? date('d M Y', strtotime($us['start_date'])) : ($us['status']=='rejected' ? 'Rejected' : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No subscription requests yet.</p>
    <?php endif; ?>
</div>

<script>
    function copyRef() { 
        let inp = document.getElementById('refLink'); 
        inp.select(); 
        navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy')); 
    }

    // Populate modal on card click
    document.querySelectorAll('.group-card').forEach(card => {
        card.addEventListener('click', function() {
            const data = this.dataset;
            const items = JSON.parse(data.items);
            let html = `
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>UTR:</strong> ${data.utr}</div>
                    <div class="col-md-4"><strong>Date:</strong> ${data.paidat}</div>
                    <div class="col-md-4"><strong>Total Net:</strong> <span class="text-success fw-bold">₹${data.totalnet}</span></div>
                </div>
                <hr>
                <h6>Breakdown by Referred User</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr><th>Referred User</th><th>Package</th><th>Gross</th><th>TDS</th><th>Admin Charge</th><th>Net</th></tr>
                        </thead>
                        <tbody>
            `;
            items.forEach(item => {
                html += `
                    <tr>
                        <td>${item.referred_name || 'N/A'}</td>
                        <td>${item.package_name || 'N/A'}</td>
                        <td>₹${indianCurrencyFormat(item.amount)}</td>
                        <td>₹${indianCurrencyFormat(item.tds_deducted || 0)}</td>
                        <td>₹${indianCurrencyFormat(item.admin_charge_deducted || 0)}</td>
                        <td><strong>₹${indianCurrencyFormat(item.net_amount || 0)}</strong></td>
                    </tr>
                `;
            });
            html += `
                        </tbody>
                    </table>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6"><strong>Total Gross:</strong> ₹${data.totalgross}</div>
                    <div class="col-md-6"><strong>Total TDS:</strong> ₹${data.totaltds}</div>
                    <div class="col-md-6"><strong>Total Admin Charge:</strong> ₹${data.totaladmin}</div>
                    <div class="col-md-6"><strong>Net Paid:</strong> <span class="text-success fw-bold">₹${data.totalnet}</span></div>
                </div>
            `;
            document.getElementById('detailContent').innerHTML = html;
        });
    });

    // Helper function to format currency (if not available in JS, use PHP's function? we'll replicate)
    function indianCurrencyFormat(num) {
        if (!num) return '0';
        num = parseFloat(num);
        let parts = num.toFixed(2).split('.');
        let integerPart = parts[0];
        let decimalPart = parts[1] || '00';
        let lastThree = integerPart.substring(integerPart.length - 3);
        let otherNumbers = integerPart.substring(0, integerPart.length - 3);
        if (otherNumbers != '') {
            lastThree = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + "," + lastThree;
        }
        return lastThree + (decimalPart !== '00' ? '.' + decimalPart : '');
    }
</script>

<?php include 'footer.php'; ?>
