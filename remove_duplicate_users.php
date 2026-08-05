<?php
// ============================================================
// 🧹 Remove Duplicate Users – Keep First ID
// ============================================================

require_once __DIR__ . '/db.php';

// Find duplicate emails and keep the first one (lowest id)
$duplicates = $pdo->query("
    SELECT email, MIN(id) as keep_id
    FROM users
    GROUP BY email
    HAVING COUNT(*) > 1
")->fetchAll();

if (empty($duplicates)) {
    echo "✅ No duplicate emails found.<br>";
} else {
    $total_deleted = 0;
    foreach ($duplicates as $dup) {
        $email = $dup['email'];
        $keep = $dup['keep_id'];
        // Delete all other users with same email
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $keep]);
        $deleted = $stmt->rowCount();
        $total_deleted += $deleted;
        echo "✅ Kept user $keep, removed $deleted duplicates for $email<br>";
    }
    echo "🎉 Total duplicates removed: $total_deleted<br>";
}
echo "🎉 Done. You can now run add_unique_email_constraint.php";
?>
