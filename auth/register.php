<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

if (isLoggedIn()) {
    redirect(BASE_URL . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'pelanggan/dashboard.php'));
}

 $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $nama     = trim($_POST['nama'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');

    // Validasi
    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username harus 3–50 karakter.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username hanya boleh huruf, angka, dan underscore.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    if (strlen($nama) < 2) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (empty($alamat)) {
        $errors[] = 'Alamat wajib diisi.';
    }
    if (empty($no_hp)) {
        $errors[] = 'No. HP wajib diisi.';
    } elseif (!preg_match('/^[0-9]+$/', $no_hp)) {
        $errors[] = 'No. HP hanya boleh berisi angka.';
    } elseif (strlen($no_hp) < 10) {
        $errors[] = 'No. HP minimal 10 digit.';
    }

    // Cek username sudah ada
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username sudah digunakan.';
        }
    }

    // Proses insert
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'pelanggan')");
            $stmt->execute([$username, $hashed]);
            $id_user = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO pelanggan (id_user, nama, alamat, no_hp) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_user, $nama, $alamat, $no_hp]);
            $pdo->commit();

            setFlash('success', 'Registrasi berhasil! Silakan login.');
            redirect(BASE_URL . 'auth/login.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Terjadi kesalahan sistem. Coba lagi.';
        }
    }
}

 $page_title = 'Register';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-11 col-sm-8 col-md-5 col-lg-4">
        <div class="card-custom card p-4">
            <h3 class="fw-bold text-center mb-1">Buat Akun</h3>
            <p class="text-secondary text-center small mb-4">Daftar sebagai pelanggan E-TMotor</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 small">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label small">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <hr class="border-secondary">
                <div class="mb-3">
                    <label class="form-label small">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small">No. HP</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" maxlength="20">
                </div>
                <button type="submit" class="btn btn-accent w-100 mt-2">Daftar</button>
            </form>
            <p class="text-secondary text-center small mt-3 mb-0">Sudah punya akun? <a href="<?= BASE_URL ?>auth/login.php" class="text-accent">Login di sini</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>