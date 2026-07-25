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

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }
    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID untuk mencegah session fixation
            session_regenerate_id(true);
            $_SESSION['id_user']  = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            redirect(BASE_URL . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'pelanggan/dashboard.php'));
        } else {
            $errors[] = 'Username atau password salah.';
        }
    }
}

 $page_title = 'Login';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-11 col-sm-8 col-md-5 col-lg-4">
        <div class="card-custom card p-4">
            <h3 class="fw-bold text-center mb-1">Login</h3>
            <p class="text-secondary text-center small mb-4">Masuk ke akun E-TMotor kamu</p>

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
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-accent w-100 mt-2">Login</button>
            </form>
            <p class="text-secondary text-center small mt-3 mb-0">Belum punya akun? <a href="<?= BASE_URL ?>auth/register.php" class="text-accent">Daftar di sini</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>