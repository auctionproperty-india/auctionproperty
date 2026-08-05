<?php
// ============================================================
// 🔒 Add Unique Constraint on users.email
// ============================================================

require_once __DIR__ . '/db.php';

try {
    // Check if constraint already exists
    $stmt = $pdo->query("
        SELECT constraint_name 
        FROM information_schema.table_constraints 
        WHERE table_name = 'users' AND constraint_type = 'UNIQUE' AND constraint_name LIKE '%email%'
    ");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "✅ Unique constraint on email already exists.<br>";
    } else {
        // Add unique constraint
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT unique_email UNIQUE (email)");
        echo "✅ Unique constraint added on email column.<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "If you already have duplicate emails, you need to remove them first.<br>";
    echo "Run remove_duplicate_users.php to clean duplicates, then retry this script.<br>";
}
?>
