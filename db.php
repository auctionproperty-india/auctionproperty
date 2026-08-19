// db.php – Parse DATABASE_URL to pgsql DSN
$url = getenv('DATABASE_URL');
if ($url) {
    $parts = parse_url($url);
    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'], '/'),
        $parts['user'],
        $parts['pass']
    );
    $pdo = new PDO($dsn);
}
