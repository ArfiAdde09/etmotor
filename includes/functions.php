<?php
/**
 * Helper functions untuk E-TMotor
 */

// Redirect dengan pesan flash
function redirect($url) {
    header("Location: $url");
    exit;
}

// Set pesan flash di session
function setFlash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

// Ambil & hapus pesan flash (sekali tampil)
function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// Cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['id_user']);
}

// Format angka ke Rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Ambil data pelanggan berdasarkan id_user
function getPelangganByUser($pdo, $id_user) {
    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_user = ?");
    $stmt->execute([$id_user]);
    return $stmt->fetch();
}

// Ambil nama role dalam bahasa Indonesia
function roleLabel($role) {
    return $role === 'admin' ? 'Admin / Mekanik' : 'Pelanggan';
}

// Badge HTML berdasarkan status reservasi
function statusBadge($status) {
    $map = [
        'menunggu' => 'warning',
        'proses'   => 'info',
        'selesai'  => 'success',
        'batal'    => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return "<span class=\"badge bg-$color text-dark\">$status</span>";
}

// Badge HTML berdasarkan status bayar
function bayarBadge($status) {
    return $status === 'lunas'
        ? '<span class="badge bg-success">Lunas</span>'
        : '<span class="badge bg-danger">Belum Bayar</span>';
}

// Indikator stok: return class Bootstrap
function stokClass($stok) {
    if ($stok >= 10) return 'text-success';
    if ($stok >= 3)  return 'text-warning';
    return 'text-danger';
}

// Label stok
function stokLabel($stok) {
    if ($stok >= 10) return 'Aman';
    if ($stok >= 3)  return 'Terbatas';
    return 'Kritis';
}