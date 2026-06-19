<?php 
require_once 'db.php'; 
require_once 'functions.php'; // ✅ नया Function Include करें

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

// ---- ADD PROPERTY ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $image_path = '';
    // अगर User ने Manual Image Upload की है तो उसे Use करें, वरना Social Card Generate करें
    $use_uploaded_image = false;
    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename);
        $image_path = $upload_dir . $filename;
        $use_uploaded_image = true;
    }

    // Auction Date Convert
    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }

    $sql = "INSERT INTO properties (
        title, description, price, location, city, type, google_location, image_url, 
        bank_name, sqft, possession_type, auction_date, 
        borrower_name, emd_amount, bid_increment, emd_deadline, 
        auction_start_time, auction_end_time, locality, reserve_price_per_sqft, contact_number
    ) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $image_path, // Image Path (maybe blank)
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $auction_date_db,
        $_POST['borrower_name'], $_POST['emd_amount'], $_POST['bid_increment'], $_POST['emd_deadline'],
        $_POST['auction_start_time'], $_POST['auction_end_time'], $_POST['locality'], 
        $_POST['reserve_price_per_sqft'], $_POST['contact_number']
    ]);
    
    $new_id = $pdo->lastInsertId();

    // ✅ अगर User ने Image Upload नहीं की है, तो Social Card Generate करें
    if (!$use_uploaded_image) {
        // Fetch the newly inserted property
        $new_prop = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
        $new_prop->execute([$new_id]);
        $prop_data = $new_prop->fetch();
        
        // Generate Social Image
        $generated_path = generateSocialCard($prop_data);
        if ($generated_path) {
            // Update the record with the generated image path
            $pdo->prepare("UPDATE properties SET image_url = ? WHERE id = ?")->execute([$generated_path, $new_id]);
        }
    }

    header("Location: properties.php?added=1#add-form");
    exit;
}

// ---- EDIT PROPERTY ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_property'])) {
    $id = $_POST['property_id'];
    $image_path = $_POST['existing_image'];
    $use_uploaded_image = false;

    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename);
        $image_path = $upload_dir . $filename;
        $use_uploaded_image = true;
    }

    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }

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

    // ✅ अगर Edit में User ने Image नहीं बदली, तो Social Card फिर से Generate करें (ताकि नई Details के साथ Update हो)
    if (!$use_uploaded_image) {
        $updated_prop = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
        $updated_prop->execute([$id]);
        $prop_data = $updated_prop->fetch();
        
        $generated_path = generateSocialCard($prop_data);
        if ($generated_path) {
            $pdo->prepare("UPDATE properties SET image_url = ? WHERE id = ?")->execute([$generated_path, $id]);
        }
    }

    header("Location: properties.php?updated=1");
    exit;
}

// ---- RENDER FORM (GET) ----
include 'header.php'; 
?>
<!-- HTML FORM (Same as before, just make sure to include `enctype="multipart/form-data"`) -->
<div class="card-premium" id="add-form">
    <h4>Add/Edit Property</h4>
    <form method="POST" enctype="multipart/form-data">
        <!-- ... your form fields ... -->
        <!-- Just add a hidden input for update -->
        <input type="hidden" name="property_id" value="<?= $_GET['edit'] ?? 0 ?>">
        <button type="submit" name="<?= isset($_GET['edit']) ? 'update_property' : 'add_property' ?>" class="btn btn-primary">Save</button>
    </form>
</div>
<!-- Rest of the list table... -->
<?php include 'footer.php'; ?>
