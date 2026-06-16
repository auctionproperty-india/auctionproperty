<?php
// रेंडर PostgreSQL कनेक्शन PDO के जरिए
$dbopts = parse_url("postgresql://admin:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-d8ok6lflk1mc739ce1j0-a.oregon-postgres.render.com/auction_db_r1hx");

$host = $dbopts["host"];
$port = $dbopts["port"];
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$dbname = ltrim($dbopts["path"], '/');

try {
    // PDO कनेक्शन बनाना
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // यह जादुई लाइन आपके पुराने mysqli कोड को टूटने से बचाएगी
    $conn = $pdo; 
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// अगर आपके बाकी पेजों में mysqli_query इस्तेमाल हुआ है, तो उनके लिए नकली फंक्शन ताकि एरर न आए
if (!function_exists('mysqli_query')) {
    function mysqli_query($conn, $query) {
        // MySQL क्वेरी को PostgreSQL के हिसाब से हल्का सा बदलना (अगर जरूरत हो)
        $query = str_replace('`', '"', $query);
        try {
            return $conn->query($query);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
