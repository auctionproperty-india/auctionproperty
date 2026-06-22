<?php
require_once 'db.php';

try {
    // Get the maximum id from properties
    $max_id = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM properties")->fetchColumn();
    $new_seq = $max_id + 1;

    // Set the sequence to the new value
    $pdo->exec("ALTER SEQUENCE properties_id_seq RESTART WITH $new_seq");

    echo "✅ Properties sequence reset to $new_seq<br>";
    echo "Now you can add new properties without duplicate key errors.<br>";
    echo "<a href='properties.php'>Go to Properties</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
