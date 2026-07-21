<?php
// ============================================================
// 📊 Admin Dashboard – Stats Overview (No Home Content)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- Fetch Stats ----
$auction_count = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$customer_count = $pdo->query("SELECT COUNT(*) FROM user_properties WHERE status = 'approved'")->fetchColumn();
$total_coins = $pdo->query("SELECT COALESCE(SUM(coins), 0) FROM users")->fetchColumn();
$total_wallet = $pdo->query("SELECT COALESCE(SUM(wallet_balance), 0) FROM users")->fetchColumn();
$pending_subs = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'pending'")->fetchColumn();
$paid_subs = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' OR status = 'paid'")->fetchColumn();
$total_paid_amount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM subscriptions WHERE status = 'active' OR status = 'paid'")->fetchColumn();

require_once __DIR__ . '/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4 text-light"><i class="fas fa-chart-pie me-2"></i>Dashboard Overview</h2>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-primary mx-auto"><i class="fas fa-gavel"></i></div>
                <h3 class="mt-3"><?= number_format($auction_count) ?></h3>
                <p class="text-muted mb-0">Total Auction Properties</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-success mx-auto"><i class="fas fa-users"></i></div>
                <h3 class="mt-3"><?= number_format($user_count) ?></h3>
                <p class="text-muted mb-0">Total Users</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-warning mx-auto"><i class="fas fa-home"></i></div>
                <h3 class="mt-3"><?= number_format($customer_count) ?></h3>
                <p class="text-muted mb-0">Customer Properties</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-primary mx-auto"><i class="fas fa-coins"></i></div>
                <h3 class="mt-3"><?= number_format($total_coins) ?></h3>
                <p class="text-muted mb-0">Total Coins</p>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-success mx-auto"><i class="fas fa-wallet"></i></div>
                <h3 class="mt-3">₹ <?= number_format($total_wallet, 2) ?></h3>
                <p class="text-muted mb-0">Total Wallet Balance</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-warning mx-auto"><i class="fas fa-hourglass-half"></i></div>
                <h3 class="mt-3"><?= number_format($pending_subs) ?></h3>
                <p class="text-muted mb-0">Pending Subscriptions</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-success mx-auto"><i class="fas fa-check-circle"></i></div>
                <h3 class="mt-3"><?= number_format($paid_subs) ?></h3>
                <p class="text-muted mb-0">Paid Subscriptions</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-premium text-center">
                <div class="stat-icon bg-soft-primary mx-auto"><i class="fas fa-money-bill-wave"></i></div>
                <h3 class="mt-3">₹ <?= number_format($total_paid_amount, 2) ?></h3>
                <p class="text-muted mb-0">Total Paid Amount</p>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6">
            <div class="card-premium">
                <h5><i class="fas fa-clock me-2"></i>Recent Users</h5>
                <table class="table table-sm table-dark table-striped">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
                    <tbody>
                        <?php foreach ($pdo->query("SELECT id, name, email, created_at FROM users ORDER BY id DESC LIMIT 5") as $u): ?>
                        <tr><td><?= $u['id'] ?></td><td><?= htmlspecialchars($u['name']) ?></td><td><?= htmlspecialchars($u['email']) ?></td><td><?= date('d M Y', strtotime($u['created_at'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-premium">
                <h5><i class="fas fa-gavel me-2"></i>Recent Properties</h5>
                <table class="table table-sm table-dark table-striped">
                    <thead><tr><th>ID</th><th>Title</th><th>Price</th><th>Added</th></tr></thead>
                    <tbody>
                        <?php foreach ($pdo->query("SELECT id, title, price, created_at FROM properties ORDER BY id DESC LIMIT 5") as $p): ?>
                        <tr><td><?= $p['id'] ?></td><td><?= htmlspecialchars($p['title']) ?></td><td>₹ <?= number_format($p['price'], 2) ?></td><td><?= date('d M Y', strtotime($p['created_at'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// require_once __DIR__ . '/footer.php';
?>
