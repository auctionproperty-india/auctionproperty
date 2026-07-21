<?php
// ============================================================
// 📊 Admin Dashboard – Professional Laravel-Style Stats
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
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

// ---- Recent users (for table) ----
$recent_users = $pdo->query("SELECT id, name, email, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

// ---- Recent properties (for table) ----
$recent_props = $pdo->query("SELECT id, title, price, created_at FROM properties ORDER BY id DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<style>
    /* Dashboard specific styles */
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #1e293b;
        border-radius: 16px;
        padding: 20px 18px;
        border: 1px solid #334155;
        transition: all 0.3s ease;
        color: #e2e8f0;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.3);
        border-color: #475569;
    }
    .stat-card .stat-icon {
        font-size: 2rem;
        opacity: 0.7;
        margin-bottom: 8px;
        display: inline-block;
        background: #0f172a;
        padding: 10px;
        border-radius: 12px;
        line-height: 1;
    }
    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: 800;
        margin: 8px 0 4px;
        letter-spacing: -0.5px;
    }
    .stat-card .stat-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #94a3b8;
        font-weight: 600;
    }
    .stat-card .stat-sub {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 4px;
    }
    .stat-card.color-1 .stat-icon { color: #60a5fa; }
    .stat-card.color-2 .stat-icon { color: #34d399; }
    .stat-card.color-3 .stat-icon { color: #fbbf24; }
    .stat-card.color-4 .stat-icon { color: #f472b6; }
    .stat-card.color-5 .stat-icon { color: #a78bfa; }
    .stat-card.color-6 .stat-icon { color: #fb923c; }
    .stat-card.color-7 .stat-icon { color: #22d3ee; }
    .stat-card.color-8 .stat-icon { color: #f87171; }
    .stat-card .stat-number.currency {
        font-size: 1.6rem;
    }
    .card-premium {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .card-premium h5 {
        color: #e2e8f0;
        font-weight: 600;
        margin-bottom: 15px;
    }
    .table-dark {
        color: #e2e8f0;
    }
    .table-dark th {
        color: #94a3b8;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #334155;
    }
    .table-dark td {
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .table-dark tbody tr:hover {
        background: #2d3748;
    }
    .text-muted-light {
        color: #94a3b8;
    }
    .gap-4 { gap: 1.5rem; }
</style>

<div class="container-fluid">
    <h2 class="mb-4 text-light"><i class="fas fa-chart-pie me-2"></i>Dashboard Overview</h2>

    <!-- Stats Grid -->
    <div class="dashboard-stats">
        <div class="stat-card color-1">
            <div class="stat-icon"><i class="fas fa-gavel"></i></div>
            <div class="stat-number"><?= number_format($total_properties) ?></div>
            <div class="stat-label">Total Auction Properties</div>
            <div class="stat-sub">All properties listed</div>
        </div>
        <div class="stat-card color-2">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= number_format($total_users) ?></div>
            <div class="stat-label">Total Users</div>
            <div class="stat-sub">Registered users</div>
        </div>
        <div class="stat-card color-3">
            <div class="stat-icon"><i class="fas fa-home"></i></div>
            <div class="stat-number"><?= number_format($customer_properties) ?></div>
            <div class="stat-label">Customer Properties</div>
            <div class="stat-sub">Approved listings</div>
        </div>
        <div class="stat-card color-4">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-number"><?= number_format($total_coins) ?></div>
            <div class="stat-label">Total Coins</div>
            <div class="stat-sub">All user coins</div>
        </div>
        <div class="stat-card color-5">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-number currency">₹ <?= number_format($total_wallet, 2) ?></div>
            <div class="stat-label">Total Wallet Balance</div>
            <div class="stat-sub">All users' wallet</div>
        </div>
        <div class="stat-card color-6">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-number"><?= number_format($pending_subs) ?></div>
            <div class="stat-label">Pending Subscriptions</div>
            <div class="stat-sub">Awaiting approval</div>
        </div>
        <div class="stat-card color-7">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= number_format($active_subs) ?></div>
            <div class="stat-label">Active Subscriptions</div>
            <div class="stat-sub">Paid & active</div>
        </div>
        <div class="stat-card color-8">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number currency">₹ <?= number_format($total_revenue, 2) ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub">From subscriptions</div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card-premium">
                <h5><i class="fas fa-user-plus me-2"></i>Recent Users</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-sm">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-premium">
                <h5><i class="fas fa-gavel me-2"></i>Recent Properties</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-sm">
                        <thead>
                            <tr><th>ID</th><th>Title</th><th>Price</th><th>Added</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_props as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['title']) ?></td>
                                <td>₹ <?= number_format($p['price'], 2) ?></td>
                                <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
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
