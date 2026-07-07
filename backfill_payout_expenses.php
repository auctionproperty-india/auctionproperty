<?php
require_once 'db.php';
require_once 'functions.php';

echo "<h2>🔧 Backfilling Accounting Expenses for Past Paid Payouts</h2>";

try {
    // Fetch all paid referral earnings that do NOT have an accounting entry yet.
    // We'll join with users to get referrer name.
    // We'll check existence by looking for an expense entry with description containing "Earning ID: X"
    $sql = "SELECT e.*, u.name as referrer_name 
            FROM user_referral_earnings e
            JOIN users u ON e.user_id = u.id
            WHERE e.status = 'paid'
            AND NOT EXISTS (
                SELECT 1 FROM account_entries a
                WHERE a.type = 'expense' 
                AND a.category = 'Referral Payout'
                AND a.description LIKE '%Earning ID: ' || e.id || '%'
            )
            ORDER BY e.paid_at DESC";
    $stmt = $pdo->query($sql);
    $earnings = $stmt->fetchAll();

    if (empty($earnings)) {
        echo "<p style='color:green;'>✅ All paid payouts already have accounting entries. Nothing to do.</p>";
        exit;
    }

    echo "<p>Found <strong>" . count($earnings) . "</strong> payouts without accounting entry. Processing...</p>";

    $added = 0;
    $failed = 0;
    $messages = [];

    foreach ($earnings as $e) {
        // Skip if net amount is zero or null
        if (empty($e['net_amount']) || $e['net_amount'] <= 0) {
            continue;
        }

        $user_id = $e['user_id'];
        $referrer_name = $e['referrer_name'];
        $net_amount = $e['net_amount'];
        $utr = $e['utr_no'] ?? 'N/A';
        $earning_id = $e['id'];

        $description = "Referral payout to $referrer_name (ID: $user_id) - Net ₹" . indianCurrencyFormat($net_amount) . " | UTR: $utr | Earning ID: $earning_id";
        
        // Insert expense entry
        $result = addAccountEntry($pdo, 'expense', $net_amount, $description, 'Referral Payout', date('Y-m-d', strtotime($e['paid_at'])));

        if ($result) {
            $added++;
            $messages[] = "✅ Added entry for Earning ID $earning_id: ₹" . indianCurrencyFormat($net_amount) . " for $referrer_name";
        } else {
            $failed++;
            $messages[] = "❌ Failed to add entry for Earning ID $earning_id";
        }
    }

    // Show summary
    echo "<hr>";
    echo "<h3>Summary</h3>";
    echo "<ul>";
    foreach ($messages as $msg) {
        echo "<li>$msg</li>";
    }
    echo "</ul>";
    echo "<p><strong>Total added:</strong> $added</p>";
    echo "<p><strong>Failed:</strong> $failed</p>";

    // Show current total expense
    $total_expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense' AND category = 'Referral Payout'")->fetchColumn();
    echo "<p><strong>Total expense from referral payouts now:</strong> ₹" . indianCurrencyFormat($total_expense) . "</p>";

    echo "<hr><p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
