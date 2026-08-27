<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $gold = trim($_POST['gold_triggers']);
    $diamond = trim($_POST['diamond_triggers']);

    // Validate: only numbers and commas
    if (!preg_match('/^[\d,]*$/', $gold)) $gold = '';
    if (!preg_match('/^[\d,]*$/', $diamond)) $diamond = '';

    // Save to settings table
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('spin_special_enabled', ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?")->execute([$enabled, $enabled]);
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('spin_gold_triggers', ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?")->execute([$gold, $gold]);
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('spin_diamond_triggers', ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?")->execute([$diamond, $diamond]);

    $msg = "✅ Settings saved!";
}

// Load current settings
$enabled = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_special_enabled'")->fetchColumn() ?: 1;
$gold_triggers = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_gold_triggers'")->fetchColumn() ?: '1,3,5';
$diamond_triggers = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_diamond_triggers'")->fetchColumn() ?: '2,4';
?>
<div class="container-fluid mt-4">
    <h1>🎡 Spin Reward Settings</h1>
    <?php if (isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="enabled" id="enabled" <?= $enabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="enabled">
                        <strong>Enable Gold / Diamond rewards</strong> (if unchecked, wheel will never land on them)
                    </label>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Gold trigger spin numbers</label>
                        <input type="text" name="gold_triggers" class="form-control" value="<?= htmlspecialchars($gold_triggers) ?>" placeholder="e.g., 1,3,5">
                        <small class="text-muted">Comma‑separated spin numbers (1‑5). On these spins, the wheel will land on GOLD.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Diamond trigger spin numbers</label>
                        <input type="text" name="diamond_triggers" class="form-control" value="<?= htmlspecialchars($diamond_triggers) ?>" placeholder="e.g., 2,4">
                        <small class="text-muted">Comma‑separated spin numbers (1‑5). On these spins, the wheel will land on DIAMOND.</small>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="save" class="btn btn-primary">💾 Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4 alert alert-info">
        <strong>How it works:</strong>
        <ul>
            <li>Each slot has 5 spins.</li>
            <li>If enabled, the wheel will <strong>always</strong> land on the configured segment on the matching spin numbers.</li>
            <li>If you put overlapping numbers (e.g., Gold and Diamond both have '1'), Diamond will take precedence.</li>
            <li>When disabled, the wheel will <strong>never</strong> land on Gold or Diamond – only properties and coins.</li>
        </ul>
    </div>
</div>
<?php include 'footer.php'; ?>
