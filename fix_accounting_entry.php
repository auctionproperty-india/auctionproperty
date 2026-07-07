<?php
require_once 'db.php';
require_once 'functions.php';

$user_email = 'dineshanand123@gmail.com';
$correct_amount = 3000; // Correct amount

echo "<h2>🔧 Fixing Accounting Entry for $user_email to ₹$correct_amount</h2>";

try {
    // ---- Step 1: Get user ----
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$user_email]);
    $user = $stmt->fetch();
    if (!$user) {
        die("❌ User not found with email: $user_email");
    }
    $user_id = $user['id'];
    $user_name = $user['name'];
    echo "✅ User: $user_name (ID: $user_id)<br>";

    // ---- Step 2: Find today's accounting entry for this user ----
    $today = date('Y-m-d');
    $search_desc = '%Manual subscription activation for user ' . $user_name . '%';

    $stmt = $pdo->prepare("SELECT id, amount, description, entry_date 
                           FROM account_entries 
                           WHERE description ILIKE ? AND entry_date = ? AND type = 'income'");
    $stmt->execute([$search_desc, $today]);
    $entries = $stmt->fetchAll();

    if (count($entries) == 0) {
        // If not found by description, try to find by user ID in description
        $search_desc2 = '%user ID: ' . $user_id . '%';
        $stmt = $pdo->prepare("SELECT id, amount, description, entry_date 
                               FROM account_entries 
                               WHERE description ILIKE ? AND entry_date = ? AND type = 'income'");
        $stmt->execute([$search_desc2, $today]);
        $entries = $stmt->fetchAll();
    }

    if (count($entries) == 0) {
        echo "❌ No accounting entry found for this user today.<br>";
        echo "You may need to add it manually from Accounting page.<br>";
        echo "Alternative: Delete the wrong entry (₹3500) from Accounting page and add a new entry of ₹3000.<br>";
        exit;
    }

    // ---- Step 3: Update the entry(s) ----
    $updated = 0;
    foreach ($entries as $e) {
        if ($e['amount'] == 3500) {
            // Update to correct amount
            $update = $pdo->prepare("UPDATE account_entries SET amount = ? WHERE id = ?");
            $update->execute([$correct_amount, $e['id']]);
            echo "✅ Entry ID {$e['id']} updated from ₹3500 to ₹{$correct_amount}.<br>";
            $updated++;
        } else {
            echo "Entry ID {$e['id']} is already ₹" . $e['amount'] . ". No change needed.<br>";
        }
    }

    if ($updated == 0) {
        echo "ℹ️ No entry with ₹3500 found. All entries are already correct.<br>";
    }

    // ---- Step 4: Show summary ----
    echo "<hr>";
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM account_entries 
                           WHERE description ILIKE ? AND type = 'income'");
    $stmt->execute(['%Manual subscription activation for user ' . $user_name . '%']);
    $total = $stmt->fetchColumn();
    echo "💰 Total income from this user's subscription (all entries): ₹" . ($total ? indianCurrencyFormat($total) : '0') . "<br>";

    echo "<hr><p style='color:green; font-weight:bold;'>✅ Accounting entry fixed!</p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
