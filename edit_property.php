<?php 
require_once 'db.php'; 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
$id = $_GET['id'] ?? 0;
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $image_path = $_POST['existing_image'];
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
    // ✅ Update में भी description = '' डाला
    $sql = "UPDATE properties SET 
        title=?, description='', price=?, location=?, city=?, type=?, google_location=?, image_url=?, 
        bank_name=?, sqft=?, possession_type=?, auction_date=?, 
        borrower_name=?, emd_amount=?, bid_increment=?, emd_deadline=?, 
        auction_start_time=?, auction_end_time=?, locality=?, reserve_price_per_sqft=?, contact_number=? 
        WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $image_path,
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $auction_date_db,
        $_POST['borrower_name'], $_POST['emd_amount'], $_POST['bid_increment'], $_POST['emd_deadline'],
        $_POST['auction_start_time'], $_POST['auction_end_time'], $_POST['locality'], 
        $_POST['reserve_price_per_sqft'], $_POST['contact_number'], $id
    ]);
    header("Location: properties.php?updated=1");
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$id]);
$prop = $stmt->fetch();
if(!$prop) die("Property not found");
include 'header.php'; 
$display_date = '';
if(!empty($prop['auction_date'])) {
    $date_obj = DateTime::createFromFormat('Y-m-d', $prop['auction_date']);
    if($date_obj) $display_date = $date_obj->format('d/m/Y');
}
?>
<div class="card-premium">
    <h4>✏️ Edit Property #<?= $id ?></h4>
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6"><label>Title</label><input name="title" value="<?= htmlspecialchars($prop['title']) ?>" class="form-control" required></div>
            <div class="col-md-6"><label>Address</label>
                <input type="text" name="location" id="location_input" value="<?= htmlspecialchars($prop['location']) ?>" class="form-control" required>
            </div>
            <div class="col-md-3"><label>City</label><input name="city" value="<?= htmlspecialchars($prop['city'] ?? '') ?>" class="form-control"></div>
            <div class="col-md-3"><label>Locality</label><input name="locality" value="<?= htmlspecialchars($prop['locality'] ?? '') ?>" class="form-control"></div>
            <div class="col-md-3"><label>Reserve Price</label><input name="price" value="<?= $prop['price'] ?>" class="form-control" required></div>
            <div class="col-md-3"><label>Price/Sq Ft</label><input name="reserve_price_per_sqft" value="<?= $prop['reserve_price_per_sqft'] ?? '' ?>" class="form-control"></div>
            <div class="col-md-4"><label>Bank Name</label><input name="bank_name" value="<?= htmlspecialchars($prop['bank_name'] ?? '') ?>" class="form-control"></div>
            <div class="col-md-4"><label>Borrower Name</label><input name="borrower_name" value="<?= htmlspecialchars($prop['borrower_name'] ?? '') ?>" class="form-control"></div>
            <div class="col-md-4"><label>Property Type</label>
                <select name="type" class="form-control">
                    <option value="Flat" <?= ($prop['type']=='Flat')?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($prop['type']=='Plot')?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($prop['type']=='Shop')?'selected':'' ?>>Shop</option>
                    <option value="Land" <?= ($prop['type']=='Land')?'selected':'' ?>>Land</option>
                    <option value="Row House" <?= ($prop['type']=='Row House')?'selected':'' ?>>Row House</option>
                    <option value="Bungalow" <?= ($prop['type']=='Bungalow')?'selected':'' ?>>Bungalow</option>
                </select>
            </div>
            <div class="col-md-3"><label>Area (Sq Ft)</label><input name="sqft" value="<?= $prop['sqft'] ?? '' ?>" class="form-control"></div>
            <div class="col-md-3"><label>Possession</label>
                <select name="possession_type" class="form-control">
                    <option value="Physical" <?= ($prop['possession_type']=='Physical')?'selected':'' ?>>Physical</option>
                    <option value="Symbolic" <?= ($prop['possession_type']=='Symbolic')?'selected':'' ?>>Symbolic</option>
                </select>
            </div>
            <div class="col-md-3"><label>EMD Amount</label><input name="emd_amount" value="<?= $prop['emd_amount'] ?? '' ?>" class="form-control"></div>
            <div class="col-md-3"><label>Bid Increment</label><input name="bid_increment" value="<?= $prop['bid_increment'] ?? '' ?>" class="form-control"></div>
            <div class="col-md-4"><label>Auction Start</label><input name="auction_start_time" value="<?= htmlspecialchars($prop['auction_start_time'] ?? '') ?>" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM"></div>
            <div class="col-md-4"><label>Auction End</label><input name="auction_end_time" value="<?= htmlspecialchars($prop['auction_end_time'] ?? '') ?>" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>
            <div class="col-md-4"><label>EMD Deadline</label><input name="emd_deadline" value="<?= htmlspecialchars($prop['emd_deadline'] ?? '') ?>" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>
            <div class="col-md-6"><label>Auction Date (DD/MM/YYYY)</label>
                <input type="text" name="auction_date" class="form-control" placeholder="e.g. 24/06/2026" value="<?= $display_date ?>">
            </div>
            <div class="col-md-6"><label>Contact Number</label><input name="contact_number" value="<?= htmlspecialchars($prop['contact_number'] ?? '') ?>" class="form-control" required></div>
            <div class="col-12"><label>Google Map Link</label>
                <input type="text" name="google_location" id="google_location" value="<?= htmlspecialchars($prop['google_location'] ?? '') ?>" class="form-control" readonly style="background:#f1f5f9;">
            </div>
            <div class="col-12">
                <label>Current Image</label><br>
                <?php if(!empty($prop['image_url']) && file_exists($prop['image_url'])): ?>
                    <img src="<?= $prop['image_url'] ?>" style="max-height:150px; border-radius:10px; margin-bottom:10px;">
                <?php else: ?>
                    <p class="text-muted">No image uploaded.</p>
                <?php endif; ?>
                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($prop['image_url'] ?? '') ?>">
                <input type="file" name="image_file" class="form-control" accept="image/*">
                <small>Leave empty to keep current image.</small>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-success">Update Property</button> <a href="properties.php" class="btn btn-secondary">Cancel</a></div>
        </div>
    </form>
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
