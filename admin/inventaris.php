<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
 $required_role = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

 $page_title = 'Inventaris Sparepart';

// Handle POST actions: tambah, update, hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $kode     = trim($_POST['kode_part'] ?? '');
        $nama     = trim($_POST['nama_part'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $stok     = (int)($_POST['stok'] ?? 0);
        $harga    = (float)($_POST['harga'] ?? 0);

        if ($kode === '' || $nama === '') {
            setFlash('error', 'Kode part dan nama part wajib diisi.');
        } elseif ($stok < 0 || $harga < 0) {
            setFlash('error', 'Stok dan harga tidak boleh negatif.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO spareparts (kode_part, nama_part, kategori, stok, harga) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$kode, $nama, $kategori, $stok, $harga]);
                setFlash('success', 'Sparepart berhasil ditambahkan.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Kode part sudah ada.');
                } else {
                    setFlash('error', 'Gagal menambahkan sparepart.');
                }
            }
        }
        redirect(BASE_URL . 'admin/inventaris.php');
    }

    if ($action === 'update') {
        $id_part  = (int)($_POST['id_part'] ?? 0);
        $kode     = trim($_POST['kode_part'] ?? '');
        $nama     = trim($_POST['nama_part'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $stok     = (int)($_POST['stok'] ?? 0);
        $harga    = (float)($_POST['harga'] ?? 0);

        if ($id_part <= 0 || $nama === '') {
            setFlash('error', 'Data tidak valid.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE spareparts SET kode_part=?, nama_part=?, kategori=?, stok=?, harga=? WHERE id_part=?");
                $stmt->execute([$kode, $nama, $kategori, $stok, $harga, $id_part]);
                setFlash('success', 'Sparepart berhasil diperbarui.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('error', 'Kode part sudah digunakan part lain.');
                } else {
                    setFlash('error', 'Gagal memperbarui.');
                }
            }
        }
        redirect(BASE_URL . 'admin/inventaris.php');
    }

    if ($action === 'hapus') {
        $id_part = (int)($_POST['id_part'] ?? 0);
        if ($id_part > 0) {
            $stmt = $pdo->prepare("DELETE FROM spareparts WHERE id_part = ?");
            $stmt->execute([$id_part]);
            setFlash('success', 'Sparepart dihapus.');
        }
        redirect(BASE_URL . 'admin/inventaris.php');
    }
}

// GET parameters: search & filter
 $search   = trim($_GET['search'] ?? '');
 $kategori = trim($_GET['kategori'] ?? '');

// Ambil daftar kategori unik untuk filter
 $kategoriList = $pdo->query("SELECT DISTINCT kategori FROM spareparts WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

// Build query
 $sql = "SELECT * FROM spareparts WHERE 1=1";
 $params = [];

if ($search !== '') {
    $sql .= " AND (nama_part LIKE ? OR kode_part LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($kategori !== '') {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
}
 $sql .= " ORDER BY kategori, nama_part";

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $parts = $stmt->fetchAll();

// Part untuk dropdown di modal (tambah/edit)
 $allParts = $pdo->query("SELECT id_part, kode_part, nama_part, stok, harga FROM spareparts ORDER BY nama_part")->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2 class="fw-bold mb-1">Inventaris Sparepart</h2>
<p class="text-secondary small mb-4">Kelola stok dan data sparepart bengkel</p>

<!-- Notifikasi stok kritis -->
<?php
 $kritis = $pdo->query("SELECT nama_part, stok FROM spareparts WHERE stok <= 2 ORDER BY stok ASC")->fetchAll();
if ($kritis): ?>
    <div class="alert alert-kritis mb-4">
        <strong><i class="bi bi-exclamation-triangle me-1"></i>Stok Kritis!</strong>
        <ul class="mb-0 mt-2 small">
            <?php foreach ($kritis as $k): ?>
                <li><?= htmlspecialchars($k['nama_part']) ?> — sisa <strong class="text-danger"><?= $k['stok'] ?></strong></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Search & Filter + Tombol Tambah -->
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <form method="GET" class="input-group">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama/kode..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
            <?php if ($search || $kategori): ?>
                <a href="<?= BASE_URL ?>admin/inventaris.php" class="btn btn-outline-danger btn-sm" title="Reset"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-md-3">
        <form method="GET" id="filterForm">
            <select name="kategori" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">Semua Kategori</option>
                <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= htmlspecialchars($kat) ?>" <?= $kategori === $kat ? 'selected' : '' ?>><?= htmlspecialchars($kat) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        </form>
    </div>
    <div class="col-md-5 text-md-end">
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg me-1"></i>Tambah Part
        </button>
    </div>
</div>

<!-- Tabel -->
<div class="table-responsive">
    <table class="table table-custom table-sm align-middle">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Part</th>
                <th>Kategori</th>
                <th>Stok</th>
                <th>Harga</th>
                <th class="text-center" style="width:120px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($parts)): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada data ditemukan.</td></tr>
            <?php endif; ?>
            <?php foreach ($parts as $p): ?>
            <tr>
                <td><code><?= htmlspecialchars($p['kode_part']) ?></code></td>
                <td><?= htmlspecialchars($p['nama_part']) ?></td>
                <td><span class="badge bg-dark border border-secondary"><?= htmlspecialchars($p['kategori'] ?: '-') ?></span></td>
                <td>
                    <span class="stok-dot <?= $p['stok'] >= 10 ? 'aman' : ($p['stok'] >= 3 ? 'terbatas' : 'kritis') ?>"></span>
                    <span class="<?= stokClass($p['stok']) ?> fw-semibold"><?= $p['stok'] ?></span>
                    <small class="text-secondary">(<?= stokLabel($p['stok']) ?>)</small>
                </td>
                <td><?= formatRupiah($p['harga']) ?></td>
                <td class="text-center">
                    <button class="btn btn-outline-warning btn-sm py-0 px-2 me-1" onclick="editPart(<?= $p['id_part'] ?>, '<?= htmlspecialchars(addslashes($p['kode_part'])) ?>', '<?= htmlspecialchars(addslashes($p['nama_part'])) ?>', '<?= htmlspecialchars(addslashes($p['kategori'] ?? '')) ?>', <?= $p['stok'] ?>, <?= $p['harga'] ?>)" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus part ini?')">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id_part" value="<?= $p['id_part'] ?>">
                        <button class="btn btn-outline-danger btn-sm py-0 px-2" title="Hapus"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-semibold">Tambah Sparepart</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="tambah">
                    <div class="mb-3">
                        <label class="form-label small">Kode Part</label>
                        <input type="text" name="kode_part" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Nama Part</label>
                        <input type="text" name="nama_part" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Kategori</label>
                        <input type="text" name="kategori" class="form-control form-control-sm" placeholder="Mis: Mesin, Cairan, Tuning">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Stok</label>
                            <input type="number" name="stok" class="form-control form-control-sm" value="0" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Harga (Rp)</label>
                            <input type="number" name="harga" class="form-control form-control-sm" value="0" min="0" step="1" required>
                        </div>
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

<!-- Modal Edit (diisi via JS) -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-semibold">Edit Sparepart</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_part" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label small">Kode Part</label>
                        <input type="text" name="kode_part" id="edit_kode" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Nama Part</label>
                        <input type="text" name="nama_part" id="edit_nama" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Kategori</label>
                        <input type="text" name="kategori" id="edit_kategori" class="form-control form-control-sm">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Stok</label>
                            <input type="number" name="stok" id="edit_stok" class="form-control form-control-sm" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Harga (Rp)</label>
                            <input type="number" name="harga" id="edit_harga" class="form-control form-control-sm" min="0" step="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-accent btn-sm">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Isi modal edit dengan data part
function editPart(id, kode, nama, kategori, stok, harga) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_kode').value = kode;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_kategori').value = kategori;
    document.getElementById('edit_stok').value = stok;
    document.getElementById('edit_harga').value = harga;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>