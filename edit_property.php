<?php 
require_once 'db.php'; 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$id = $_GET['id'] ?? 0;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sql = "UPDATE properties SET 
        title=?, description=?, price=?, location=?, city=?, type=?, google_location=?, image_url=?, 
        bank_name=?, sqft=?, possession_type=?, auction_date=?, 
        borrower_name=?, emd_amount=?, bid_increment=?, emd_deadline=?, 
        auction_start_time=?, auction_end_time=?, locality=?, reserve_price_per_sqft=?, contact_number=? 
        WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['description'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $_POST['image_url'],
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $_POST['auction_date'],
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
?>

<div class="card-premium">
    <h4>✏️ Edit Property #<?= $id ?></h4>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6"><label>Title</label><input name="title" value="<?= htmlspecialchars($prop['title']) ?>" class="form-control" required></div>
            <div class="col-md-6"><label>Address</label><input name="location" value="<?= htmlspecialchars($prop['location']) ?>" class="form-control" required></div>
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

            <div class="col-md-6"><label>Contact Number</label><input name="contact_number" value="<?= htmlspecialchars($prop['contact_number'] ?? '') ?>" class="form-control" required></div>
            <div class="col-md-6"><label>Auction Date</label><input type="date" name="auction_date" value="<?= $prop['auction_date'] ?? '' ?>" class="form-control"></div>

            <div class="col-12"><label>Description</label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($prop['description']) ?></textarea></div>
            <div class="col-6"><label>Image URL</label><input name="image_url" value="<?= htmlspecialchars($prop['image_url']) ?>" class="form-control"></div>
            <div class="col-6"><label>Google Map</label><input name="google_location" value="<?= htmlspecialchars($prop['google_location'] ?? '') ?>" class="form-control"></div>
            
            <div class="col-12"><button type="submit" class="btn btn-success">Update Property</button> <a href="properties.php" class="btn btn-secondary">Cancel</a></div>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
