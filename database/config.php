<?php
$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "db_ecommerce";
$port = 3306;

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_errno) {
    die("Koneksi gagal: (" . $conn->connect_errno . ") " . $conn->connect_error);
}
?>