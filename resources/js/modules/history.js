import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const daftarRiwayat = document.getElementById('daftar-riwayat');

if (daftarRiwayat) {
    const tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const urlHapus = daftarRiwayat.dataset.deleteUrl;
    const urlHapusSemua = daftarRiwayat.dataset.deleteAllUrl;
    const pencarianRiwayat = document.getElementById('pencarian-riwayat');
    const filterRiwayat = document.getElementById('filter-riwayat');
    const urutanRiwayat = document.getElementById('urutan-riwayat');
    const tombolHapusSemua = document.getElementById('tombol-hapus-riwayat');
    const modalDetailRiwayat = document.getElementById('modal-detail-riwayat');

    let riwayat = [];
    let idRiwayatAktif = null;
    let sedangMenghapus = false;

    const konfirmasiPenghapusan = async ({ title, text, confirmButtonText }) => {
        if (sedangMenghapus) return false;

        sedangMenghapus = true;
        renderRiwayat();

        try {
            const result = await Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'swal-riwayat-popup',
                    title: 'swal-riwayat-title',
                    htmlContainer: 'swal-riwayat-text',
                    actions: 'swal-riwayat-actions',
                    confirmButton: 'swal-riwayat-confirm',
                    cancelButton: 'swal-riwayat-cancel',
                },
            });

            if (result.isConfirmed) return true;
        } catch {
            tampilkanNotifikasi('error', 'Konfirmasi tidak dapat ditampilkan', 'Silakan coba kembali.');
        }

        sedangMenghapus = false;
        renderRiwayat();

        return false;
    };

    const tampilkanNotifikasi = (icon, title, text = '') => Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        text,
        showConfirmButton: false,
        timer: 3600,
        timerProgressBar: true,
        customClass: {
            popup: 'swal-riwayat-toast',
        },
    });

    const pesanStatus = (status) => ({
        pending: 'Menunggu analisis',
        processing: 'Sedang dianalisis',
        completed: 'Selesai',
        failed: 'Gagal',
    }[status] || 'Status tidak diketahui');

    const labelHasil = (item) => {
        if (item.status !== 'completed') return pesanStatus(item.status);
        if (item.label === 'artificial') return 'Cenderung dihasilkan AI';
        if (item.label === 'human') return 'Cenderung dibuat manusia';
        return 'Hasil tidak tersedia';
    };

    const nilaiSkor = (item) =>
        typeof item.score === 'number' && Number.isFinite(item.score)
            ? `${(item.score * 100).toFixed(2)}%`
            : 'Tidak tersedia';

    const waktuAnalisis = (value) => {
        if (!value) return 'Waktu tidak tersedia';

        const date = new Date(value);
        return Number.isNaN(date.getTime())
            ? 'Waktu tidak tersedia'
            : new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
    };

    const kelasStatus = (item) => {
        if (item.status !== 'completed') return 'status-review';
        return item.label === 'artificial' ? 'status-ai' : 'status-human';
    };

    const aturModalDetail = (terbuka) => {
        if (!modalDetailRiwayat) return;

        modalDetailRiwayat.classList.toggle('is-open', terbuka);
        modalDetailRiwayat.hidden = !terbuka;
    };

    const buatIkon = () => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('class', 'icon');
        svg.setAttribute('viewBox', '0 0 24 24');

        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('x', '3');
        rect.setAttribute('y', '3');
        rect.setAttribute('width', '18');
        rect.setAttribute('height', '18');
        rect.setAttribute('rx', '3');
        svg.append(rect);

        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', '8.5');
        circle.setAttribute('cy', '8.5');
        circle.setAttribute('r', '1.5');
        svg.append(circle);

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'M21 15l-5-5L5 21');
        svg.append(path);

        return svg;
    };

    const buatTombolIkon = (kelas, title, label, pathData) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `icon-button ${kelas}`;
        button.title = title;
        button.setAttribute('aria-label', label);

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('class', 'icon icon-sm');
        svg.setAttribute('viewBox', '0 0 24 24');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathData);
        svg.append(path);
        button.append(svg);

        return button;
    };

    const buatKeadaanKosong = (title, message) => {
        const container = document.createElement('div');
        container.className = 'kosong-riwayat';
        const content = document.createElement('div');
        const heading = document.createElement('h2');
        heading.textContent = title;
        const description = document.createElement('p');
        description.textContent = message;
        content.append(heading, description);
        container.append(content);
        return container;
    };

    const bukaDetailRiwayat = (item) => {
        idRiwayatAktif = item.id;

        document.getElementById('judul-detail-riwayat').textContent = item.name;
        document.getElementById('label-hasil-detail').textContent = 'Hasil klasifikasi';
        document.getElementById('nilai-ai-detail').textContent = item.status === 'completed'
            ? (item.label === 'artificial' ? 'AI-generated' : 'Human')
            : pesanStatus(item.status);
        document.getElementById('nilai-manusia-detail').textContent = nilaiSkor(item);
        document.getElementById('status-detail-riwayat').textContent = pesanStatus(item.status);
        document.getElementById('tanggal-detail-riwayat').textContent = waktuAnalisis(item.date);
        document.getElementById('id-detail-riwayat').textContent = `#${String(item.id).slice(-6)}`;

        const gambar = document.getElementById('gambar-detail-riwayat');
        const pesanGambar = document.getElementById('pesan-gambar-detail-riwayat');
        gambar.onerror = () => {
            gambar.removeAttribute('src');
            gambar.hidden = true;
            pesanGambar.hidden = false;
        };

        if (item.imageUrl) {
            gambar.src = item.imageUrl;
            gambar.hidden = false;
            pesanGambar.hidden = true;
        } else {
            gambar.removeAttribute('src');
            gambar.hidden = true;
            pesanGambar.hidden = false;
        }

        aturModalDetail(true);
    };

    const buatBarisRiwayat = (item) => {
        const row = document.createElement('div');
        row.className = 'baris-riwayat';

        const file = document.createElement('div');
        file.className = 'file-riwayat';
        const icon = document.createElement('span');
        icon.className = 'ikon-file-riwayat';
        if (item.imageUrl) {
            const thumbnail = document.createElement('img');
            thumbnail.src = item.imageUrl;
            thumbnail.alt = '';
            thumbnail.loading = 'lazy';
            thumbnail.style.width = '100%';
            thumbnail.style.height = '100%';
            thumbnail.style.objectFit = 'cover';
            icon.append(thumbnail);
        } else {
            icon.append(buatIkon());
        }

        const metadata = document.createElement('div');
        metadata.style.minWidth = '0';
        const name = document.createElement('p');
        name.className = 'nama-file-riwayat';
        name.textContent = item.name;
        const date = document.createElement('p');
        date.className = 'tanggal-file-riwayat';
        date.textContent = `${waktuAnalisis(item.date)} · ID #${String(item.id).slice(-6)}`;
        metadata.append(name, date);
        file.append(icon, metadata);

        const result = document.createElement('span');
        result.className = `status-pill ${kelasStatus(item)}`;
        result.textContent = labelHasil(item);

        const scoreContainer = document.createElement('div');
        const scoreHeading = document.createElement('div');
        scoreHeading.className = 'kepala-skor-riwayat';
        const scoreLabel = document.createElement('span');
        scoreLabel.style.color = '#94a3b8';
        scoreLabel.textContent = 'Skor model';
        const scoreValue = document.createElement('strong');
        scoreValue.textContent = nilaiSkor(item);
        scoreHeading.append(scoreLabel, scoreValue);
        const track = document.createElement('div');
        track.className = 'lajur-skor-riwayat';
        const fill = document.createElement('div');
        fill.className = 'isi-skor-riwayat';
        const score = typeof item.score === 'number' ? Math.max(0, Math.min(1, item.score)) : 0;
        fill.style.width = `${score * 100}%`;
        track.append(fill);
        scoreContainer.append(scoreHeading, track);

        const actions = document.createElement('div');
        actions.className = 'aksi-riwayat';
        const viewButton = buatTombolIkon(
            'tombol-lihat-detail',
            'Lihat detail',
            `Lihat detail ${item.name}`,
            'M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z M12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        );
        viewButton.addEventListener('click', () => bukaDetailRiwayat(item));

        const deleteButton = buatTombolIkon(
            'tombol-hapus-item',
            'Hapus',
            `Hapus ${item.name}`,
            'M4 7h16M9 7V4h6v3m3 0l-1 14H7L6 7M10 11v6m4-6v6',
        );
        deleteButton.disabled = sedangMenghapus;
        deleteButton.addEventListener('click', () => hapusSatu(item));
        actions.append(viewButton, deleteButton);

        row.append(file, result, scoreContainer, actions);
        return row;
    };

    const renderRiwayat = () => {
        const keyword = (pencarianRiwayat?.value || '').trim().toLocaleLowerCase('id-ID');
        const filtered = riwayat
            .filter((item) => String(item.name || '').toLocaleLowerCase('id-ID').includes(keyword))
            .filter((item) => {
                if (!filterRiwayat || filterRiwayat.value === 'all') return true;
                if (filterRiwayat.value === 'ai') return item.status === 'completed' && item.label === 'artificial';
                if (filterRiwayat.value === 'human') return item.status === 'completed' && item.label === 'human';
                return item.status !== 'completed';
            });

        filtered.sort((a, b) => {
            if (urutanRiwayat?.value === 'highest') return (b.score ?? -1) - (a.score ?? -1);
            if (urutanRiwayat?.value === 'lowest') return (a.score ?? 2) - (b.score ?? 2);
            return Number(b.id) - Number(a.id);
        });

        const completed = riwayat.filter((item) => item.status === 'completed' && typeof item.score === 'number');
        const average = completed.length
            ? completed.reduce((sum, item) => sum + item.score, 0) / completed.length
            : 0;
        document.getElementById('total-analisis').textContent = String(riwayat.length);
        document.getElementById('analisis-ai').textContent = String(
            riwayat.filter((item) => item.status === 'completed' && item.label === 'artificial').length,
        );
        document.getElementById('rata-rata-skor').textContent = `${(average * 100).toFixed(2)}%`;
        if (tombolHapusSemua) {
            tombolHapusSemua.disabled = sedangMenghapus;
        }

        daftarRiwayat.replaceChildren();

        if (!filtered.length) {
            const empty = riwayat.length
                ? buatKeadaanKosong('Riwayat tidak ditemukan', 'Coba ubah kata kunci atau filter.')
                : buatKeadaanKosong('Belum ada riwayat', 'Hasil analisis Anda akan muncul di sini.');
            daftarRiwayat.append(empty);
            return;
        }

        filtered.forEach((item) => daftarRiwayat.append(buatBarisRiwayat(item)));
    };

    const hapusRequest = async (url) => {
        let response;
        try {
            response = await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': tokenCsrf || '',
                },
            });
        } catch {
            throw new Error('Terjadi kesalahan saat mengirim permintaan penghapusan. Periksa koneksi Anda lalu coba kembali.');
        }

        let payload = {};
        try {
            payload = await response.json();
        } catch {
            payload = {};
        }
        if (!response.ok) {
            throw new Error(payload.message || 'Riwayat belum dapat dihapus. Silakan coba kembali.');
        }
        return payload;
    };

    const hapusSatu = async (item) => {
        if (sedangMenghapus || !urlHapus) return;
        const dikonfirmasi = await konfirmasiPenghapusan({
            title: 'Hapus Riwayat Ini?',
            text: 'Apakah kamu yakin ingin menghapus riwayat analisis ini? Tindakan ini tidak dapat dibatalkan.',
            confirmButtonText: 'Ya, Hapus',
        });
        if (!dikonfirmasi) return;

        try {
            const payload = await hapusRequest(urlHapus.replace('__ID__', encodeURIComponent(item.id)));
            riwayat = riwayat.filter((entry) => entry.id !== item.id);
            if (idRiwayatAktif === item.id) aturModalDetail(false);
            renderRiwayat();
            tampilkanNotifikasi(
                payload.cleanup_complete === false ? 'warning' : 'success',
                payload.cleanup_complete === false ? 'Riwayat dihapus, file belum dibersihkan' : 'Riwayat berhasil dihapus',
                payload.message || '',
            );
        } catch (error) {
            tampilkanNotifikasi('error', 'Penghapusan gagal', error.message);
        } finally {
            sedangMenghapus = false;
            renderRiwayat();
        }
    };

    tombolHapusSemua?.addEventListener('click', async () => {
        if (sedangMenghapus || !urlHapusSemua) return;

        if (riwayat.length === 0) {
            await Swal.fire({
                title: 'Riwayat Kosong',
                text: 'Riwayat analisis Anda saat ini tidak ada atau sudah kosong.',
                icon: 'info',
                confirmButtonText: 'OK',
                buttonsStyling: false,
                customClass: {
                    popup: 'swal-riwayat-popup',
                    title: 'swal-riwayat-title',
                    htmlContainer: 'swal-riwayat-text',
                    actions: 'swal-riwayat-actions',
                    confirmButton: 'swal-riwayat-info-confirm',
                },
            });
            return;
        }

        const dikonfirmasi = await konfirmasiPenghapusan({
            title: 'Hapus Semua Riwayat?',
            text: 'Apakah kamu yakin ingin menghapus seluruh riwayat analisis? Tindakan ini tidak dapat dibatalkan.',
            confirmButtonText: 'Ya, Hapus Semua',
        });
        if (!dikonfirmasi) return;

        try {
            const payload = await hapusRequest(urlHapusSemua);
            riwayat = [];
            idRiwayatAktif = null;
            aturModalDetail(false);
            renderRiwayat();
            tampilkanNotifikasi(
                payload.cleanup_complete === false ? 'warning' : 'success',
                payload.cleanup_complete === false ? 'Riwayat dihapus, beberapa file belum dibersihkan' : 'Semua riwayat berhasil dihapus',
                payload.message || '',
            );
        } catch (error) {
            tampilkanNotifikasi('error', 'Penghapusan gagal', error.message);
        } finally {
            sedangMenghapus = false;
            renderRiwayat();
        }
    });

    [pencarianRiwayat, filterRiwayat, urutanRiwayat].forEach((element) =>
        element?.addEventListener('input', renderRiwayat),
    );
    filterRiwayat?.addEventListener('change', renderRiwayat);
    urutanRiwayat?.addEventListener('change', renderRiwayat);

    document.getElementById('tombol-tutup-detail')?.addEventListener('click', () => aturModalDetail(false));
    modalDetailRiwayat?.addEventListener('mousedown', (event) => {
        if (event.target === event.currentTarget) aturModalDetail(false);
    });

    document.getElementById('tombol-simpan-laporan')?.addEventListener('click', () => {
        if (idRiwayatAktif === null) return;
        const cleanup = () => document.body.classList.remove('cetak-laporan-riwayat');
        window.addEventListener('afterprint', cleanup, { once: true });
        document.body.classList.add('cetak-laporan-riwayat');
        window.print();
    });

    try {
        const parsed = JSON.parse(daftarRiwayat.dataset.analyses || '[]');
        riwayat = Array.isArray(parsed) ? parsed : [];
        renderRiwayat();
    } catch {
        daftarRiwayat.replaceChildren(
            buatKeadaanKosong('Riwayat belum dapat dimuat', 'Muat ulang halaman untuk mencoba kembali.'),
        );
    }
}
