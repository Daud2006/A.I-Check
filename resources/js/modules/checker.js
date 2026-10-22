// Unggah gambar dan tampilkan hanya hasil yang dikembalikan backend.
const inputGambar = document.getElementById('input-gambar');
const tombolPilihGambar = document.getElementById('tombol-pilih-gambar');
const areaDropGambar = document.getElementById('area-drop-gambar');
const tampilanUpload = document.getElementById('kotak-upload');
const tampilanHasil = document.getElementById('tampilan-hasil');
const gambarPratinjau = document.getElementById('gambar-pratinjau');
const areaPratinjau = document.getElementById('area-pratinjau');
const namaFile = document.getElementById('nama-file');
const ukuranFile = document.getElementById('ukuran-file');
const tombolHapusGambar = document.getElementById('tombol-hapus-gambar');
const tombolAnalisis = document.getElementById('tombol-analisis');
const kemajuanAnalisis = document.getElementById('kemajuan-analisis');
const isiKemajuan = document.getElementById('isi-kemajuan');
const lajurProgres = document.querySelector('.lajur-progres[role="progressbar"]');
const nilaiKemajuan = document.getElementById('nilai-kemajuan');
const statusKemajuan = document.getElementById('status-kemajuan');
const areaSiapAnalisis = document.getElementById('area-siap-analisis');
const hasilAnalisis = document.getElementById('hasil-analisis');
const pesanChecker = document.getElementById('pesan-checker');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

const ukuranMaksimal = 10 * 1024 * 1024;
const mimeDidukung = ['image/jpeg', 'image/png', 'image/webp'];
let fileTerpilih = null;
let urlPratinjau = '';
let unggahanBerlangsung = false;

const elemenMarker = areaPratinjau
    ? [...areaPratinjau.querySelectorAll('.titik-marker')]
    : [];

const aturTampilanUpload = (terlihat) => {
    if (!tampilanUpload) return;

    tampilanUpload.hidden = !terlihat;
    tampilanUpload.classList.toggle('is-hidden', !terlihat);
};

const aturTampilanHasil = (terlihat) => {
    if (!tampilanHasil) return;

    tampilanHasil.hidden = !terlihat;
    tampilanHasil.classList.toggle('is-visible', terlihat);
};

const tampilkanPesan = (pesan, { error = false, tautanLogin = false } = {}) => {
    if (pesanChecker) {
        pesanChecker.textContent = pesan;
        pesanChecker.hidden = false;
        pesanChecker.classList.toggle('is-error', error);
    }

    if (!hasilAnalisis) return;

    hasilAnalisis.replaceChildren();
    const pesanElement = document.createElement('p');
    pesanElement.className = error ? 'checker-message-error' : 'checker-message-success';
    pesanElement.setAttribute('role', error ? 'alert' : 'status');
    pesanElement.textContent = pesan;
    hasilAnalisis.append(pesanElement);

    if (tautanLogin && inputGambar?.dataset.authUrl) {
        const loginLink = document.createElement('a');
        loginLink.href = inputGambar.dataset.authUrl;
        loginLink.className = 'btn btn-primary';
        loginLink.textContent = 'Masuk ke akun';
        hasilAnalisis.append(loginLink);
    }

    if (areaSiapAnalisis) areaSiapAnalisis.hidden = true;
    hasilAnalisis.hidden = false;
};

const tampilkanPrediksi = (payload) => {
    const label = {
        artificial: 'Cenderung dihasilkan oleh AI',
        human: 'Cenderung dibuat manusia',
    }[payload.result_label];
    const confidence = payload.confidence_score;

    if (!label || typeof confidence !== 'number' || !Number.isFinite(confidence) || confidence < 0 || confidence > 1) {
        tampilkanPesan('Hasil dari layanan analisis tidak dapat diverifikasi.', { error: true });
        return false;
    }

    tampilkanPesan(`${label}. Skor model: ${(confidence * 100).toFixed(2)}%.`);
    const catatan = document.createElement('p');
    catatan.className = 'deskripsi-panel';
    catatan.textContent = 'Prediksi model dapat keliru dan bukan bukti pasti. Hasil sebaiknya ditinjau bersama informasi lain.';
    hasilAnalisis?.append(catatan);

    return true;
};

const aturProgres = (nilai) => {
    if (isiKemajuan) isiKemajuan.style.width = `${nilai}%`;
    if (nilaiKemajuan) nilaiKemajuan.textContent = `${nilai}%`;
    if (lajurProgres) lajurProgres.setAttribute('aria-valuenow', String(nilai));
};

const aturStatusTombol = (unggahanSelesai = false) => {
    if (!tombolAnalisis) return;

    tombolAnalisis.disabled = unggahanBerlangsung || unggahanSelesai;
    tombolAnalisis.textContent = unggahanSelesai ? 'Analisis Selesai' : 'Unggah Gambar';
};

const resetPemeriksaan = () => {
    if (unggahanBerlangsung) return;

    fileTerpilih = null;
    if (urlPratinjau) URL.revokeObjectURL(urlPratinjau);
    urlPratinjau = '';
    if (gambarPratinjau) gambarPratinjau.removeAttribute('src');
    if (inputGambar) inputGambar.value = '';
    if (namaFile) namaFile.textContent = '-';
    if (ukuranFile) ukuranFile.textContent = '-';
    if (pesanChecker) {
        pesanChecker.textContent = '';
        pesanChecker.hidden = true;
        pesanChecker.classList.remove('is-error');
    }
    if (kemajuanAnalisis) kemajuanAnalisis.hidden = true;
    if (kemajuanAnalisis) kemajuanAnalisis.setAttribute('aria-busy', 'false');
    if (statusKemajuan) statusKemajuan.textContent = 'Mengunggah gambar...';
    if (hasilAnalisis) {
        hasilAnalisis.replaceChildren();
        hasilAnalisis.hidden = true;
    }
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = false;
    elemenMarker.forEach((marker) => { marker.hidden = true; });
    aturProgres(0);
    aturStatusTombol();
    aturTampilanUpload(true);
    aturTampilanHasil(false);
};

const pilihFile = (file) => {
    if (!file) return;

    if (!mimeDidukung.includes(file.type)) {
        tampilkanPesan('Format gambar yang didukung adalah JPEG, PNG, dan WebP.', { error: true });
        return;
    }

    if (file.size > ukuranMaksimal) {
        tampilkanPesan('Ukuran gambar maksimal 10 MB.', { error: true });
        return;
    }

    fileTerpilih = file;
    if (urlPratinjau) URL.revokeObjectURL(urlPratinjau);
    urlPratinjau = URL.createObjectURL(file);
    if (gambarPratinjau) gambarPratinjau.src = urlPratinjau;
    if (namaFile) namaFile.textContent = file.name;
    if (ukuranFile) ukuranFile.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
    if (pesanChecker) {
        pesanChecker.textContent = '';
        pesanChecker.hidden = true;
    }
    if (hasilAnalisis) {
        hasilAnalisis.replaceChildren();
        hasilAnalisis.hidden = true;
    }
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = false;
    aturStatusTombol();
    aturTampilanUpload(false);
    aturTampilanHasil(true);
};

const unggahGambar = () => {
    if (!fileTerpilih || !inputGambar?.dataset.uploadUrl || unggahanBerlangsung) return;

    const data = new FormData();
    data.append('image', fileTerpilih);
    const request = new XMLHttpRequest();

    unggahanBerlangsung = true;
    aturStatusTombol();
    if (pesanChecker) pesanChecker.hidden = true;
    if (kemajuanAnalisis) kemajuanAnalisis.hidden = false;
    if (kemajuanAnalisis) kemajuanAnalisis.setAttribute('aria-busy', 'true');
    if (statusKemajuan) statusKemajuan.textContent = 'Mengunggah gambar...';
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = false;
    if (hasilAnalisis) hasilAnalisis.hidden = true;
    aturProgres(0);

    request.open('POST', inputGambar.dataset.uploadUrl);
    request.timeout = 75000;
    request.setRequestHeader('Accept', 'application/json');
    if (csrfToken) request.setRequestHeader('X-CSRF-TOKEN', csrfToken);

    request.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
            aturProgres(Math.min(99, Math.round((event.loaded / event.total) * 100)));
        }
    });

    request.upload.addEventListener('load', () => {
        if (statusKemajuan) statusKemajuan.textContent = 'Gambar diterima. Menunggu analisis AI...';
    });

    request.addEventListener('load', () => {
        let payload = {};
        try {
            payload = JSON.parse(request.responseText);
        } catch {
            payload = {};
        }

        if (request.status === 201 && payload.status === 'completed') {
            aturProgres(100);
            const predictionIsValid = tampilkanPrediksi(payload);
            if (kemajuanAnalisis) kemajuanAnalisis.hidden = true;
            aturStatusTombol(predictionIsValid);
            return;
        }

        const validationMessage = payload.errors?.image?.[0];
        if (request.status === 422) {
            tampilkanPesan(validationMessage || payload.message || 'File gambar tidak valid.', { error: true });
        } else if (request.status === 401) {
            tampilkanPesan('Silakan masuk sebelum mengunggah gambar.', { error: true, tautanLogin: true });
        } else {
            tampilkanPesan(payload.message || 'Gambar gagal diunggah. Silakan coba kembali.', { error: true });
        }
    });

    request.addEventListener('error', () => {
        tampilkanPesan('Koneksi gagal. Periksa jaringan Anda lalu coba kembali.', { error: true });
    });

    request.addEventListener('timeout', () => {
        tampilkanPesan('Analisis memerlukan waktu terlalu lama. Silakan coba kembali.', { error: true });
    });

    request.addEventListener('loadend', () => {
        unggahanBerlangsung = false;
        if (kemajuanAnalisis) kemajuanAnalisis.setAttribute('aria-busy', 'false');
        if (request.status !== 201) aturStatusTombol();
        if (kemajuanAnalisis && request.status !== 201) kemajuanAnalisis.hidden = true;
    });

    request.send(data);
};

tombolPilihGambar?.addEventListener('click', () => inputGambar?.click());
inputGambar?.addEventListener('change', () => pilihFile(inputGambar.files?.[0]));
tombolHapusGambar?.addEventListener('click', resetPemeriksaan);
tombolAnalisis?.addEventListener('click', unggahGambar);

areaDropGambar?.addEventListener('dragover', (event) => {
    event.preventDefault();
    tampilanUpload?.classList.add('is-dragging');
});
areaDropGambar?.addEventListener('dragleave', () => tampilanUpload?.classList.remove('is-dragging'));
areaDropGambar?.addEventListener('drop', (event) => {
    event.preventDefault();
    tampilanUpload?.classList.remove('is-dragging');
    pilihFile(event.dataTransfer?.files?.[0]);
});
