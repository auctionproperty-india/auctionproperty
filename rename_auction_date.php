<?php
require_once 'db.php';

try {
    // Rename auction_date to inspection_date
    $pdo->exec("ALTER TABLE properties RENAME COLUMN auction_date TO inspection_date");
    echo "✅ Column 'auction_date' renamed to 'inspection_date' successfully!<br>";
    echo "Now existing auction_date values are in inspection_date.<br>";
    echo "<a href='properties.php' class='btn btn-primary'>Go to Properties</a>";
} catch (Exception $e) {
    // अगर कॉलम पहले से ही inspection_date है तो Error आ सकता है, लेकिन ignore करें
    if (strpos($e->getMessage(), 'does not exist') === false) {
        echo "❌ Error: " . $e->getMessage();
    } else {
        echo "ℹ️ Column 'auction_date' not found (already renamed or not exists).<br>";
        echo "You can add inspection_date manually if needed.<br>";
    }
}
?>
