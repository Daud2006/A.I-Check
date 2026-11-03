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
const teksTombolAnalisis = document.getElementById('teks-tombol-analisis');
const tombolUnggahBaru = document.getElementById('tombol-unggah-baru');
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

    if (tombolUnggahBaru) tombolUnggahBaru.hidden = true;
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
    const hasil = {
        'AI-GENERATED': {
            interpretasi: 'Model memberi kategori AI-GENERATED. Ini merupakan indikasi dari prediksi model, bukan bukti independen bahwa gambar dibuat menggunakan AI.',
            kelas: 'status-ai',
        },
        'AI-EDITED': {
            interpretasi: 'Model memberi kategori AI-EDITED. Ini merupakan indikasi dari prediksi model, bukan bukti independen bahwa gambar telah diedit menggunakan AI.',
            kelas: 'status-ai',
        },
        REAL: {
            interpretasi: 'Model memberi kategori REAL. Prediksi ini tidak membuktikan bahwa gambar autentik atau tidak pernah dimanipulasi.',
            kelas: 'status-real',
        },
        UNCERTAIN: {
            interpretasi: 'Model memberi kategori UNCERTAIN, sehingga kategori gambar belum dapat ditentukan dari hasil prediksi ini.',
            kelas: 'status-uncertain',
        },
    }[payload.result_label];

    const scoreAI = payload.confidence_score;

    if (
        !hasil
        || typeof scoreAI !== 'number'
        || !Number.isFinite(scoreAI)
        || scoreAI < 0
        || scoreAI > 1
    ) {
        tampilkanPesan('Hasil dari layanan analisis tidak dapat diverifikasi.', { error: true });
        return false;
    }

    if (pesanChecker) {
        pesanChecker.textContent = '';
        pesanChecker.hidden = true;
        pesanChecker.classList.remove('is-error');
    }

    const heading = document.createElement('h2');
    heading.className = 'judul-hasil-analisis';
    heading.textContent = 'Hasil Deteksi';

    const badge = document.createElement('span');
    badge.className = `status-pill ${hasil.kelas}`;
    badge.textContent = payload.result_label;

    const skor = document.createElement('div');
    skor.className = 'probabilitas-hasil';
    const labelSkor = document.createElement('span');
    labelSkor.textContent = 'Probabilitas AI';
    const nilaiSkor = document.createElement('strong');
    nilaiSkor.textContent = `${(scoreAI * 100).toFixed(2)}%`;
    skor.append(labelSkor, nilaiSkor);

    const judulKeterangan = document.createElement('h3');
    judulKeterangan.className = 'judul-keterangan-analisis';
    judulKeterangan.textContent = 'Keterangan Analisis';

    const buatBagianKeterangan = (judul, isi) => {
        const bagian = document.createElement('section');
        bagian.className = 'bagian-keterangan-analisis';

        const subjudul = document.createElement('h4');
        subjudul.textContent = judul;

        const keterangan = document.createElement('p');
        keterangan.textContent = isi;

        bagian.append(subjudul, keterangan);
        return bagian;
    };

    const bagianHasilModel = buatBagianKeterangan(
        'A. Hasil model',
        `${payload.result_label} — Probabilitas AI ${nilaiSkor.textContent}.`,
    );
    const bagianBukti = buatBagianKeterangan(
        'B. Bukti pendukung',
        'Sistem saat ini tidak memeriksa metadata EXIF maupun Content Credentials (C2PA). Bukti metadata atau provenance belum diperiksa; ini tidak berarti informasi tersebut tidak ada pada gambar.',
    );
    const bagianInterpretasi = buatBagianKeterangan(
        'C. Interpretasi',
        hasil.interpretasi,
    );
    const bagianKeterbatasan = buatBagianKeterangan(
        'D. Keterbatasan',
        'Probabilitas AI adalah estimasi model, bukan tingkat kepastian atau akurasi hasil. Model dapat keliru; tanpa pemeriksaan metadata dan provenance, keduanya tidak dapat digunakan untuk menguatkan atau menyangkal prediksi ini.',
    );

    hasilAnalisis?.replaceChildren(
        heading,
        badge,
        skor,
        judulKeterangan,
        bagianHasilModel,
        bagianBukti,
        bagianInterpretasi,
        bagianKeterbatasan,
    );
    if (hasilAnalisis) hasilAnalisis.hidden = false;
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = true;
    if (tombolUnggahBaru) tombolUnggahBaru.hidden = false;

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
    if (teksTombolAnalisis) {
        teksTombolAnalisis.textContent = unggahanSelesai
            ? 'Analisis Selesai'
            : fileTerpilih
                ? 'Periksa'
                : 'Unggah Gambar';
    }
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
    if (tombolUnggahBaru) tombolUnggahBaru.hidden = true;
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
        resetPemeriksaan();
        tampilkanPesan('Format gambar yang didukung adalah JPEG, PNG, dan WebP.', { error: true });
        return;
    }

    if (file.size > ukuranMaksimal) {
        resetPemeriksaan();
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
    if (tombolUnggahBaru) tombolUnggahBaru.hidden = true;
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
            const responsePayload = JSON.parse(request.responseText);
            if (responsePayload && typeof responsePayload === 'object' && !Array.isArray(responsePayload)) {
                payload = responsePayload;
            }
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
tombolUnggahBaru?.addEventListener('click', () => {
    resetPemeriksaan();
    inputGambar?.click();
});

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
