<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

try {
    // यह कोड अपने आप डेटाबेस में चेक करेगा और 'status' कॉलम जोड़ देगा
    $sql = "ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'";
    $conn->exec($sql);
    
    echo "<div style='color: green; font-family: sans-serif; padding: 20px; font-weight: bold;'>
            ✓ SUCCESS: Database updated successfully! 'status' column has been added to the users table.<br>
            अब आप इस फाइल को गिटहब से डिलीट कर सकते हैं।
          </div>";
} catch(PDOException $e) {
    // अगर कॉलम पहले से होगा या कोई और एरर होगी तो यहाँ दिखेगी
    echo "<div style='color: red; font-family: sans-serif; padding: 20px; font-weight: bold;'>
            ⚠️ INFO/ERROR: " . $e->getMessage() . "<br>
            (अगर संदेश में 'Duplicate column name' लिखा है, तो इसका मतलब कॉलम पहले से जुड़ चुका है और सब ठीक है!)
          </div>";
}
?>
