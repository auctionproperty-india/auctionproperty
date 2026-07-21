<?php
// ============================================================
// ✅ Referrals Page – With Safe Date Formatting
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php';

// ---- Helper function to safely format date ----
function safeDateFormat($dateStr) {
    if (empty($dateStr) || strtotime($dateStr) === false) {
        return 'Not Activated';
    }
    return date('d M Y', strtotime($dateStr));
}

// ---- Get user's referral code ----
$stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$referral_code = $user['referral_code'] ?? '';

// ---- Get user's referral earnings summary ----
$earnings_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_referrals,
        COALESCE(SUM(net_amount), 0) as total_earnings,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END), 0) as paid_earnings,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END), 0) as pending_earnings
    FROM user_referral_earnings 
    WHERE user_id = ?
");
$earnings_stmt->execute([$user_id]);
$earnings = $earnings_stmt->fetch();

// ---- Get all referrals with their status ----
$referrals_stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at,
        u.activation_date,
        u.status as user_status,
        p.name as package_name,
        s.status as sub_status,
        s.start_date,
        s.end_date,
        ure.amount as referral_amount,
        ure.net_amount as referral_net,
        ure.status as referral_payment_status,
        ure.created_at as referral_created_at,
        ure.paid_at as referral_paid_at
    FROM users u
    LEFT JOIN (
        SELECT DISTINCT ON (user_id) user_id, package_id, status, start_date, end_date
        FROM subscriptions
        WHERE status = 'active' OR status = 'paid'
        ORDER BY user_id, id DESC
    ) s ON u.id = s.user_id
    LEFT JOIN packages p ON s.package_id = p.id
    LEFT JOIN user_referral_earnings ure ON u.id = ure.referred_user_id AND ure.user_id = ?
    WHERE u.referred_by = ?
    ORDER BY u.created_at DESC
");
$referrals_stmt->execute([$user_id, $user_id]);
$referrals = $referrals_stmt->fetchAll();

// ---- Get referral link ----
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$referral_link = $base_url . '/register.php?ref=' . $referral_code;
?>

<style>
    .referral-container {
        background: white;
        border-radius: 24px;
        padding: 25px;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
    }
    .referral-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: #f8fafc;
        border-radius: 16px;
        padding: 18px 20px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
    .stat-card h3 {
        font-weight: 700;
        margin: 0;
        font-size: 1.5rem;
    }
    .stat-card p {
        margin: 0;
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-card .stat-icon {
        font-size: 1.5rem;
        opacity: 0.6;
        margin-bottom: 4px;
    }
    .referral-link-box {
        background: #f1f5f9;
        border-radius: 12px;
        padding: 12px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
    }
    .referral-link-box input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 6px 0;
        font-size: 0.9rem;
        color: #0f172a;
        outline: none;
        min-width: 200px;
    }
    .referral-link-box .btn-copy {
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 6px 16px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s;
    }
    .referral-link-box .btn-copy:hover {
        background: #1d4ed8;
        transform: scale(1.02);
    }
    .table-referrals {
        margin-top: 15px;
        font-size: 0.9rem;
    }
    .table-referrals th {
        background: #f1f5f9;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        padding: 10px 12px;
    }
    .table-referrals td {
        padding: 10px 12px;
        vertical-align: middle;
    }
    .badge-package {
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 30px;
        font-weight: 600;
    }
    .badge-package.silver { background: #e2e8f0; color: #475569; }
    .badge-package.gold { background: #fef3c7; color: #92400e; }
    .badge-package.platinum { background: #dbeafe; color: #1e40af; }
    .badge-package.diamond { background: #d1fae5; color: #065f46; }
    .badge-status {
        font-size: 0.7rem;
        padding: 3px 12px;
        border-radius: 30px;
        font-weight: 600;
    }
    .badge-status.active { background: #dcfce7; color: #166534; }
    .badge-status.inactive { background: #fee2e2; color: #991b1b; }
    .badge-status.pending { background: #fef3c7; color: #92400e; }
    .badge-status.paid { background: #dbeafe; color: #1e40af; }
    .badge-status.not-activated { background: #f1f5f9; color: #64748b; }
    .text-muted-small { font-size: 0.75rem; color: #94a3b8; }
    @media (max-width: 768px) {
        .referral-stats { grid-template-columns: repeat(2, 1fr); }
        .referral-link-box { flex-direction: column; align-items: stretch; }
        .table-referrals { font-size: 0.75rem; }
        .table-referrals th, .table-referrals td { padding: 6px 8px; }
    }
</style>

<div class="container-fluid">
    <div class="referral-container">
        <h4><i class="fas fa-link me-2"></i>Referrals</h4>

        <!-- Stats -->
        <div class="referral-stats">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <h3><?= number_format($earnings['total_referrals'] ?? 0) ?></h3>
                <p>Total Referrals</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <h3>₹ <?= number_format($earnings['total_earnings'] ?? 0, 2) ?></h3>
                <p>Total Earnings</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <h3>₹ <?= number_format($earnings['paid_earnings'] ?? 0, 2) ?></h3>
                <p>Paid</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <h3>₹ <?= number_format($earnings['pending_earnings'] ?? 0, 2) ?></h3>
                <p>Pending</p>
            </div>
        </div>

        <!-- Referral Link -->
        <div class="referral-link-box">
            <i class="fas fa-share-alt" style="color:#64748b;"></i>
            <input type="text" id="referralLink" value="<?= htmlspecialchars($referral_link) ?>" readonly>
            <button class="btn-copy" onclick="copyReferralLink()"><i class="fas fa-copy"></i> Copy</button>
        </div>

        <!-- Referrals Table -->
        <?php if(count($referrals) > 0): ?>
            <div class="table-responsive">
                <table class="table table-referrals table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Registered On</th>
                            <th>Activation Date</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th>Earning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $row): 
                            // ✅ Safe date formatting – no warnings
                            $registered_on = safeDateFormat($row['created_at']);
                            $activation_date = safeDateFormat($row['activation_date']);
                            
                            // Package badge
                            $pkg = strtolower($row['package_name'] ?? '');
                            $pkgBadge = $pkg ? '<span class="badge-package ' . $pkg . '">' . htmlspecialchars($row['package_name']) . '</span>' : '<span class="badge-package" style="background:#f1f5f9;color:#94a3b8;">Free</span>';
                            
                            // Status badge
                            $status = $row['sub_status'] ?? 'inactive';
                            $statusBadge = '';
                            if ($status == 'active' || $status == 'paid') {
                                $statusBadge = '<span class="badge-status active">Active</span>';
                            } elseif ($status == 'pending') {
                                $statusBadge = '<span class="badge-status pending">Pending</span>';
                            } else {
                                $statusBadge = '<span class="badge-status inactive">Inactive</span>';
                            }
                            
                            // Earning
                            $earning = $row['referral_net'] ?? 0;
                            $earningDisplay = $earning > 0 ? '₹ ' . number_format($earning, 2) : '—';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['name'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($row['email'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                            <td><?= $registered_on ?></td>
                            <td><?= $activation_date ?></td>
                            <td><?= $pkgBadge ?></td>
                            <td><?= $statusBadge ?></td>
                            <td><?= $earningDisplay ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-muted text-center mt-3">
                <small>Showing <?= count($referrals) ?> referrals</small>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-user-plus" style="font-size:2rem; opacity:0.5;"></i>
                <p class="mt-2">You haven't referred anyone yet.</p>
                <p class="text-muted small">Share your referral link and earn rewards!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyReferralLink() {
    const input = document.getElementById('referralLink');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.querySelector('.btn-copy');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => { btn.innerHTML = originalText; }, 2000);
    }).catch(() => {
        // Fallback
        document.execCommand('copy');
        alert('Referral link copied!');
    });
}
</script>

<?php include 'footer.php'; ?>
