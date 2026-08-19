<?php
require_once 'db.php';

echo "<h1>🔧 Convert to Private Treaty</h1>";

try {
    // उन 15 properties की list (जिन्हें हमने पहले date दी थी)
    $ids = [250, 262, 343, 500, 599, 671, 673, 676, 718, 736, 756, 776, 793, 799, 817];

    echo "<p>📋 Updating " . count($ids) . " properties to 'Private Treaty'...</p>";

    $pdo->beginTransaction();
    $updated = 0;

    foreach ($ids as $id) {
        $sql = "UPDATE properties SET auction_start_time = 'Private Treaty', auction_date = NULL WHERE id = $id";
        $pdo->exec($sql);
        $updated++;
        echo "<p style='color:green;'>✅ ID $id updated → Private Treaty</p>";
    }

    $pdo->commit();
    echo "<p style='color:green;'>✅ Successfully updated <strong>$updated</strong> properties.</p>";

    // Verify remaining NULL
    $stmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE auction_date IS NULL AND auction_start_time = 'Private Treaty'");
    $count = $stmt->fetchColumn();
    echo "<p>📊 Total Private Treaty properties now: <strong>$count</strong></p>";
    echo "<p><a href='/'>Go to Homepage</a></p>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
