<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
 $required_role = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

 $page_title = 'Dashboard Admin';

// === POST: proses / selesaikan / batal ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Ubah status reservasi ke PROSES (sekaligus buat record servis) ---
    if ($action === 'proses') {
        $id_reservasi = (int)($_POST['id_reservasi'] ?? 0);
        if ($id_reservasi > 0) {
            $stmt = $pdo->prepare("SELECT status FROM reservasi WHERE id_reservasi = ?");
            $stmt->execute([$id_reservasi]);
            $res = $stmt->fetch();
            if ($res && $res['status'] === 'menunggu') {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE reservasi SET status = 'proses' WHERE id_reservasi = ?");
                    $stmt->execute([$id_reservasi]);
                    $stmt = $pdo->prepare("INSERT INTO servis (id_reservasi, status) VALUES (?, 'proses')");
                    $stmt->execute([$id_reservasi]);
                    $pdo->commit();
                    setFlash('success', 'Reservasi diproses.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('error', 'Gagal memproses reservasi.');
                }
            }
        }
        redirect(BASE_URL . 'admin/dashboard.php');
    }

    // --- Batalkan reservasi ---
    if ($action === 'batal') {
        $id_reservasi = (int)($_POST['id_reservasi'] ?? 0);
        if ($id_reservasi > 0) {
            $stmt = $pdo->prepare("UPDATE reservasi SET status = 'batal' WHERE id_reservasi = ? AND status = 'menunggu'");
            $stmt->execute([$id_reservasi]);
            setFlash('success', 'Reservasi dibatalkan.');
        }
        redirect(BASE_URL . 'admin/dashboard.php');
    }

    // --- Selesaikan servis: catatan + sparepart + hitung total ---
    if ($action === 'selesaikan') {
        $id_reservasi   = (int)($_POST['id_reservasi'] ?? 0);
        $catatan        = trim($_POST['catatan_mekanik'] ?? '');
        $parts          = $_POST['parts'] ?? [];     // array id_part
        $jumlahs        = $_POST['jumlah'] ?? [];     // array jumlah

        if ($id_reservasi <= 0) {
            setFlash('error', 'Data tidak valid.');
            redirect(BASE_URL . 'admin/dashboard.php');
        }

        // Validasi: cek reservasi & servis ada
        $stmt = $pdo->prepare("
            SELECT r.id_reservasi, r.id_layanan, s.id_servis, l.estimasi_biaya
            FROM reservasi r
            JOIN servis s ON r.id_reservasi = s.id_reservasi
            JOIN layanan l ON r.id_layanan = l.id_layanan
            WHERE r.id_reservasi = ? AND r.status = 'proses' AND s.status = 'proses'
        ");
        $stmt->execute([$id_reservasi]);
        $res = $stmt->fetch();

        if (!$res) {
            setFlash('error', 'Reservasi tidak ditemukan atau status tidak sesuai.');
            redirect(BASE_URL . 'admin/dashboard.php');
        }

        $id_servis      = $res['id_servis'];
        $estimasi_biaya = (float)$res['estimasi_biaya'];
        $total_parts    = 0;

        $pdo->beginTransaction();
        try {
            // Hitung total biaya parts & insert detail_servis_part
            if (!empty($parts)) {
                foreach ($parts as $i => $id_part) {
                    $id_part = (int)$id_part;
                    $jml     = max(1, (int)($jumlahs[$i] ?? 1));

                    // Ambil harga satuan & cek stok
                    $stmt = $pdo->prepare("SELECT harga, stok FROM spareparts WHERE id_part = ?");
                    $stmt->execute([$id_part]);
                    $part = $stmt->fetch();
                    if (!$part) throw new Exception("Part ID $id_part tidak ditemukan.");
                    if ($part['stok'] < $jml) throw new Exception("Stok " . $part['nama_part'] . " tidak mencukupi (sisa: {$part['stok']}).");

                    $harga_satuan = (float)$part['harga'];
                    $total_parts += $harga_satuan * $jml;

                    // Insert detail
                    $stmt = $pdo->prepare("INSERT INTO detail_servis_part (id_servis, id_part, jumlah, harga_satuan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id_servis, $id_part, $jml, $harga_satuan]);

                    // Kurangi stok
                    $stmt = $pdo->prepare("UPDATE spareparts SET stok = stok - ? WHERE id_part = ?");
                    $stmt->execute([$jml, $id_part]);
                }
            }

            $total_biaya = $estimasi_biaya + $total_parts;

            // Update servis
            $stmt = $pdo->prepare("
                UPDATE servis SET catatan_mekanik = ?, total_biaya = ?, status = 'selesai', selesai_at = NOW()
                WHERE id_servis = ?
            ");
            $stmt->execute([$catatan, $total_biaya, $id_servis]);

            // Update reservasi
            $stmt = $pdo->prepare("UPDATE reservasi SET status = 'selesai' WHERE id_reservasi = ?");
            $stmt->execute([$id_reservasi]);

            $pdo->commit();
            setFlash('success', "Servis selesai! Total biaya: " . formatRupiah($total_biaya));
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal menyelesaikan servis: ' . $e->getMessage());
        }
        redirect(BASE_URL . 'admin/dashboard.php');
    }
}

// Data: antrean 7 hari ke depan
$today = date('Y-m-d');
$week_later = date('Y-m-d', strtotime('+7 days'));
$stmt = $pdo->prepare("
   SELECT r.*, p.nama, m.plat_nomor, m.merk_tipe, l.nama_layanan, l.estimasi_biaya,
          s.id_servis, s.status AS status_servis
   FROM reservasi r
   JOIN pelanggan p ON r.id_pelanggan = p.id_pelanggan
   JOIN motor m ON r.id_motor = m.id_motor
   JOIN layanan l ON r.id_layanan = l.id_layanan
   LEFT JOIN servis s ON r.id_reservasi = s.id_reservasi
   WHERE r.tanggal BETWEEN ? AND ? AND r.status != 'batal'
   ORDER BY r.tanggal, r.jam
");
$stmt->execute([$today, $week_later]);
$antrean = $stmt->fetchAll();

// Data: part kritis (untuk notifikasi)
 $kritisParts = $pdo->query("SELECT nama_part, stok FROM spareparts WHERE stok <= 2 ORDER BY stok ASC")->fetchAll();

// Data: semua sparepart (untuk dropdown di modal selesaikan)
 $allParts = $pdo->query("SELECT id_part, kode_part, nama_part, stok, harga FROM spareparts WHERE stok > 0 ORDER BY nama_part")->fetchAll();
 $partsOptions = '';
foreach ($allParts as $ap) {
    $partsOptions .= '<option value="' . $ap['id_part'] . '">' . htmlspecialchars($ap['kode_part'] . ' — ' . $ap['nama_part'] . ' (Stok: ' . $ap['stok'] . ', Rp ' . number_format($ap['harga'], 0, ',', '.') . ')') . '</option>';
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2 class="fw-bold mb-1">Dashboard Admin</h2>
<p class="text-secondary small mb-4">Antrean Minggu Ini (<?= date('d M', strtotime($today)) ?> — <?= date('d M Y', strtotime($week_later)) ?>)</p>

<!-- Notifikasi stok kritis -->
<?php if ($kritisParts): ?>
<div class="alert alert-kritis mb-4">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Stok Kritis (<?= count($kritisParts) ?> part)</strong>
    <ul class="mb-0 mt-2 small">
        <?php foreach ($kritisParts as $kp): ?>
            <li><?= htmlspecialchars($kp['nama_part']) ?> — sisa <strong class="text-danger"><?= $kp['stok'] ?></strong></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= BASE_URL ?>admin/inventaris.php" class="btn btn-outline-danger btn-sm mt-2">Kelola Inventaris</a>
</div>
<?php endif; ?>

<!-- Tabel Antrean -->
<div class="card-custom card p-4">
    <?php if (empty($antrean)): ?>
        <p class="text-secondary text-center py-3 mb-0">Tidak ada antrean untuk 7 hari ke depan.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle">
            <thead>
                <tr>
                    <th>Antrean</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Pelanggan</th>
                    <th>Motor</th>
                    <th>Layanan</th>
                    <th>Estimasi</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($antrean as $a): ?>
                <tr class="<?= $a['tanggal'] === $today ? 'bg-today' : '' ?>">
                    <td><strong class="text-accent">RSV-<?= $a['id_reservasi'] ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($a['tanggal'])) ?></td>
                    <td><?= substr($a['jam'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($a['nama']) ?></td>
                    <td><?= htmlspecialchars($a['plat_nomor']) ?><br><small class="text-secondary"><?= htmlspecialchars($a['merk_tipe']) ?></small></td>
                    <td><?= htmlspecialchars($a['nama_layanan']) ?></td>
                    <td><?= formatRupiah($a['estimasi_biaya']) ?></td>
                    <td><?= statusBadge($a['status']) ?></td>
                    <td style="min-width:140px;">
                        <?php if ($a['status'] === 'menunggu'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="proses">
                                <input type="hidden" name="id_reservasi" value="<?= $a['id_reservasi'] ?>">
                                <button class="btn btn-info btn-sm py-0 px-2 text-dark" title="Proses" <?= ($a['tanggal'] === $today) ? '' : 'disabled' ?>>
                                    <i class="bi bi-play-fill me-1"></i>Proses
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Batalkan reservasi ini?')">
                                <input type="hidden" name="action" value="batal">
                                <input type="hidden" name="id_reservasi" value="<?= $a['id_reservasi'] ?>">
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2" title="Batal"><i class="bi bi-x-lg"></i></button>
                            </form>
                        <?php elseif ($a['status'] === 'proses'): ?>
                            <button class="btn btn-success btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalSelesai"
                                    onclick="prepareSelesai(<?= $a['id_reservasi'] ?>, <?= $a['id_servis'] ?>, '<?= htmlspecialchars(addslashes($a['nama_layanan'])) ?>', <?= $a['estimasi_biaya'] ?>)"
                                    title="Selesaikan">
                                <i class="bi bi-check-lg me-1"></i>Selesai
                            </button>
                        <?php else: ?>
                            <span class="text-secondary small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Selesaikan Servis -->
<div class="modal fade" id="modalSelesai" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-semibold">Selesaikan Servis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formSelesai">
                <div class="modal-body">
                    <input type="hidden" name="action" value="selesaikan">
                    <input type="hidden" name="id_reservasi" id="selesai_id_reservasi">

                    <div class="mb-3">
                        <label class="form-label small">Layanan</label>
                        <input type="text" id="selesai_layanan" class="form-control form-control-sm" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Catatan Mekanik</label>
                        <textarea name="catatan_mekanik" class="form-control form-control-sm" rows="3" placeholder="Deskripsi pekerjaan yang dilakukan..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Sparepart yang Dipakai <span class="text-secondary">(opsional)</span></label>
                        <div id="part_rows">
                            <!-- Baris part akan ditambah via JS -->
                        </div>
                        <button type="button" id="btn_add_part_row" class="btn btn-outline-warning btn-sm mt-2">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Part
                        </button>
                    </div>

                    <div class="p-3 rounded" style="background:#1a1a1a; border:1px solid #333;">
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Estimasi biaya layanan:</span>
                            <span id="selesai_estimasi" class="fw-semibold">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span class="text-secondary">Total part:</span>
                            <span id="selesai_total_part" class="fw-semibold">Rp 0</span>
                        </div>
                        <hr class="border-secondary my-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">TOTAL BIAYA:</span>
                            <span id="selesai_total" class="fw-bold text-accent fs-5">Rp 0</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-accent btn-sm">Simpan & Selesaikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Data part untuk dropdown (dari PHP)
window.availableParts = `<?= $partsOptions ?>`;

let estimasiBiaya = 0;

function prepareSelesai(idReservasi, idServis, namaLayanan, estimasi) {
    document.getElementById('selesai_id_reservasi').value = idReservasi;
    document.getElementById('selesai_layanan').value = namaLayanan;
    estimasiBiaya = estimasi;
    document.getElementById('selesai_estimasi').textContent = 'Rp ' + estimasi.toLocaleString('id-ID');
    document.getElementById('part_rows').innerHTML = '';
    hitungTotal();
}

function hitungTotal() {
    let totalPart = 0;
    document.querySelectorAll('.part-row').forEach(function(row) {
        const sel = row.querySelector('select[name="parts[]"]');
        const jml = parseInt(row.querySelector('input[name="jumlah[]"]').value) || 1;
        if (sel && sel.value) {
            const opt = sel.options[sel.selectedIndex];
            // Harga ada di teks option: (Stok: X, Rp YYY)
            const match = opt.text.match(/Rp\s([\d.]+)/);
            if (match) {
                totalPart += parseInt(match[1].replace(/\./g, '')) * jml;
            }
        }
    });
    document.getElementById('selesai_total_part').textContent = 'Rp ' + totalPart.toLocaleString('id-ID');
    document.getElementById('selesai_total').textContent = 'Rp ' + (estimasiBiaya + totalPart).toLocaleString('id-ID');
}

// Delegasi event untuk hitung ulang saat dropdown/jumlah berubah
document.getElementById('part_rows').addEventListener('change', hitungTotal);
document.getElementById('part_rows').addEventListener('input', hitungTotal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>