/**
 * E-TMotor — Client-side JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // =========================================
    // Cek slot reservasi (fetch API)
    // Digunakan di pelanggan/dashboard.php
    // =========================================
    const dateInput = document.getElementById('tgl_reservasi');
    const slotContainer = document.getElementById('slot_container');
    const jamInput = document.getElementById('jam_reservasi'); // hidden input

    const serviceInput = document.getElementById('id_layanan');

    if (dateInput && slotContainer) {
        const resetSlots = () => {
            slotContainer.innerHTML = '<p class="text-secondary small">Pilih tanggal terlebih dahulu.</p>';
            if (jamInput) jamInput.value = '';
        };

        dateInput.addEventListener('change', function () {
            const tanggal = this.value;
            if (!tanggal) {
                resetSlots();
                return;
            }

            // Validasi: tidak boleh tanggal kemarin
            const today = new Date().toISOString().split('T')[0];
            if (tanggal < today) {
                slotContainer.innerHTML = '<p class="text-danger small">Tidak bisa memilih tanggal yang sudah lewat.</p>';
                if (jamInput) jamInput.value = '';
                return;
            }

            slotContainer.innerHTML = '<div class="text-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Mengecek slot...</div>';

            fetch(BASE_URL + 'pelanggan/dashboard.php?action=cek_slot&tanggal=' + encodeURIComponent(tanggal))
                .then(res => res.json())
                .then(data => {
                    if (!data.slots || data.slots.length === 0) {
                        slotContainer.innerHTML = '<p class="text-secondary small">Tidak ada slot tersedia.</p>';
                        return;
                    }

                    let html = '<div class="d-flex flex-wrap gap-2">';
                    data.slots.forEach(function (s) {
                        const isFull = s.terisi >= 3;
                        const cls = isFull ? 'slot-btn full' : 'slot-btn available';
                        const disabled = isFull ? 'disabled' : '';
                        const sisa = isFull ? 'Penuh' : (3 - s.terisi) + ' sisa';
                        html += `<button type="button" class="btn btn-outline-secondary ${cls}" 
                                    data-jam="${s.jam}" ${disabled}
                                    title="${s.terisi}/3 terisi — ${sisa}">
                                    ${s.jam}<br><small>${sisa}</small>
                                 </button>`;
                    });
                    html += '</div>';
                    slotContainer.innerHTML = html;

                    // Event listener untuk pilih slot
                    slotContainer.querySelectorAll('.slot-btn.available').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            slotContainer.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
                            this.classList.add('active');
                            if (jamInput) jamInput.value = this.dataset.jam;
                        });
                    });
                })
                .catch(() => {
                    slotContainer.innerHTML = '<p class="text-danger small">Gagal memuat slot. Coba lagi.</p>';
                });
        });

        if (serviceInput) {
            serviceInput.addEventListener('change', resetSlots);
        }
    }

    // =========================================
    // Dynamic tambah baris sparepart (modal selesaikan servis)
    // =========================================
    const addPartBtn = document.getElementById('btn_add_part_row');
    const partRows = document.getElementById('part_rows');

    if (addPartBtn && partRows) {
        addPartBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 align-items-center part-row';
            row.innerHTML = `
                <div class="col-md-6">
                    <select name="parts[]" class="form-select form-select-sm" required>
                        <option value="">-- Pilih Part --</option>
                        ${window.availableParts || ''}
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="jumlah[]" class="form-control form-control-sm" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest('.part-row').remove()">
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </div>
            `;
            partRows.appendChild(row);
        });
    }

    // =========================================
    // Konfirmasi pembayaran
    // =========================================
    document.querySelectorAll('.btn-konfirmasi-bayar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const idServis = this.dataset.id;
            const metode = document.getElementById('metode_' + idServis);
            if (!metode || !metode.value) {
                alert('Pilih metode pembayaran terlebih dahulu.');
                return;
            }
            if (!confirm('Konfirmasi pembayaran via ' + metode.value.toUpperCase() + '?')) return;

            const formData = new FormData();
            formData.append('action', 'bayar');
            formData.append('id_servis', idServis);
            formData.append('metode_bayar', metode.value);

            fetch(BASE_URL + 'pelanggan/dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Gagal memproses pembayaran.');
                }
            })
            .catch(() => alert('Terjadi kesalahan jaringan.'));
        });
    });
});