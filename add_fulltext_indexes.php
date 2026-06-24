<?php
require_once 'db.php';

try {
    // Enable pg_trgm extension (if not already)
    $pdo->exec("CREATE EXTENSION IF NOT EXISTS pg_trgm");

    // Create GIN indexes for text search
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_title_trgm ON properties USING gin (title gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_city_trgm ON properties USING gin (city gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_bank_trgm ON properties USING gin (bank_name gin_trgm_ops)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_location_trgm ON properties USING gin (location gin_trgm_ops)");

    echo "✅ Full-text search indexes created!<br>";
    echo "Now 'ILIKE' queries will be much faster.<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "If error says 'pg_trgm' not found, don't worry – regular indexes are still working.<br>";
}
?>
