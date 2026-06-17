<?php
session_start();
// अगर यूजर पहले से लॉगइन है, तो हम उसे चेक कर सकते हैं
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Property India // Premium Real Estate Portal</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --border: #e2e8f0;
        }
        
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light);
            color: var(--dark);
        }

        /* 🌍 NAVBAR DESIGN */
        .navbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .logo {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            letter-spacing: -0.5px;
        }
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            font-size: 15px;
            transition: color 0.3s;
        }
        .nav-links a:hover {
            color: var(--primary);
        }
        .btn-auth {
            background: var(--primary);
            color: white !important;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background 0.3s !important;
        }
        .btn-auth:hover {
            background: var(--primary-hover);
        }

        /* 🚀 HERO SECTION */
        .hero {
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            color: white;
            padding: 80px 40px;
            text-align: center;
        }
        .hero h1 {
            font-size: 42px;
            font-weight: 800;
            margin: 0 0 15px 0;
            letter-spacing: -1px;
        }
        .hero p {
            font-size: 18px;
            color: #94a3b8;
            max-width: 600px;
            margin: 0 auto 30px auto;
        }
        .hero-btns {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .btn-hero {
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.3s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }

        /* 🏡 PROPERTY SECTION SECTION */
        .section-container {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .section-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
            text-align: center;
        }
        .section-subtitle {
            color: var(--gray);
            font-size: 15px;
            text-align: center;
            margin-bottom: 40px;
        }
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .property-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .property-card:hover {
            transform: translateY(-5px);
        }
        .property-img-placeholder {
            background: #e2e8f0;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-weight: bold;
            font-size: 14px;
        }
        .property-content {
            padding: 20px;
        }
        .property-price {
            color: var(--primary);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .property-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 10px 0;
        }
        .property-desc {
            color: var(--gray);
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .btn-details {
            display: block;
            text-align: center;
            background: #f1f5f9;
            color: var(--dark);
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-details:hover {
            background: #e2e8f0;
        }

        /* 🔏 FOOTER */
        footer {
            background: var(--dark);
            color: #94a3b8;
            text-align: center;
            padding: 30px;
            font-size: 14px;
            margin-top: 60px;
            border-top: 1px solid #1e293b;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">🏢 Prime Property India</a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="#properties">Properties</a>
            
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php">📊 My Dashboard</a>
                <a href="logout.php" style="color: #ef4444;">Logout</a>
            <?php else: ?>
                <a href="login.php">Sign In</a>
                <a href="register.php" class="btn-auth">Register Now</a>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero">
        <h1>Find Your Premier Property Node</h1>
        <p>बिना किसी परेशानी के डायरेक्ट कॉर्पोरेट नेटवर्क से जुड़ें, सिक्योर इन्वेस्टमेंट और बेहतरीन प्रॉपर्टीज का पूरा डेटाबेस यहाँ उपलब्ध है।</p>
        <div class="hero-btns">
            <a href="#properties" class="btn-hero btn-primary">Browse Properties</a>
            <?php if (!$is_logged_in): ?>
                <a href="register.php" class="btn-hero btn-secondary">Create Free Account</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="section-container" id="properties">
        <h2 class="section-title">Featured Property Listings</h2>
        <p class="section-subtitle">प्रत्येक लिस्टिंग की पूरी गहराई और लीगल वेरिफिकेशन की डिटेल्स देखने के लिए नीचे दिए ऑप्शंस चुनें।</p>

        <div class="property-grid">
            <div class="property-card">
                <div class="property-img-placeholder">🏢 Commercial Shop Image Placeholder</div>
                <div class="property-content">
                    <div class="property-price">₹ 25,00,000</div>
                    <h3 class="property-title">Premium Commercial Shop</h3>
                    <p class="property-desc">Tejaji Nagar Bypass Road, Indore. 150 Sq.Ft area, perfect for retail and business hubs.</p>
                    <a href="property_details.php?id=1" class="btn-details">🔍 View Full Details</a>
                </div>
            </div>

            <div class="property-card">
                <div class="property-img-placeholder">🏗️ Development Plot Image Placeholder</div>
                <div class="property-content">
                    <div class="property-price">₹ 45,00,000</div>
                    <h3 class="property-title">Residential Investment Plot</h3>
                    <p class="property-desc">Khandwa Road Corridor, Indore. RERA approved colony project with complete infrastructure setup.</p>
                    <a href="property_details.php?id=2" class="btn-details">🔍 View Full Details</a>
                </div>
            </div>

            <div class="property-card">
                <div class="property-img-placeholder">🌇 Smart City Zone Image Placeholder</div>
                <div class="property-content">
                    <div class="property-price">₹ 12,50,000</div>
                    <h3 class="property-title">Dholera SIR Industrial Plot</h3>
                    <p class="property-desc">High-growth Special Investment Region. Strategic location with futuristic tech connectivity.</p>
                    <a href="property_details.php?id=3" class="btn-details">🔍 View Full Details</a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        &copy; <?php echo date('Y'); ?> Prime Property India. All Secure Rights Reserved // Ecosystem Infrastructure.
    </footer>

</body>
</html>
