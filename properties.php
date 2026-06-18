<?php 
require_once 'db.php'; 

// ---- सबसे पहले All Logic (Header से पहले) ----
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

// Default Contact Number fetch करें
$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

// Add Property Logic
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $sql = "INSERT INTO properties (
        title, description, price, location, city, type, google_location, image_url, 
        bank_name, sqft, possession_type, auction_date, 
        borrower_name, emd_amount, bid_increment, emd_deadline, 
        auction_start_time, auction_end_time, locality, reserve_price_per_sqft, contact_number
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['description'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $_POST['image_url'],
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $_POST['auction_date'],
        $_POST['borrower_name'], $_POST['emd_amount'], $_POST['bid_increment'], $_POST['emd_deadline'],
        $_POST['auction_start_time'], $_POST['auction_end_time'], $_POST['locality'], 
        $_POST['reserve_price_per_sqft'], $_POST['contact_number']
    ]);
    
    header("Location: properties.php?added=1#add-form");
    exit;
}

include 'header.php'; 
?>

<?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show">✅ Property Added Successfully!</div>
<?php endif; ?>

<!-- Add Property Form -->
<div class="card-premium" id="add-form" style="border-left: 4px solid #fbbf24;">
    <h5><i class="fas fa-plus-circle me-2" style="color: #fbbf24;"></i>Add Auction Property</h5>
    <form method="POST" class="mt-3">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Address</label><input type="text" name="location" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">City</label><input type="text" name="city" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Locality</label><input type="text" name="locality" class="form-control" placeholder="e.g. Sapna Sangeeta"></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Reserve Price (₹)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Price per Sq Ft</label><input type="number" step="0.01" name="reserve_price_per_sqft" class="form-control"></div>
            
            <div class="col-md-4"><label class="form-label fw-semibold">Bank Name</label><input type="text" name="bank_name" class="form-control" placeholder="e.g. Union Bank"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Borrower Name</label><input type="text" name="borrower_name" class="form-control" placeholder="e.g. Alka Agarwal"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Property Type</label>
                <select name="type" class="form-control">
                    <option value="Flat">Flat</option><option value="Plot">Plot</option>
                    <option value="Shop">Shop</option><option value="Land">Land</option>
                    <option value="Row House">Row House</option><option value="Bungalow">Bungalow</option>
                </select>
            </div>
            
            <div class="col-md-3"><label class="form-label fw-semibold">Area (Sq Ft)</label><input type="number" step="0.01" name="sqft" class="form-control"></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Possession</label>
                <select name="possession_type" class="form-control">
                    <option value="Physical">Physical</option>
                    <option value="Symbolic">Symbolic</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label fw-semibold">EMD Amount (₹)</label><input type="number" step="0.01" name="emd_amount" class="form-control" placeholder="e.g. 306500"></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Bid Increment (₹)</label><input type="number" step="0.01" name="bid_increment" class="form-control" placeholder="e.g. 30650"></div>

            <div class="col-md-4"><label class="form-label fw-semibold">Auction Start</label><input type="text" name="auction_start_time" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Auction End</label><input type="text" name="auction_end_time" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">EMD Deadline</label><input type="text" name="emd_deadline" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>

            <div class="col-md-6"><label class="form-label fw-semibold">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($default_contact) ?>" required>
                <small class="text-muted">Default from Settings.</small>
            </div>
            <div class="col-md-6"><label class="form-label fw-semibold">Auction Date</label><input type="date" name="auction_date" class="form-control"></div>

            <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Image URL</label><input type="text" name="image_url" class="form-control" placeholder="https://..."></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Google Map</label><input type="text" name="google_location" class="form-control" placeholder="https://maps.google.com/..."></div>
            
            <div class="col-12"><button type="submit" name="add_property" class="btn btn-primary btn-lg w-100"><i class="fas fa-save me-2"></i>Add Property</button></div>
        </div>
    </form>
</div>

<!-- Property List -->
<div class="card-premium mt-4">
    <h5>📋 All Properties</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light"><tr><th>ID</th><th>Title</th><th>Bank</th><th>City</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php 
            $stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC");
            while($row = $stmt->fetch()) { ?>
                <tr><td><?= $row['id'] ?></td><td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['bank_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['city'] ?? '') ?></td>
                <td>₹<?= number_format($row['price'], 2) ?></td>
                <td><span class="badge bg-<?= ($row['status']=='available')?'success':'secondary' ?>"><?= $row['status'] ?></span></td>
                <td>
                    <a href="edit_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                    <a href="delete_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑️</a>
                </td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
