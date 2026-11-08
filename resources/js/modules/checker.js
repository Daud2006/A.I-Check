// Unggah gambar dan tampilkan hanya hasil yang dikembalikan backend.
import Swal from 'sweetalert2';

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

    if (tautanLogin && inputGambar?.dataset.loginUrl) {
        const loginLink = document.createElement('a');
        loginLink.href = inputGambar.dataset.loginUrl;
        loginLink.className = 'btn btn-primary';
        loginLink.textContent = 'Masuk ke akun';
        hasilAnalisis.append(loginLink);
    }

    if (areaSiapAnalisis) areaSiapAnalisis.hidden = true;
    hasilAnalisis.hidden = false;
};

const tampilkanKesalahanAnalisis = () => {
    if (!hasilAnalisis) return;

    const heading = document.createElement('h3');
    heading.className = 'judul-keterangan-analisis';
    heading.textContent = 'Mengapa Gambar Ini Terdeteksi Demikian?';

    const message = document.createElement('p');
    message.textContent = 'Analisis tidak dapat diselesaikan karena terjadi kesalahan. Tidak ada kesimpulan AI atau REAL yang dapat diberikan. Silakan coba kembali.';

    const section = document.createElement('section');
    section.className = 'bagian-keterangan-analisis';
    section.append(heading, message);

    hasilAnalisis.replaceChildren(section);
    hasilAnalisis.hidden = false;
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = true;
    if (tombolUnggahBaru) tombolUnggahBaru.hidden = false;
};

const tampilkanPopupLimitGuest = async () => {
    const result = await Swal.fire({
        title: 'Kesempatan Deteksi Gambar Habis!',
        text: 'Batas Deteksi Gambar tanpa login telah tercapai. Silakan buat akun atau login untuk melanjutkan Deteksi Gambar dan menikmati fitur A.I-Check.',
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Login',
        denyButtonText: 'Buat Akun',
        cancelButtonText: 'Tidak',
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'swal-riwayat-popup',
            title: 'swal-riwayat-title',
            htmlContainer: 'swal-riwayat-text',
            actions: 'swal-riwayat-actions',
            confirmButton: 'swal-riwayat-confirm',
            denyButton: 'swal-riwayat-deny',
            cancelButton: 'swal-riwayat-cancel',
        },
    });

    if (result.isConfirmed && inputGambar?.dataset.loginUrl) {
        window.location.assign(inputGambar.dataset.loginUrl);
    } else if (result.isDenied && inputGambar?.dataset.registerUrl) {
        window.location.assign(inputGambar.dataset.registerUrl);
    }
};

const tampilkanPopupLimitFree = async () => {
    const result = await Swal.fire({
        title: 'Batas Deteksi Gambar Tercapai!',
        text: 'Anda telah habis batas Deteksi Gambar dengan akun Free (Gratis). Silakan berlangganan atau tunggu hingga besok hari untuk dapat melakukan Deteksi Gambar kembali.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Berlangganan',
        cancelButtonText: 'Tidak',
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'swal-riwayat-popup',
            title: 'swal-riwayat-title',
            htmlContainer: 'swal-riwayat-text',
            actions: 'swal-riwayat-actions',
            confirmButton: 'swal-riwayat-info-confirm',
            cancelButton: 'swal-riwayat-cancel',
        },
    });

    if (result.isConfirmed) {
        document.querySelector('[data-subscribe-open]')?.click();
    }
};

const tampilkanPrediksi = (payload) => {
    if (payload.result_label === 'ERROR') {
        tampilkanKesalahanAnalisis();
        return false;
    }

    const hasil = {
        'AI-GENERATED': {
            interpretasi: 'AI Engine memberikan verdict AI-GENERATED. Ini merupakan prediksi model, bukan bukti mutlak bahwa gambar dibuat menggunakan AI.',
            kelas: 'status-ai',
        },
        'AI-EDITED': {
            interpretasi: 'AI Engine memberikan verdict AI-EDITED. Kategori ini mengikuti hasil engine dan bukan bukti mutlak tentang proses pengeditan gambar.',
            kelas: 'status-ai',
        },
        REAL: {
            interpretasi: 'AI Engine memberikan verdict REAL dengan skor probabilitas AI yang ditampilkan. Hasil ini tidak membuktikan bahwa gambar pasti diambil dengan kamera atau tidak pernah diedit.',
            kelas: 'status-real',
        },
        UNCERTAIN: {
            interpretasi: 'AI Engine memberikan verdict UNCERTAIN, sehingga hasil belum cukup meyakinkan untuk menentukan apakah gambar dibuat AI atau berasal dari sumber asli.',
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

    const evidence = payload.engine_evidence && typeof payload.engine_evidence === 'object'
        ? payload.engine_evidence
        : {};
    const modelScores = evidence.per_model && typeof evidence.per_model === 'object'
        ? Object.entries(evidence.per_model).filter(([, value]) => typeof value === 'number' && Number.isFinite(value))
        : [];
    const kalimatDasar = {
        'AI-GENERATED': `Gambar ini terindikasi dibuat menggunakan AI. Probabilitas AI dari model adalah ${nilaiSkor.textContent}. Hasil ini merupakan prediksi, bukan bukti mutlak.`,
        'AI-EDITED': `Gambar ini mendapat kategori AI-EDITED dari AI Engine dengan probabilitas AI ${nilaiSkor.textContent}. Kategori ini mengikuti verdict engine dan bukan bukti mutlak tentang proses pengeditan.`,
        REAL: `Gambar ini terindikasi sebagai gambar asli (Human/Real). Probabilitas AI yang diberikan model adalah ${nilaiSkor.textContent}. Hasil ini bukan jaminan bahwa gambar pasti diambil menggunakan kamera atau tidak pernah diedit.`,
        UNCERTAIN: `Hasil Deteksi Gambar belum cukup meyakinkan. AI Engine memberikan probabilitas AI ${nilaiSkor.textContent}; disarankan melakukan pemeriksaan tambahan.`,
    }[payload.result_label];

    const penjelasan = [kalimatDasar];
    if (typeof evidence.threshold === 'number') {
        penjelasan.push(`Threshold AI Engine: ${(evidence.threshold * 100).toFixed(2)}%.`);
    }
    if (typeof evidence.low_threshold === 'number') {
        penjelasan.push(`Batas bawah rentang uncertain: ${(evidence.low_threshold * 100).toFixed(2)}%.`);
    }
    if (
        payload.result_label === 'AI-GENERATED'
        && evidence.metadata_says_ai !== true
        && typeof evidence.threshold === 'number'
        && scoreAI >= evidence.threshold
    ) {
        penjelasan.push('Skor probabilitas AI mencapai threshold yang digunakan engine.');
    } else if (
        payload.result_label === 'REAL'
        && typeof evidence.low_threshold === 'number'
        && scoreAI <= evidence.low_threshold
    ) {
        penjelasan.push('Skor probabilitas AI berada pada atau di bawah batas bawah engine, yang mendukung verdict REAL.');
    } else if (
        payload.result_label === 'UNCERTAIN'
        && typeof evidence.low_threshold === 'number'
        && typeof evidence.threshold === 'number'
        && scoreAI > evidence.low_threshold
        && scoreAI < evidence.threshold
    ) {
        penjelasan.push('Skor berada di antara batas bawah dan threshold engine, sehingga hasil masuk rentang uncertain.');
    }
    if (typeof evidence.basis === 'string' && evidence.basis.trim() !== '') {
        penjelasan.push(`Dasar yang dilaporkan AI Engine: ${evidence.basis}`);
    }
    if (evidence.metadata_says_ai === true) {
        penjelasan.push('Metadata provenance yang diperiksa AI Engine mendukung indikasi AI.');
    }
    if (typeof evidence.metadata_generator === 'string' && evidence.metadata_generator.trim() !== '') {
        penjelasan.push(`Generator pada metadata: ${evidence.metadata_generator}.`);
    }
    if (modelScores.length > 1) {
        const scores = modelScores.map(([, value]) => value);
        const minScore = Math.min(...scores);
        const maxScore = Math.max(...scores);
        penjelasan.push(
            `Skor antar-model bervariasi dari ${(minScore * 100).toFixed(2)}% hingga ${(maxScore * 100).toFixed(2)}%.`,
        );
    }

    const judulPenjelasan = document.createElement('h3');
    judulPenjelasan.className = 'judul-keterangan-analisis';
    judulPenjelasan.textContent = 'Mengapa Gambar Ini Terdeteksi Demikian?';
    const bagianPenjelasan = buatBagianKeterangan('Penjelasan berdasarkan hasil AI Engine', penjelasan.join(' '));
    const bagianPerModel = modelScores.length
        ? buatBagianKeterangan(
            'Skor dari setiap model',
            modelScores.map(([name, value]) => `${name}: ${(value * 100).toFixed(2)}%`).join(' · '),
        )
        : null;

    hasilAnalisis?.replaceChildren(
        heading,
        badge,
        skor,
        judulPenjelasan,
        bagianPenjelasan,
        ...(bagianPerModel ? [bagianPerModel] : []),
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
            ? 'Deteksi Selesai'
            : 'Deteksi Gambar';
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
        if (payload.code === 'guest_limit_reached') {
            void tampilkanPopupLimitGuest();
        } else if (payload.code === 'free_limit_reached') {
            void tampilkanPopupLimitFree();
        } else if (
            payload.code === 'analysis_failed'
            || payload.status === 'failed'
            || payload.result_label === 'ERROR'
            || request.status >= 500
        ) {
            tampilkanPesan(payload.message || 'Analisis tidak dapat diselesaikan. Silakan coba kembali.', { error: true });
            tampilkanKesalahanAnalisis();
        } else if (request.status === 422) {
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
