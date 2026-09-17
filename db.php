<?php
// Set consistent timezone matching local server and MySQL
date_default_timezone_set('Asia/Colombo');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Candidates for database name (prefer active database with valid tables)
$dbCandidates = ['luxury_hotel_db', 'hotel_reservation_db'];
$pdo = null;

foreach ($dbCandidates as $candidateDb) {
    try {
        $dsn = "mysql:host=$host;dbname=$candidateDb;charset=$charset";
        $testPdo = new PDO($dsn, $user, $pass, $options);
        // Verify tables exist and are readable in storage engine
        $testPdo->query("SELECT 1 FROM users LIMIT 1");
        $pdo = $testPdo;
        $db = $candidateDb;
        break;
    } catch (\Throwable $e) {
        // Continue to fallback
        continue;
    }
}

if (!$pdo) {
    // If neither passed the table check, attempt direct connection to primary
    try {
        $dsn = "mysql:host=$host;dbname=hotel_reservation_db;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}
?>