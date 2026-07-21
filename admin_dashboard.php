<?php
// ============================================================
// 📊 Admin Dashboard – Simple Stats
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

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
    <h2 class="mb-4 text-light">📊 Dashboard Overview</h2>
    <div class="row g-4">
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-primary mx-auto"><i class="fas fa-gavel"></i></div><h3><?= number_format($auction_count) ?></h3><p class="text-muted">Auction Properties</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-success mx-auto"><i class="fas fa-users"></i></div><h3><?= number_format($user_count) ?></h3><p class="text-muted">Total Users</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-warning mx-auto"><i class="fas fa-home"></i></div><h3><?= number_format($customer_count) ?></h3><p class="text-muted">Customer Properties</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-primary mx-auto"><i class="fas fa-coins"></i></div><h3><?= number_format($total_coins) ?></h3><p class="text-muted">Total Coins</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-success mx-auto"><i class="fas fa-wallet"></i></div><h3>₹ <?= number_format($total_wallet, 2) ?></h3><p class="text-muted">Wallet Balance</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-warning mx-auto"><i class="fas fa-hourglass-half"></i></div><h3><?= number_format($pending_subs) ?></h3><p class="text-muted">Pending Subs</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-success mx-auto"><i class="fas fa-check-circle"></i></div><h3><?= number_format($paid_subs) ?></h3><p class="text-muted">Paid Subs</p></div></div>
        <div class="col-md-3"><div class="card-premium text-center"><div class="stat-icon bg-soft-primary mx-auto"><i class="fas fa-money-bill-wave"></i></div><h3>₹ <?= number_format($total_paid_amount, 2) ?></h3><p class="text-muted">Total Paid</p></div></div>
    </div>
</div>
