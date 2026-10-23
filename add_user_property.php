<?php
// ============================================================
// 📝 Add User Property – Sale OR Exchange (Supabase Upload)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

function containsMobileNumber($text) {
    return preg_match('/[6-9][0-9]{9}/', $text);
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $listing_type = $_POST['listing_type'] ?? 'sale';
    if (!in_array($listing_type, ['sale','exchange'])) $listing_type = 'sale';

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $type = trim($_POST['type']);
    $sqft = (float)($_POST['sqft'] ?? 0);
    $construction_sqft = (float)($_POST['construction_sqft'] ?? 0);
    
    // Exchange fields
    $want_type = trim($_POST['want_type'] ?? '');
    $want_budget = (float)($_POST['want_budget'] ?? 0);
    $want_city = trim($_POST['want_city'] ?? '');
    $want_state = trim($_POST['want_state'] ?? '');
    $want_locality = trim($_POST['want_locality'] ?? '');

    $image_url = '';

    if(empty($title) || $price <= 0) {
        $error = "❌ Title and Price are required.";
    } else {
        $has_mobile = containsMobileNumber($description);
        $status = $has_mobile ? 'rejected' : 'approved';
        $admin_remarks = $has_mobile ? 'Mobile number not allowed in description.' : null;

        // ☁️ Supabase Upload Code
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $result = uploadAnyFile($_FILES['image'], 'user_props');
            if (isset($result['url'])) {
                $image_url = $result['url'];
            } else {
                $error = "❌ " . ($result['error'] ?? 'Image upload failed.');
            }
        }

        if(empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO user_properties 
                (user_id, title, description, price, city, state, type, sqft, construction_sqft, 
                 image_url, status, admin_remarks, listing_type, want_type, want_budget, want_city, want_state, want_locality, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $user_id, $title, $description, $price, $city, $state, $type, $sqft, $construction_sqft, 
                $image_url, $status, $admin_remarks, $listing_type, $want_type, $want_budget, $want_city, $want_state, $want_locality
            ]);
            
            if ($status == 'approved') {
                header("Location: user_properties.php?msg=auto_approved");
            } else {
                header("Location: user_properties.php?msg=auto_rejected");
            }
            exit;
        }
    }
}

include 'header.php'; 
?>
<div class="container-fluid">
    <h4><i class="fas fa-plus-circle me-2"></i>Add Your Property</h4>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    
    <div class="card p-4 shadow-sm" style="border-radius: 16px;">
        <form method="POST" enctype="multipart/form-data">
            
            <!-- 🔥 Compact Listing Type Dropdown -->
            <div class="mb-4 p-3" style="background:#f8fafc; border-radius:12px; border: 1px solid #e2e8f0;">
                <label class="form-label fw-bold text-dark mb-2">मैं क्या करना चाहता हूँ? *</label>
                <select name="listing_type" id="listing_type" class="form-select form-select-lg" onchange="toggleExchange(this.value)" style="border-radius: 10px; font-weight: 600; color: #1e3a8a; cursor: pointer;">
                    <option value="sale" selected>🏷️ Sale (सिर्फ बेचना है)</option>
                    <option value="exchange">🔄 Exchange (बदलना है - All India)</option>
                </select>
            </div>

            <!-- My Property (Give) -->
            <h6 class="text-primary mb-3 fw-bold"><i class="fas fa-arrow-up"></i> प्रॉपर्टी की जानकारी (जो आप देंगे)</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="जैसे: 3BHK Flat in Mumbai">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" required placeholder="e.g. 5000000">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="प्रॉपर्टी के बारे में बताएं..."></textarea>
                    <small class="text-danger">⚠️ Mobile numbers are not allowed in description.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="Flat">Flat</option>
                        <option value="Plot">Plot</option>
                        <option value="Shop">Shop</option>
                        <option value="Land">Land</option>
                        <option value="House">House</option>
                        <option value="Row House">Row House</option>
                        <option value="Bungalow">Bungalow</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Area (Sq Ft)</label>
                    <input type="number" step="0.01" name="sqft" class="form-control" placeholder="e.g. 1200">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Construction Area (Sq Ft)</label>
                    <input type="number" step="0.01" name="construction_sqft" class="form-control" placeholder="e.g. 800">
                </div>
                <div class="col-md-12 mt-3">
                    <label class="form-label fw-bold">Upload Image / PDF</label>
                    <input type="file" name="image" class="form-control" accept="image/*, .pdf">
                    <small class="text-muted">Max file size: 10MB.</small>
                </div>
            </div>

            <!-- 🔥 Exchange Section (Hidden by Default) -->
            <div id="exchangeSection" style="display:none; background: #eff6ff; border: 2px dashed #93c5fd; border-radius: 12px; padding: 20px; margin-top: 25px;">
                <h6 class="text-success mb-3 fw-bold"><i class="fas fa-arrow-down"></i> मैं क्या लेना चाहता हूँ (All India Exchange)</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">क्या लेना चाहता हूँ? (Type)</label>
                        <select name="want_type" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="Flat">Flat</option>
                            <option value="Plot">Plot</option>
                            <option value="Shop">Shop</option>
                            <option value="Land">Land</option>
                            <option value="House">House</option>
                            <option value="Row House">Row House</option>
                            <option value="Bungalow">Bungalow</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">किस Budget में लेना चाहता हूँ? (₹)</label>
                        <input type="number" step="0.01" name="want_budget" class="form-control" placeholder="e.g. 5000000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">कहाँ लेना चाहता हूँ — City (All India)</label>
                        <input type="text" name="want_city" class="form-control" placeholder="कोई भी शहर">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">State</label>
                        <input type="text" name="want_state" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Locality / Area</label>
                        <input type="text" name="want_locality" class="form-control">
                    </div>
                </div>
            </div>

            <div class="mt-4 border-top pt-3">
                <button type="submit" name="add_property" class="btn btn-primary px-5 py-2" style="border-radius: 10px;">
                    <i class="fas fa-paper-plane me-1"></i> Submit Property
                </button>
                <a href="user_properties.php" class="btn btn-secondary px-4 py-2 ms-2" style="border-radius: 10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Dropdown change hone par Exchange section toggle karne ka function
function toggleExchange(val) {
    var sec = document.getElementById('exchangeSection');
    if (val === 'exchange') {
        sec.style.display = 'block';
        // Smooth scroll to exchange section
        sec.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        sec.style.display = 'none';
    }
}
</script>
<?php include 'footer.php'; ?>
