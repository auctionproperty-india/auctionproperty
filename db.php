<?php
// Internal Connection URL (Render recommended)
$db_url = "postgres://auctionproperty_p917_user:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com:5432/auctionproperty_p917";

try {
    $conn = new PDO($db_url);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Agar yahan error aaye, toh samajh lo Database access issue hai
    die("Database Error: " . $e->getMessage());
}
?>
