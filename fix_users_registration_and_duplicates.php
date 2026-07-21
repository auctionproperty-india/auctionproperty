<?php
// ============================================================
// 🔧 Fix Users: Set Registration Date & Remove Duplicates
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Only admin can run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Users</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Fix Users – Registration Date & Duplicates</h1>";

try {
    $pdo->beginTransaction();

    // ============================================================
    // 1. Set registration date for users with NULL created_at
    // ============================================================
    echo "<h2>📅 Step 1: Setting Registration Date</h2>";
    $stmt = $pdo->prepare("UPDATE users SET created_at = NOW() WHERE created_at IS NULL");
    $stmt->execute();
    $updated_count = $stmt->rowCount();
    echo "<div class='success'>✅ Updated $updated_count users with NULL registration date to today.</div>";

    // ============================================================
    // 2. Find and remove duplicate users (same email)
    // ============================================================
    echo "<h2>🧹 Step 2: Removing Duplicate Users</h2>";
    
    // Get all emails that have more than one user
    $duplicate_emails = $pdo->query("
        SELECT email, COUNT(*) as cnt, MIN(id) as keep_id
        FROM users
        WHERE email IS NOT NULL AND email != ''
        GROUP BY email
        HAVING COUNT(*) > 1
    ")->fetchAll();

    if (empty($duplicate_emails)) {
        echo "<div class='success'>✅ No duplicate emails found.</div>";
    } else {
        $total_deleted = 0;
        $skipped = 0;
        echo "<table>";
        echo "<tr><th>Email</th><th>Keep ID</th><th>Deleted IDs</th><th>Status</th></tr>";
        
        foreach ($duplicate_emails as $dup) {
            $email = $dup['email'];
            $keep_id = $dup['keep_id'];
            
            // Get all ids for this email except the keep_id
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $keep_id]);
            $delete_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($delete_ids)) continue;
            
            $delete_list = implode(',', $delete_ids);
            $deleted_count = 0;
            $skipped_count = 0;
            
            foreach ($delete_ids as $del_id) {
                // Check if this user has any active subscription
                $sub_check = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ? AND status = 'active'");
                $sub_check->execute([$del_id]);
                $has_active = $sub_check->fetchColumn();
                
                // Also check if user has any referral earnings
                $ref_check = $pdo->prepare("SELECT COUNT(*) FROM user_referral_earnings WHERE user_id = ?");
                $ref_check->execute([$del_id]);
                $has_referrals = $ref_check->fetchColumn();
                
                if ($has_active > 0 || $has_referrals > 0) {
                    // Skip deletion to avoid data loss
                    $skipped_count++;
                    continue;
                }
                
                // Delete the user
                $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $del_stmt->execute([$del_id]);
                $deleted_count++;
            }
            
            $total_deleted += $deleted_count;
            $skipped += $skipped_count;
            
            $status = "Deleted $deleted_count, Skipped $skipped_count";
            echo "<tr><td>" . htmlspecialchars($email) . "</td><td>$keep_id</td><td>$delete_list</td><td>$status</td></tr>";
        }
        echo "</table>";
        echo "<div class='success'>✅ Deleted $total_deleted duplicate users. Skipped $skipped (have active subscription/referrals).</div>";
    }

    // ============================================================
    // 3. Reset sequences to avoid duplicate key errors
    // ============================================================
    echo "<h2>🔄 Step 3: Resetting Sequences</h2>";
    $tables = ['users'];
    foreach ($tables as $table) {
        $seq = $pdo->query("SELECT pg_get_serial_sequence('$table', 'id')")->fetchColumn();
        if ($seq) {
            $max = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM $table")->fetchColumn();
            $next = $max + 1;
            $pdo->exec("SELECT setval('$seq', $next, false)");
            echo "<div class='info'>✅ $seq set to $next</div>";
        }
    }

    $pdo->commit();
    echo "<div class='success'>🎉 All fixes applied successfully!</div>";
    echo "<div class='info'>🔗 <a href='admin_dashboard.php'>Go to Admin Dashboard</a></div>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
