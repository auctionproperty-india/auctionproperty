<?php 
require_once 'db.php'; 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $image_path = '';
    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename);
        $image_path = $upload_dir . $filename;
    }
    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }
    // ✅ SQL में description = '' (खाली) डाला है ताकि Error न आए
    $sql = "INSERT INTO properties (
        title, description, price, location, city, type, google_location, image_url, 
        bank_name, sqft, possession_type, auction_date, 
        borrower_name, emd_amount, bid_increment, emd_deadline, 
        auction_start_time, auction_end_time, locality, reserve_price_per_sqft, contact_number
    ) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $image_path,
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $auction_date_db,
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
    <div class="alert alert-success">✅ Property Added Successfully!</div>
<?php endif; ?>
<div class="card-premium" id="add-form" style="border-left: 4px solid #fbbf24;">
    <h5><i class="fas fa-plus-circle me-2" style="color: #fbbf24;"></i>Add Auction Property</h5>
    <form method="POST" class="mt-3" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6"><label>Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="col-md-6"><label>Address</label><input type="text" name="location" id="location_input" class="form-control" placeholder="e.g. Sapna Sangeeta, Indore" required></div>
            <div class="col-md-3"><label>City</label><input type="text" name="city" class="form-control" required></div>
            <div class="col-md-3"><label>Locality</label><input type="text" name="locality" class="form-control" placeholder="e.g. Sapna Sangeeta"></div>
            <div class="col-md-3"><label>Reserve Price (₹)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="col-md-3"><label>Price per Sq Ft</label><input type="number" step="0.01" name="reserve_price_per_sqft" class="form-control"></div>
            <div class="col-md-4"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" placeholder="e.g. Union Bank"></div>
            <div class="col-md-4"><label>Borrower Name</label><input type="text" name="borrower_name" class="form-control" placeholder="e.g. Alka Agarwal"></div>
            <div class="col-md-4"><label>Property Type</label>
                <select name="type" class="form-control">
                    <option value="Flat">Flat</option><option value="Plot">Plot</option>
                    <option value="Shop">Shop</option><option value="Land">Land</option>
                    <option value="Row House">Row House</option><option value="Bungalow">Bungalow</option>
                </select>
            </div>
            <div class="col-md-3"><label>Area (Sq Ft)</label><input type="number" step="0.01" name="sqft" class="form-control"></div>
            <div class="col-md-3"><label>Possession</label>
                <select name="possession_type" class="form-control">
                    <option value="Physical">Physical</option>
                    <option value="Symbolic">Symbolic</option>
                </select>
            </div>
            <div class="col-md-3"><label>EMD Amount (₹)</label><input type="number" step="0.01" name="emd_amount" class="form-control"></div>
            <div class="col-md-3"><label>Bid Increment (₹)</label><input type="number" step="0.01" name="bid_increment" class="form-control"></div>
            <div class="col-md-4"><label>Auction Start</label><input type="text" name="auction_start_time" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM"></div>
            <div class="col-md-4"><label>Auction End</label><input type="text" name="auction_end_time" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>
            <div class="col-md-4"><label>EMD Deadline</label><input type="text" name="emd_deadline" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>
            <div class="col-md-6"><label>Auction Date (DD/MM/YYYY)</label>
                <input type="text" name="auction_date" class="form-control" placeholder="e.g. 24/06/2026">
            </div>
            <div class="col-md-6"><label>Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($default_contact) ?>" required>
            </div>
            <div class="col-12"><label>Google Map Link</label>
                <input type="text" name="google_location" id="google_location" class="form-control" readonly style="background:#f1f5f9;">
            </div>
            <div class="col-12"><label>Upload Property Image</label>
                <input type="file" name="image_file" class="form-control" accept="image/*">
            </div>
            <div class="col-12"><button type="submit" name="add_property" class="btn btn-primary btn-lg w-100"><i class="fas fa-save me-2"></i>Add Property</button></div>
        </div>
    </form>
</div>
<div class="card-premium mt-4">
    <h5>📋 All Properties</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>ID</th><th>Title</th><th>Bank</th><th>City</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
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
<script>
    function initAutocomplete() {
        var input = document.getElementById('location_input');
        if (input) {
            var autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                if (place.geometry) {
                    var lat = place.geometry.location.lat();
                    var lng = place.geometry.location.lng();
                    document.getElementById('google_location').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
                }
            });
        }
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places&callback=initAutocomplete" async defer></script>
<?php include 'footer.php'; ?>
