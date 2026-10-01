// Mengatur daftar riwayat, filter, statistik, dan modal detail untuk melihat hasil analisis sebelumnya.
const daftarRiwayat = document.getElementById('daftar-riwayat');
if (daftarRiwayat) {
    const contohRiwayat = [
        { id: 1, name: 'poster-kampanye.jpg', date: 'Hari ini, 10:42', aiScore: 92, humanScore: 8, label: 'Kemungkinan AI' },
        { id: 2, name: 'festival-musik.png', date: 'Kemarin, 16:20', aiScore: 18, humanScore: 82, label: 'Kemungkinan asli' },
        { id: 3, name: 'promo-produk.webp', date: '12 Mei 2025', aiScore: 64, humanScore: 36, label: 'Perlu ditinjau' },
    ];

    let riwayat = JSON.parse(localStorage.getItem('verifai-history') || 'null') || contohRiwayat;
    const pencarianRiwayat = document.getElementById('pencarian-riwayat');
    const filterRiwayat = document.getElementById('filter-riwayat');
    const urutanRiwayat = document.getElementById('urutan-riwayat');
    const tombolHapusSemua = document.getElementById('tombol-hapus-riwayat');

    const kelasStatus = (skor) => skor >= 75 ? 'status-ai' : skor >= 45 ? 'status-review' : 'status-human';

    const renderRiwayat = () => {
        const kataKunci = (pencarianRiwayat?.value || '').toLowerCase();
        const hasilFilter = riwayat.filter((item) => item.name.toLowerCase().includes(kataKunci)).filter((item) => filterRiwayat?.value === 'all' || (filterRiwayat?.value === 'ai' && item.aiScore >= 75) || (filterRiwayat?.value === 'review' && item.aiScore >= 45 && item.aiScore < 75) || (filterRiwayat?.value === 'human' && item.aiScore < 45));
        hasilFilter.sort((a, b) => urutanRiwayat?.value === 'highest' ? b.aiScore - a.aiScore : urutanRiwayat?.value === 'lowest' ? a.aiScore - b.aiScore : b.id - a.id);
        const rataRata = riwayat.length ? Math.round(riwayat.reduce((jumlah, item) => jumlah + item.aiScore, 0) / riwayat.length) : 0;

        document.getElementById('total-analisis').textContent = riwayat.length;
        document.getElementById('analisis-ai').textContent = riwayat.filter((item) => item.aiScore >= 75).length;
        document.getElementById('rata-rata-skor').textContent = `${rataRata}%`;
        tombolHapusSemua.hidden = riwayat.length === 0;

        if (!hasilFilter.length) {
            daftarRiwayat.innerHTML = `<div class="kosong-riwayat"><div><span class="ikon-riwayat-kosong"><svg aria-hidden="true" class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><h2>${riwayat.length ? 'Riwayat tidak ditemukan' : 'Belum ada riwayat'}</h2><p>${riwayat.length ? 'Coba ubah kata kunci atau filter.' : 'Hasil analisis Anda akan muncul di sini.'}</p></div></div>`;
            return;
        }

        daftarRiwayat.innerHTML = hasilFilter.map((item) => `<div class="baris-riwayat"><div class="file-riwayat"><span class="ikon-file-riwayat"><svg aria-hidden="true" class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></span><div style="min-width:0"><p class="nama-file-riwayat">${item.name}</p><p class="tanggal-file-riwayat">${item.date} · ID #${String(item.id).slice(-6)}</p></div></div><span class="status-pill ${kelasStatus(item.aiScore)}">${item.label}</span><div><div class="kepala-skor-riwayat"><span style="color:#94a3b8">Skor AI</span><strong>${item.aiScore}%</strong></div><div class="lajur-skor-riwayat"><div class="isi-skor-riwayat" style="width:${item.aiScore}%"></div></div></div><div class="aksi-riwayat"><button type="button" class="icon-button tombol-lihat-detail" data-id="${item.id}" title="Lihat detail"><svg aria-hidden="true" class="icon icon-sm" viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg></button><button type="button" class="icon-button tombol-hapus-item" data-id="${item.id}" title="Hapus"><svg aria-hidden="true" class="icon icon-sm" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m3 0l-1 14H7L6 7M10 11v6m4-6v6"/></svg></button></div></div>`).join('');

        daftarRiwayat.querySelectorAll('.tombol-lihat-detail').forEach((button) => button.addEventListener('click', () => bukaDetailRiwayat(Number(button.dataset.id))));
        daftarRiwayat.querySelectorAll('.tombol-hapus-item').forEach((button) => button.addEventListener('click', () => {
            riwayat = riwayat.filter((item) => item.id !== Number(button.dataset.id));
            localStorage.setItem('verifai-history', JSON.stringify(riwayat));
            renderRiwayat();
        }));
    };

    const modalDetailRiwayat = document.getElementById('modal-detail-riwayat');

    const aturModalDetail = (terbuka) => {
        if (!modalDetailRiwayat) return;
        modalDetailRiwayat.classList.toggle('is-open', terbuka);
        modalDetailRiwayat.hidden = !terbuka;
    };

    const bukaDetailRiwayat = (id) => {
        const item = riwayat.find((entry) => entry.id === id);
        if (!item) return;
        document.getElementById('judul-detail-riwayat').textContent = item.name;
        document.getElementById('nilai-ai-detail').textContent = `${item.aiScore}%`;
        document.getElementById('nilai-manusia-detail').textContent = `${item.humanScore}%`;
        document.getElementById('status-detail-riwayat').textContent = item.label;
        document.getElementById('tanggal-detail-riwayat').textContent = item.date;
        document.getElementById('id-detail-riwayat').textContent = `#${String(item.id).slice(-6)}`;
        aturModalDetail(true);
    };

    [pencarianRiwayat, filterRiwayat, urutanRiwayat].forEach((elemen) => elemen?.addEventListener('input', renderRiwayat));
    tombolHapusSemua?.addEventListener('click', () => {
        if (window.confirm('Hapus seluruh riwayat analisis?')) {
            riwayat = [];
            localStorage.removeItem('verifai-history');
            renderRiwayat();
        }
    });
    document.getElementById('tombol-tutup-detail')?.addEventListener('click', () => aturModalDetail(false));
    modalDetailRiwayat?.addEventListener('mousedown', (event) => { if (event.target === event.currentTarget) aturModalDetail(false); });
    renderRiwayat();
}
