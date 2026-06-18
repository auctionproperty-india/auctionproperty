<?php 
// सबसे पहले DB कनेक्शन लोड करें
require_once 'db.php'; 

// अभी तक Properties की टेबल नहीं है, तो चलो पहले Database में टेबल बनाते हैं (सिर्फ एक बार)
// यह कोड अपने आप चलेगा और टेबल बना देगा अगर नहीं है
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS properties (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        location VARCHAR(255),
        image_url TEXT,
        status VARCHAR(20) DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password TEXT NOT NULL,
        phone VARCHAR(15),
        referral_code VARCHAR(20) UNIQUE NOT NULL,
        is_admin BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // अगर टेबल बनाने में कोई दिक्कत हो (जैसे पहले से है तो कोई बात नहीं)
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏠 PropertyDeal - Home</title>
    <!-- Bootstrap CSS (स्टाइल के लिए) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">🏠 PropertyDeal</a>
            <div class="ms-auto">
                <!-- अभी सिर्फ डमी बटन हैं, आगे बदलेंगे -->
                php
<a href="login.php" class="btn btn-outline-light me-2">Login</a>
<a href="register.php" class="btn btn-primary">Register</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="alert alert-success text-center">
            <h4>✅ आपका Render Server सफलतापूर्वक चल रहा है!</h4>
            <p>Database Connection: <strong>सफल (Connected)</strong></p>
        </div>

        <h2 class="text-center mb-4">Available Properties</h2>
        
        <div class="row">
            <?php
            // अभी डेटा बेस में कोई Property नहीं है, इसलिए कोई लिस्ट नहीं दिखेगी
            // लेकिन हम यह चेक करेंगे कि क्वेरी चल रही है या नहीं
            try {
                $stmt = $pdo->query("SELECT * FROM properties WHERE status = 'available' ORDER BY id DESC");
                $properties = $stmt->fetchAll();
                
                if(count($properties) > 0) {
                    foreach($properties as $prop) { ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 shadow">
                                <div class="card-body">
                                    <h5><?= htmlspecialchars($prop['title']) ?></h5>
                                    <p>₹ <?= number_format($prop['price'], 2) ?></p>
                                    <button class="btn btn-success w-100" disabled>Buy Now (Coming Soon)</button>
                                </div>
                            </div>
                        </div>
                    <?php }
                } else {
                    echo '<div class="col-12 text-center"><p class="text-muted">📭 अभी कोई Property नहीं है। Admin पैनल से जोड़ेंगे।</p></div>';
                }
            } catch (Exception $e) {
                echo '<div class="col-12 text-center"><p class="text-danger">⚠️ टेबल क्वेरी में समस्या: ' . $e->getMessage() . '</p></div>';
            }
            ?>
        </div>
        
        <hr>
        <p class="text-center text-muted">🔧 Step 1: Home Page & Database Connection - सफलतापूर्वक चल रहा है!</p>
    </div>
</body>
</html>
