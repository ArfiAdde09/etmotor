<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
session_start();

 $page_title = 'Beranda';
 $layanan = $pdo->query("SELECT * FROM layanan ORDER BY estimasi_biaya ASC")->fetchAll();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero -->
<section class="hero-section text-center py-5 px-3">
    <div class="position-relative" style="z-index:1;">
        <p class="text-accent fw-semibold mb-2" style="font-size:0.85rem; letter-spacing:0.15em;">BENGKEL TMOTOR</p>
        <h1 class="fw-bold display-5 mb-3">Servis & Modifikasi<br>Motor Profesional</h1>
        <p class="text-secondary mx-auto" style="max-width:540px;">
            Dari servis ringan harian sampai bore up dan setting camshaft tingkat lanjut.
            Kami tangani dengan presisi dan pengalaman bertahun-tahun.
        </p>
        <div class="mt-4">
            <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-accent btn-lg px-4 me-2">Daftar Sekarang</a>
            <a href="#layanan" class="btn btn-outline-light btn-lg px-4">Lihat Layanan</a>
        </div>
    </div>
</section>

<!-- Katalog Layanan -->
<section id="layanan" class="py-5">
    <h2 class="section-heading fw-bold mb-4">Layanan & Harga</h2>
    <div class="row g-4 mt-2">
        <?php foreach ($layanan as $l): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card-custom card h-100 p-4">
                <h5 class="fw-semibold mb-2"><?= htmlspecialchars($l['nama_layanan']) ?></h5>
                <p class="text-secondary small mb-3"><?= htmlspecialchars($l['deskripsi']) ?></p>
                <p class="text-accent fw-bold mb-0"><?= formatRupiah($l['estimasi_biaya']) ?></p>
                <p class="text-secondary" style="font-size:0.75rem;">*Estimasi, biaya final menyesuaikan kondisi</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Info Operasional -->
<section class="py-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card-custom card p-4 h-100">
                <h5 class="fw-semibold mb-3"><i class="bi bi-clock text-accent me-2"></i>Jam Operasional</h5>
                <table class="table table-custom table-sm mb-0">
                    <tr><td>Senin – Jumat</td><td class="text-end">08.00 – 17.00</td></tr>
                    <tr><td>Sabtu</td><td class="text-end">08.00 – 15.00</td></tr>
                    <tr><td>Minggu</td><td class="text-end text-danger">Tutup</td></tr>
                </table>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom card p-4 h-100">
                <h5 class="fw-semibold mb-3"><i class="bi bi-geo-alt text-accent me-2"></i>Lokasi</h5>
                <div style="border-radius:8px; overflow:hidden; border:1px solid #333;">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.2!2d106.82!3d-6.35!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNsKwMjEnMDAuMCJTIDEwNsKwNDknMTIuMCJF!5e0!3m2!1sid!2sid!4v1700000000000!5m2!1sid!2sid"
                        width="100%" height="180" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <p class="text-secondary small mt-2 mb-0">Jl. Raya Motor No. 88, Jakarta Selatan</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom card p-4 h-100">
                <h5 class="fw-semibold mb-3"><i class="bi bi-telephone text-accent me-2"></i>Kontak</h5>
                <ul class="list-unstyled text-secondary mb-0">
                    <li class="mb-2"><i class="bi bi-whatsapp me-2 text-success"></i>0812-3456-7890</li>
                    <li class="mb-2"><i class="bi bi-instagram me-2 text-danger"></i>@tmotor.bengkel</li>
                    <li class="mb-2"><i class="bi bi-envelope me-2"></i>info@tmotor.com</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>