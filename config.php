<?php
define('APP_NAME', 'MyUKT');
define('APP_URL', 'http://localhost/tugasweb');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'a250911092509@gmail.com'); // Ganti dengan email Gmail Anda
define('SMTP_PASSWORD', 'dpfn jjmv xzsu xanr'); // Ganti dengan App Password
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'a250911092509@gmail.com');
define('SMTP_FROM_NAME', 'MyUKT Admin');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'myukt';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
?>