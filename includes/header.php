<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'E-TMotor' ?> — Bengkel TMotor</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>index.php">
            <i class="bi bi-gear-wide-connected text-accent me-1"></i>E-TMotor
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-center gap-1">
                <?php if (!isLoggedIn()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-accent btn-sm px-3" href="<?= BASE_URL ?>auth/register.php">Register</a></li>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/inventaris.php">Inventaris</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/riwayat.php">Riwayat</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-accent"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </li>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm px-3" href="<?= BASE_URL ?>auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pelanggan/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pelanggan/riwayat.php">Riwayat</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-accent"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </li>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm px-3" href="<?= BASE_URL ?>auth/logout.php">Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash messages -->
<?php $flash_error = getFlash('error'); if ($flash_error): ?>
    <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($flash_error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
<?php $flash_success = getFlash('success'); if ($flash_success): ?>
    <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($flash_success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>

<main class="container my-4">