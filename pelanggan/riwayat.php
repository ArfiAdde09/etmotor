<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
 $required_role = 'pelanggan';
require_once __DIR__ . '/../includes/auth_check.php';

 $page_title = 'Riwayat Servis';
 $pelanggan = getPelangganByUser($pdo, $_SESSION['id_user']);

// Ambil semua servis selesai milik pelanggan ini
 $stmt = $pdo->prepare("
    SELECT s.*, r.tanggal, r.jam, m.plat_nomor, m.merk_tipe,
           l.nama_layanan, l.estimasi_biaya
    FROM servis s
    JOIN reservasi r ON s.id_reservasi = r.id_reservasi
    JOIN motor m ON r.id_motor = m.id_motor
    JOIN layanan l ON r.id_layanan = l.id_layanan
    WHERE r.id_pelanggan = ? AND s.status = 'selesai'
    ORDER BY s.selesai_at DESC
");
 $stmt->execute([$pelanggan['id_pelanggan']]);
 $riwayat = $stmt->fetchAll();

// Ambil detail parts
 $detailParts = [];
if (!empty($riwayat)) {
    $ids = array_column($riwayat, 'id_servis');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT dsp.*, sp.nama_part, sp.kode_part
        FROM detail_servis_part dsp
        JOIN spareparts sp ON dsp.id_part = sp.id_part
        WHERE dsp.id_servis IN ($placeholders)
        ORDER BY dsp.id_detail
    ");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $detailParts[$row['id_servis']][] = $row;
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2 class="fw-bold mb-1">Riwayat Servis</h2>
<p class="text-secondary small mb-4">Riwayat semua servis motormu yang sudah selesai</p>

<?php if (empty($riwayat)): ?>
    <div class="text-center py-5">
        <i class="bi bi-clock-history display-4 text-secondary"></i>
        <p class="text-secondary mt-2">Belum ada riwayat servis.</p>
        <a href="<?= BASE_URL ?>pelanggan/dashboard.php" class="btn btn-accent btn-sm">Buat Reservasi</a>
    </div>
<?php else: ?>
    <?php foreach ($riwayat as $r): ?>
    <div class="card-custom card p-4 mb-3">
        <div class="row align-items-start">
            <div class="col-md-6">
                <span class="small text-secondary">RSV-<?= $r['id_reservasi'] ?> &middot; <?= $r['tanggal'] ?> <?= substr($r['jam'], 0, 5) ?></span>
                <h5 class="fw-semibold mb-1"><?= htmlspecialchars($r['nama_layanan']) ?></h5>
                <p class="text-secondary small mb-2">
                    <i class="bi bi-bicycle me-1"></i><?= htmlspecialchars($r['plat_nomor'] . ' — ' . $r['merk_tipe']) ?>
                </p>
                <?php if ($r['catatan_mekanik']): ?>
                    <p class="small mb-0"><strong>Catatan Mekanik:</strong> <?= htmlspecialchars($r['catatan_mekanik']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <?= bayarBadge($r['status_bayar']) ?>
                <?php if ($r['metode_bayar']): ?>
                    <span class="badge bg-dark border border-secondary ms-1"><?= strtoupper(htmlspecialchars($r['metode_bayar'])) ?></span>
                <?php endif; ?>
                <p class="text-accent fw-bold fs-5 mb-1 mt-1"><?= formatRupiah($r['total_biaya']) ?></p>
                <p class="text-secondary small mb-0">Selesai: <?= $r['selesai_at'] ?></p>
            </div>
        </div>

        <?php if (isset($detailParts[$r['id_servis']]) && !empty($detailParts[$r['id_servis']])): ?>
        <hr class="border-secondary my-3">
        <h6 class="small text-secondary fw-semibold mb-2">Sparepart yang Dipakai:</h6>
        <div class="table-responsive">
            <table class="table table-custom table-sm mb-0" style="font-size:0.85rem;">
                <thead>
                    <tr><th>Kode</th><th>Nama</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($detailParts[$r['id_servis']] as $dp): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($dp['kode_part']) ?></code></td>
                        <td><?= htmlspecialchars($dp['nama_part']) ?></td>
                        <td><?= $dp['jumlah'] ?></td>
                        <td><?= formatRupiah($dp['harga_satuan']) ?></td>
                        <td><?= formatRupiah($dp['harga_satuan'] * $dp['jumlah']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>