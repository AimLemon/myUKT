<?php
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    $host = $env['DB_HOST'];
    $user = $env['DB_USERNAME'];
    $pass = '';
    $db   = $env['DB_NAME'];
} else {
    die("File .env tidak ditemukan.");
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>