<?php
// रेंडर PostgreSQL डेटाबेस कनेक्शन
$connection_string = "postgresql://admin:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-d8ok6lflk1mc739ce1j0-a.oregon-postgres.render.com/auction_db_r1hx";

$conn = pg_connect($connection_string);

if (!$conn) {
    die("Database Connection Failed: " . pg_last_error());
}
?>
