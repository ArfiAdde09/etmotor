<?php
// Base URL proyek — sesuaikan dengan nama folder di Laragon
define('BASE_URL', '/etmotor/');

// Konfigurasi koneksi PDO ke MySQL via Laragon
 $host     = 'localhost';
 $dbname   = 'db_etmotor';
 $username = 'root';
 $password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}