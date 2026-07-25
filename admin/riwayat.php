<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
 $required_role = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

 $page_title = 'Riwayat Servis';

// Ambil semua servis yang sudah selesai atau batal
$stmt = $pdo->query("
    SELECT r.*, p.nama AS nama_pelanggan, m.plat_nomor, m.merk_tipe,
           l.nama_layanan,
           s.id_servis, s.total_biaya, s.status_bayar, s.metode_bayar, s.selesai_at, s.catatan_mekanik
    FROM reservasi r
    JOIN pelanggan p ON r.id_pelanggan = p.id_pelanggan
    JOIN motor m ON r.id_motor = m.id_motor
    JOIN layanan l ON r.id_layanan = l.id_layanan
    LEFT JOIN servis s ON r.id_reservasi = s.id_reservasi
    WHERE r.status IN ('selesai', 'batal')
    ORDER BY r.tanggal DESC, r.jam DESC
");
$riwayat = $stmt->fetchAll();

// Ambil detail parts per servis
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
<p class="text-secondary small mb-4">Semua servis yang telah diselesaikan</p>

<?php if (empty($riwayat)): ?>
    <p class="text-secondary text-center py-5">Belum ada riwayat servis.</p>
<?php else: ?>
    <?php foreach ($riwayat as $r): ?>
    <div class="card-custom card p-4 mb-3">
        <div class="row align-items-start">
            <div class="col-md-8">
                <span class="small text-secondary">RSV-<?= $r['id_reservasi'] ?> &middot; <?= $r['tanggal'] ?> <?= substr($r['jam'], 0, 5) ?></span>
                <h5 class="fw-semibold mb-1"><?= htmlspecialchars($r['nama_layanan']) ?></h5>
                <p class="text-secondary small mb-2">
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($r['nama_pelanggan']) ?><br>
                    <i class="bi bi-bicycle me-1"></i><?= htmlspecialchars($r['plat_nomor'] . ' — ' . $r['merk_tipe']) ?>
                </p>
                <?php if ($r['status'] === 'selesai' && $r['catatan_mekanik']): ?>
                    <p class="small mb-0"><strong>Catatan:</strong> <?= htmlspecialchars($r['catatan_mekanik']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($r['status'] === 'selesai'): ?>
                    <?= bayarBadge($r['status_bayar']) ?>
                    <?php if ($r['metode_bayar']): ?>
                        <span class="badge bg-dark border border-secondary ms-1"><?= strtoupper(htmlspecialchars($r['metode_bayar'])) ?></span>
                    <?php endif; ?>
                    <p class="text-accent fw-bold fs-5 mb-1 mt-1"><?= formatRupiah($r['total_biaya']) ?></p>
                    <p class="text-secondary small mb-0">Selesai: <?= $r['selesai_at'] ? date('d/m/Y H:i', strtotime($r['selesai_at'])) : '-' ?></p>
                <?php else: ?>
                    <?= statusBadge($r['status']) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($r['status'] === 'selesai' && isset($detailParts[$r['id_servis']]) && !empty($detailParts[$r['id_servis']])): ?>
        <hr class="border-secondary my-3">
        <h6 class="small text-secondary fw-semibold mb-2">Sparepart Dipakai:</h6>
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