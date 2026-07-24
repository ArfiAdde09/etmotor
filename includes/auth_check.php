<?php
/**
 * Guard: wajib dipasang di baris paling atas halaman yang butuh login.
 * $required_role: 'admin' atau 'pelanggan'
 */
if (!isset($required_role)) {
    $required_role = null;
}

if (!isset($_SESSION['id_user'])) {
    setFlash('error', 'Silakan login terlebih dahulu.');
    redirect(BASE_URL . 'auth/login.php');
}

if ($required_role !== null && $_SESSION['role'] !== $required_role) {
    // Role tidak sesuai, arahkan ke dashboard mereka
    if ($_SESSION['role'] === 'admin') {
        redirect(BASE_URL . 'admin/dashboard.php');
    } else {
        redirect(BASE_URL . 'pelanggan/dashboard.php');
    }
}