<?php
require_once 'db.php';
require_once 'functions.php';

echo "<h2>🔧 Fixing Accounting Descriptions & Categories</h2>";

try {
    // Fetch all entries where description contains 'user ID' and category is 'Subscription'
    $entries = $pdo->query("SELECT id, description, category FROM account_entries WHERE description LIKE '%user ID%' OR category = 'Subscription'")->fetchAll();

    $updated = 0;
    foreach ($entries as $entry) {
        $new_desc = $entry['description'];
        $new_cat = $entry['category'];

        // Extract user ID from description pattern: "user ID X" or "user ID: X" etc.
        if (preg_match('/user ID\s*[:]?\s*(\d+)/i', $entry['description'], $matches)) {
            $user_id = (int)$matches[1];
            // Fetch user name and email
            $user_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
            if ($user) {
                // Replace the old description with new one containing name and email
                $new_desc = preg_replace('/user ID\s*[:]?\s*\d+/i', $user['name'] . ' (' . $user['email'] . ')', $entry['description']);
            }
        }

        // Update category to 'Auction Subscription' if it's 'Subscription'
        if ($entry['category'] == 'Subscription') {
            $new_cat = 'Auction Subscription';
        }

        // Update only if changed
        if ($new_desc != $entry['description'] || $new_cat != $entry['category']) {
            $stmt = $pdo->prepare("UPDATE account_entries SET description = ?, category = ? WHERE id = ?");
            $stmt->execute([$new_desc, $new_cat, $entry['id']]);
            $updated++;
        }
    }

    echo "✅ Updated $updated entries.<br>";

    // Show a sample of updated entries
    $sample = $pdo->query("SELECT id, description, category FROM account_entries WHERE category = 'Auction Subscription' ORDER BY id DESC LIMIT 5")->fetchAll();
    echo "<h4>Sample Updated Entries:</h4>";
    echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>Description</th><th>Category</th></tr>";
    foreach ($sample as $s) {
        echo "<tr><td>{$s['id']}</td><td>{$s['description']}</td><td>{$s['category']}</td></tr>";
    }
    echo "</table>";

    echo "<hr><p style='color:red; font-weight:bold;'>⚠️ Delete this file immediately after running.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
