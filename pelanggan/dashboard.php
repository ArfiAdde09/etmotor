<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
 $required_role = 'pelanggan';
require_once __DIR__ . '/../includes/auth_check.php';

 $page_title = 'Dashboard Pelanggan';
 $pelanggan = getPelangganByUser($pdo, $_SESSION['id_user']);

// === AJAX: cek slot ===
if (isset($_GET['action']) && $_GET['action'] === 'cek_slot') {
    header('Content-Type: application/json');
    $tanggal = $_GET['tanggal'] ?? '';
    $slots = [];

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        // Slot jam 08:00 – 16:00
        for ($h = 8; $h <= 16; $h++) {
            $jam = str_pad($h, 2, '0') . ':00:00';
            $stmt = $pdo->prepare("SELECT COUNT(*) AS terisi FROM reservasi WHERE tanggal = ? AND jam = ? AND status != 'batal'");
            $stmt->execute([$tanggal, $jam]);
            $row = $stmt->fetch();
            $slots[] = [
                'jam'    => substr($jam, 0, 5),
                'terisi' => (int)$row['terisi'],
            ];
        }
    }
    echo json_encode(['slots' => $slots]);
    exit;
}

// === AJAX: konfirmasi bayar ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bayar') {
    header('Content-Type: application/json');
    $id_servis   = (int)($_POST['id_servis'] ?? 0);
    $metode_bayar = $_POST['metode_bayar'] ?? '';

    if ($id_servis <= 0 || !in_array($metode_bayar, ['gopay', 'shopeepay', 'tunai'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
        exit;
    }

    // Pastikan servis milik pelanggan ini
    $stmt = $pdo->prepare("
        SELECT s.id_servis FROM servis s
        JOIN reservasi r ON s.id_reservasi = r.id_reservasi
        WHERE s.id_servis = ? AND r.id_pelanggan = ? AND s.status = 'selesai' AND s.status_bayar = 'belum_bayar'
    ");
    $stmt->execute([$id_servis, $pelanggan['id_pelanggan']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Servis tidak ditemukan atau sudah lunas.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE servis SET metode_bayar = ?, status_bayar = 'lunas' WHERE id_servis = ?");
    $stmt->execute([$metode_bayar, $id_servis]);
    echo json_encode(['success' => true]);
    exit;
}

// === POST: tambah motor baru ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_motor') {
    $plat      = trim(strtoupper($_POST['plat_nomor'] ?? ''));
    $merk      = trim($_POST['merk_tipe'] ?? '');
    $spek      = trim($_POST['spek_mesin'] ?? '');

    if ($plat === '' || $merk === '') {
        setFlash('error', 'Plat nomor dan merk/tipe wajib diisi.');
    } else {
        // Cek plat duplikat untuk pelanggan ini
        $stmt = $pdo->prepare("SELECT id_motor FROM motor WHERE id_pelanggan = ? AND plat_nomor = ?");
        $stmt->execute([$pelanggan['id_pelanggan'], $plat]);
        if ($stmt->fetch()) {
            setFlash('error', 'Motor dengan plat ini sudah terdaftar.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO motor (id_pelanggan, plat_nomor, merk_tipe, spek_mesin) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pelanggan['id_pelanggan'], $plat, $merk, $spek]);
            setFlash('success', 'Motor berhasil ditambahkan.');
        }
    }
    redirect(BASE_URL . 'pelanggan/dashboard.php');
}

// === POST: buat reservasi ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reservasi') {
    $id_motor   = (int)($_POST['id_motor'] ?? 0);
    $id_layanan = (int)($_POST['id_layanan'] ?? 0);
    $tanggal    = $_POST['tanggal'] ?? '';
    $jam        = $_POST['jam'] ?? '';

    $errors = [];
    if ($id_motor <= 0) $errors[] = 'Pilih motor.';
    if ($id_layanan <= 0) $errors[] = 'Pilih layanan.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $errors[] = 'Tanggal tidak valid.';
    if (!preg_match('/^\d{2}:\d{2}$/', $jam)) $errors[] = 'Pilih jam slot.';

    // Validasi tanggal bukan masa lalu
    if ($tanggal < date('Y-m-d')) $errors[] = 'Tidak bisa reservasi di tanggal yang sudah lewat.';

    // Validasi slot belum penuh
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS terisi FROM reservasi WHERE tanggal = ? AND jam = ? AND status != 'batal'");
        $stmt->execute([$tanggal, $jam . ':00']);
        $row = $stmt->fetch();
        if ($row['terisi'] >= 3) $errors[] = 'Slot jam tersebut sudah penuh.';
    }

    // Validasi motor milik pelanggan ini
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id_motor FROM motor WHERE id_motor = ? AND id_pelanggan = ?");
        $stmt->execute([$id_motor, $pelanggan['id_pelanggan']]);
        if (!$stmt->fetch()) $errors[] = 'Motor tidak valid.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO reservasi (id_pelanggan, id_motor, id_layanan, tanggal, jam) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$pelanggan['id_pelanggan'], $id_motor, $id_layanan, $tanggal, $jam . ':00']);
        $id_reservasi = $pdo->lastInsertId();
        setFlash('success', "Reservasi berhasil! Nomor antrean kamu: <strong>RSV-$id_reservasi</strong>");
        redirect(BASE_URL . 'pelanggan/dashboard.php');
    } else {
        setFlash('error', implode('<br>', $errors));
        redirect(BASE_URL . 'pelanggan/dashboard.php');
    }
}

// Data untuk view
 $motors   = $pdo->prepare("SELECT * FROM motor WHERE id_pelanggan = ? ORDER BY plat_nomor");
 $motors->execute([$pelanggan['id_pelanggan']]);
 $motors = $motors->fetchAll();

 $layanans = $pdo->query("SELECT * FROM layanan ORDER BY estimasi_biaya ASC")->fetchAll();

// Reservasi aktif pelanggan ini (status menunggu/proses)
 $stmt = $pdo->prepare("
    SELECT r.*, l.nama_layanan, m.plat_nomor, m.merk_tipe, s.id_servis, s.total_biaya, s.status_bayar, s.metode_bayar, s.status AS status_servis
    FROM reservasi r
    JOIN layanan l ON r.id_layanan = l.id_layanan
    JOIN motor m ON r.id_motor = m.id_motor
    LEFT JOIN servis s ON r.id_reservasi = s.id_reservasi
    WHERE r.id_pelanggan = ? AND r.status IN ('menunggu','proses')
    ORDER BY r.tanggal, r.jam
");
 $stmt->execute([$pelanggan['id_pelanggan']]);
 $reservasiAktif = $stmt->fetchAll();

// Tagihan belum bayar (servis selesai tapi belum bayar)
 $stmt = $pdo->prepare("
    SELECT s.*, r.tanggal, l.nama_layanan, m.plat_nomor, m.merk_tipe
    FROM servis s
    JOIN reservasi r ON s.id_reservasi = r.id_reservasi
    JOIN layanan l ON r.id_layanan = l.id_layanan
    JOIN motor m ON r.id_motor = m.id_motor
    WHERE r.id_pelanggan = ? AND s.status = 'selesai' AND s.status_bayar = 'belum_bayar'
    ORDER BY s.selesai_at DESC
");
 $stmt->execute([$pelanggan['id_pelanggan']]);
 $tagihan = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2 class="fw-bold mb-1">Halo, <?= htmlspecialchars($pelanggan['nama']) ?></h2>
<p class="text-secondary small mb-4">Kelola reservasi dan pantau status servis motormu</p>

<!-- Tabs -->
<ul class="nav nav-tabs border-secondary mb-4" id="dashTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active text-accent" data-bs-toggle="tab" data-bs-target="#tabReservasi" type="button">Buat Reservasi</button>
    </li>
    <li class="nav-item">
        <button class="nav-link text-secondary" data-bs-toggle="tab" data-bs-target="#tabStatus" type="button">
            Status Kendaraan <?= $reservasiAktif ? '<span class="badge bg-accent text-dark ms-1">' . count($reservasiAktif) . '</span>' : '' ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link text-secondary" data-bs-toggle="tab" data-bs-target="#tabTagihan" type="button">
            Tagihan <?= $tagihan ? '<span class="badge bg-danger ms-1">' . count($tagihan) . '</span>' : '' ?>
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- TAB 1: Buat Reservasi -->
    <div class="tab-pane fade show active" id="tabReservasi">
        <?php if (empty($motors)): ?>
            <div class="alert alert-warning small">
                Kamu belum punya motor terdaftar. Tambahkan motor dulu di bawah ini.
            </div>
            <!-- Form tambah motor -->
            <div class="card-custom card p-4 mb-4">
                <h5 class="fw-semibold mb-3">Tambah Motor Baru</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="tambah_motor">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Plat Nomor</label>
                            <input type="text" name="plat_nomor" class="form-control form-control-sm" placeholder="B 1234 XYZ" required style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Merk / Tipe</label>
                            <input type="text" name="merk_tipe" class="form-control form-control-sm" placeholder="Honda Vario 160" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Spek Mesin (opsional)</label>
                            <input type="text" name="spek_mesin" class="form-control form-control-sm" placeholder="159cc, 4-stroke">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-accent btn-sm">Simpan Motor</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="card-custom card p-4">
                <form method="POST" id="formReservasi">
                    <input type="hidden" name="action" value="reservasi">
                    <input type="hidden" name="jam" id="jam_reservasi" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Pilih Motor</label>
                            <select name="id_motor" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Motor --</option>
                                <?php foreach ($motors as $m): ?>
                                    <option value="<?= $m['id_motor'] ?>"><?= htmlspecialchars($m['plat_nomor'] . ' — ' . $m['merk_tipe']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="#" class="small text-accent" data-bs-toggle="modal" data-bs-target="#modalTambahMotor">+ Tambah motor baru</a>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Pilih Layanan</label>
                            <select name="id_layanan" id="id_layanan" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Layanan --</option>
                                <?php foreach ($layanans as $l): ?>
                                    <option value="<?= $l['id_layanan'] ?>"><?= htmlspecialchars($l['nama_layanan']) ?> — <?= formatRupiah($l['estimasi_biaya']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Tanggal</label>
                            <input type="date" name="tanggal" id="tgl_reservasi" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Pilih Jam</label>
                            <div id="slot_container">
                                <p class="text-secondary small">Pilih tanggal terlebih dahulu.</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-accent btn-sm mt-2">Buat Reservasi</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: Status Kendaraan -->
    <div class="tab-pane fade" id="tabStatus">
        <?php if (empty($reservasiAktif)): ?>
            <p class="text-secondary">Tidak ada reservasi aktif.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom table-sm align-middle">
                    <thead>
                        <tr><th>Antrean</th><th>Tanggal</th><th>Jam</th><th>Motor</th><th>Layanan</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservasiAktif as $r): ?>
                        <tr>
                            <td><strong class="text-accent">RSV-<?= $r['id_reservasi'] ?></strong></td>
                            <td><?= $r['tanggal'] ?></td>
                            <td><?= substr($r['jam'], 0, 5) ?></td>
                            <td><?= htmlspecialchars($r['plat_nomor'] . ' — ' . $r['merk_tipe']) ?></td>
                            <td><?= htmlspecialchars($r['nama_layanan']) ?></td>
                            <td><?= statusBadge($r['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 3: Tagihan -->
    <div class="tab-pane fade" id="tabTagihan">
        <?php if (empty($tagihan)): ?>
            <p class="text-secondary">Tidak ada tagihan yang perlu dibayar.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($tagihan as $t): ?>
                <?php
                // Ambil detail spare parts untuk servis ini
                $parts_stmt = $pdo->prepare("
                    SELECT dsp.jumlah, dsp.harga_satuan, sp.nama_part
                    FROM detail_servis_part dsp
                    JOIN spareparts sp ON dsp.id_part = sp.id_part
                    WHERE dsp.id_servis = ?
                ");
                $parts_stmt->execute([$t['id_servis']]);
                $parts = $parts_stmt->fetchAll();
                ?>
                <div class="col-md-6">
                    <div class="card-custom card p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="small text-secondary">RSV-<?= $t['id_reservasi'] ?></span>
                                <h6 class="fw-semibold mb-0"><?= htmlspecialchars($t['nama_layanan']) ?></h6>
                                <small class="text-secondary"><?= htmlspecialchars($t['plat_nomor'] . ' — ' . $t['merk_tipe']) ?></small>
                            </div>
                            <?= bayarBadge($t['status_bayar']) ?>
                        </div>

                        <?php if (!empty($t['catatan_mekanik'])): ?>
                            <div class="mb-3">
                                <p class="small text-secondary mb-1">Catatan Mekanik:</p>
                                <p class="small fst-italic bg-dark rounded p-2 mb-0">
                                    <?= nl2br(htmlspecialchars($t['catatan_mekanik'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($parts)): ?>
                            <div class="mb-3">
                                <p class="small text-secondary mb-1">Spare Part Digunakan:</p>
                                <ul class="list-group list-group-flush small">
                                    <?php foreach ($parts as $p): ?>
                                    <li class="list-group-item bg-transparent px-0 d-flex justify-content-between">
                                        <span><?= htmlspecialchars($p['nama_part']) ?> (x<?= $p['jumlah'] ?>)</span>
                                        <span class="text-secondary"><?= formatRupiah($p['harga_satuan'] * $p['jumlah']) ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <p class="text-accent fw-bold fs-5 mb-3">Total: <?= formatRupiah($t['total_biaya']) ?></p>
                        
                        <div class="d-flex gap-2 align-items-center">
                            <select id="metode_<?= $t['id_servis'] ?>" class="form-select form-select-sm" style="max-width:160px;">
                                <option value="">Pilih metode</option>
                                <option value="gopay">GoPay</option>
                                <option value="shopeepay">ShopeePay</option>
                                <option value="tunai">Tunai</option>
                            </select>
                            <button class="btn btn-accent btn-sm btn-konfirmasi-bayar" data-id="<?= $t['id_servis'] ?>">
                                Konfirmasi Bayar
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Tambah Motor -->
<div class="modal fade" id="modalTambahMotor" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-semibold">Tambah Motor Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="tambah_motor">
                    <div class="mb-3">
                        <label class="form-label small">Plat Nomor</label>
                        <input type="text" name="plat_nomor" class="form-control form-control-sm" placeholder="B 1234 XYZ" required style="text-transform:uppercase;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Merk / Tipe</label>
                        <input type="text" name="merk_tipe" class="form-control form-control-sm" placeholder="Honda Vario 160" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Spek Mesin (opsional)</label>
                        <input type="text" name="spek_mesin" class="form-control form-control-sm" placeholder="159cc, 4-stroke">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-accent btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Base URL untuk JS -->
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>