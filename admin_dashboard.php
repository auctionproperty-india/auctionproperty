<?php
// ============================================================
// 📊 Admin Dashboard – White + Dark Blue Theme
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- Helper: Safe Date Format ----
function safeDateFormat($dateStr) {
    if (empty($dateStr) || strtotime($dateStr) === false) {
        return 'N/A';
    }
    return date('d M Y', strtotime($dateStr));
}

// ---- Fetch all stats ----
$total_properties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$customer_properties = $pdo->query("SELECT COUNT(*) FROM user_properties WHERE status = 'approved'")->fetchColumn();
$total_coins = $pdo->query("SELECT COALESCE(SUM(coins), 0) FROM users")->fetchColumn();
$total_wallet = $pdo->query("SELECT COALESCE(SUM(wallet_balance), 0) FROM users")->fetchColumn();
$pending_subs = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'pending'")->fetchColumn();
$active_subs = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' OR status = 'paid'")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE status = 'active' OR status = 'paid'")->fetchColumn();

// ---- Recent users (limit 5) ----
$recent_users = $pdo->query("SELECT id, name, email, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

// ---- Recent properties (limit 5) ----
$recent_props = $pdo->query("SELECT id, title, price, created_at FROM properties ORDER BY id DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<style>
    .dashboard-container { background: #f8fafc; border-radius: 24px; padding: 20px 25px; margin: 0; }
    .dashboard-title { color: #1e293b; font-weight: 700; margin-bottom: 20px; font-size: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
    .dashboard-title i { color: #1e3a8a; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-bottom: 30px; }
    .stat-card-white { background: #ffffff; border-radius: 16px; padding: 18px 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.25s ease; color: #0f172a; }
    .stat-card-white:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); border-color: #b9d0f0; }
    .stat-card-white .stat-icon { font-size: 2rem; display: inline-block; background: #eef2ff; padding: 8px 10px; border-radius: 12px; margin-bottom: 8px; color: #1e3a8a; }
    .stat-card-white .stat-number { font-size: 1.8rem; font-weight: 800; margin: 4px 0 2px; letter-spacing: -0.5px; color: #0f172a; }
    .stat-card-white .stat-number.currency { font-size: 1.5rem; }
    .stat-card-white .stat-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600; color: #475569; }
    .stat-card-white .stat-sub { font-size: 0.7rem; color: #94a3b8; margin-top: 2px; }
    .card-table-white { background: #ffffff; border-radius: 16px; padding: 16px 18px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .card-table-white h5 { color: #1e293b; font-weight: 600; margin-bottom: 12px; }
    .card-table-white h5 i { color: #1e3a8a; }
    .table-white { color: #0f172a; font-size: 0.9rem; }
    .table-white th { color: #475569; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.4px; border-bottom: 2px solid #e2e8f0; padding: 10px 8px; }
    .table-white td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .table-white tbody tr:hover { background: #f8fafc; }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .dashboard-container { padding: 15px; margin-top: 0; }
        .stat-card-white .stat-number { font-size: 1.4rem; }
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-title">
        <i class="fas fa-chart-pie"></i> Dashboard Overview
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-gavel"></i></div>
            <div class="stat-number"><?= number_format($total_properties) ?></div>
            <div class="stat-label">Total Auction Properties</div>
            <div class="stat-sub">All properties listed</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= number_format($total_users) ?></div>
            <div class="stat-label">Total Users</div>
            <div class="stat-sub">Registered users</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-home"></i></div>
            <div class="stat-number"><?= number_format($customer_properties) ?></div>
            <div class="stat-label">Customer Properties</div>
            <div class="stat-sub">Approved listings</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-number"><?= number_format($total_coins) ?></div>
            <div class="stat-label">Total Coins</div>
            <div class="stat-sub">All user coins</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-number currency">₹ <?= number_format($total_wallet, 2) ?></div>
            <div class="stat-label">Total Wallet Balance</div>
            <div class="stat-sub">All users' wallet</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-number"><?= number_format($pending_subs) ?></div>
            <div class="stat-label">Pending Subscriptions</div>
            <div class="stat-sub">Awaiting approval</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= number_format($active_subs) ?></div>
            <div class="stat-label">Active Subscriptions</div>
            <div class="stat-sub">Paid & active</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number currency">₹ <?= number_format($total_revenue, 2) ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub">From subscriptions</div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card-table-white">
                <h5><i class="fas fa-user-plus me-2"></i>Recent Users</h5>
                <div class="table-responsive">
                    <table class="table table-white">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= safeDateFormat($u['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-table-white">
                <h5><i class="fas fa-gavel me-2"></i>Recent Properties</h5>
                <div class="table-responsive">
                    <table class="table table-white">
                        <thead>
                            <tr><th>ID</th><th>Title</th><th>Price</th><th>Added</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_props as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['title']) ?></td>
                                <td>₹ <?= number_format($p['price'], 2) ?></td>
                                <td><?= safeDateFormat($p['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// require_once __DIR__ . '/footer.php';
?>
