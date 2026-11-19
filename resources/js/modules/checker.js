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
const tombolGantiGambar = document.getElementById('tombol-ganti-gambar');
const tombolAnalisis = document.getElementById('tombol-analisis');
const teksTombolAnalisis = document.getElementById('teks-tombol-analisis');
const tombolUnggahBaru = document.getElementById('tombol-unggah-baru');
const kemajuanAnalisis = document.getElementById('kemajuan-analisis');
const isiKemajuan = document.getElementById('isi-kemajuan');
const lajurProgres = document.querySelector('.lajur-progres[role="progressbar"]');
const nilaiKemajuan = document.getElementById('nilai-kemajuan');
const statusKemajuan = document.getElementById('status-kemajuan');
const tautanRiwayatAntrean = document.getElementById('tautan-riwayat-antrean');
const areaSiapAnalisis = document.getElementById('area-siap-analisis');
const hasilAnalisis = document.getElementById('hasil-analisis');
const pesanChecker = document.getElementById('pesan-checker');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const ringkasanKuota = document.getElementById('ringkasan-kuota');

const ukuranMaksimal = 10 * 1024 * 1024;
const mimeDidukung = ['image/jpeg', 'image/png', 'image/webp'];
let fileTerpilih = null;
let urlPratinjau = '';
let unggahanBerlangsung = false;
let menungguAnalisis = false;

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

const tampilkanPesan = (pesan, { error = false, tautanLogin = false, tautanRiwayat = false } = {}) => {
    if (pesanChecker) {
        pesanChecker.textContent = pesan;
        pesanChecker.hidden = false;
        pesanChecker.classList.toggle('is-error', error);
    }

    if (!hasilAnalisis) return;

    if (tombolUnggahBaru) tombolUnggahBaru.hidden = true;
    hasilAnalisis.replaceChildren();
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = tautanLogin || tautanRiwayat;
    hasilAnalisis.hidden = !tautanLogin && !tautanRiwayat;

    if (tautanLogin && inputGambar?.dataset.authUrl) {
        const loginLink = document.createElement('a');
        loginLink.href = inputGambar.dataset.authUrl;
        loginLink.className = 'btn btn-primary';
        loginLink.textContent = 'Masuk ke akun';
        hasilAnalisis.append(loginLink);
    }

    if (tautanRiwayat && inputGambar?.dataset.historyUrl) {
        const historyLink = document.createElement('a');
        historyLink.href = inputGambar.dataset.historyUrl;
        historyLink.className = 'btn btn-secondary';
        historyLink.textContent = 'Buka Riwayat';
        hasilAnalisis.append(historyLink);
    }
};
const tampilkanPrediksi = (payload) => {
    const hasil = {
        'AI-GENERATED': {
            interpretasi: 'Gambar kemungkinan dibuat dengan AI.',
            kelas: 'status-ai',
        },
        'AI-EDITED': {
            interpretasi: 'Gambar kemungkinan diedit dengan bantuan AI.',
            kelas: 'status-ai',
        },
        REAL: {
            interpretasi: 'Model menilai gambar lebih cenderung berasal dari manusia.',
            kelas: 'status-real',
        },
        UNCERTAIN: {
            interpretasi: 'Model belum cukup yakin untuk menentukan kategori gambar.',
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

    const interpretasi = document.createElement('p');
    interpretasi.className = 'deskripsi-panel';
    interpretasi.textContent = `${hasil.interpretasi} Hasil ini adalah estimasi model.`;

    const rincian = document.createElement('details');
    rincian.className = 'bagian-keterangan-analisis';
    const ringkasan = document.createElement('summary');
    ringkasan.textContent = 'Detail pemeriksaan';
    rincian.append(ringkasan);

    const details = payload.prediction_details && typeof payload.prediction_details === 'object'
        ? payload.prediction_details
        : {};
    const modelScores = details.per_model && typeof details.per_model === 'object'
        ? Object.entries(details.per_model)
            .filter(([, score]) => typeof score === 'number' && Number.isFinite(score))
            .map(([model, score]) => `${model}: ${(score * 100).toFixed(2)}%`)
            .join(' · ')
        : '';
    const metadataEvidence = details.metadata_says_ai === true
        ? `Metadata memuat penanda AI${details.metadata_generator ? ` (${details.metadata_generator})` : ''}.`
        : 'Tidak ditemukan penanda provenance AI yang dikenali pada metadata gambar.';
    const basisEvidence = typeof details.basis === 'string' && details.basis !== ''
        ? `Basis: ${details.basis}.`
        : 'Rincian basis deteksi tidak tersedia.';
    const thresholdEvidence = typeof details.threshold === 'number'
        ? `Operating point ${details.operating_point || 'tidak tersedia'}; ambang AI ${(details.threshold * 100).toFixed(2)}%.`
        : '';
    const waktu = payload.created_at ? new Date(payload.created_at) : null;
    const informasi = [
        payload.id ? `ID analisis: #${payload.id}` : '',
        waktu && !Number.isNaN(waktu.getTime()) ? `Waktu: ${waktu.toLocaleString('id-ID')}` : '',
        basisEvidence,
        thresholdEvidence,
        modelScores ? `Skor per model: ${modelScores}.` : '',
        metadataEvidence,
    ];
    for (const item of informasi.filter(Boolean)) {
        const baris = document.createElement('p');
        baris.textContent = item;
        rincian.append(baris);
    }

    hasilAnalisis?.replaceChildren(heading, badge, skor, interpretasi, rincian);
    if (hasilAnalisis && inputGambar?.dataset.historyUrl && payload.id) {
        const tautan = document.createElement('a');
        const url = new URL(inputGambar.dataset.historyUrl, window.location.href);
        url.searchParams.set('analysis', payload.id);
        tautan.href = url.href;
        tautan.className = 'btn btn-secondary btn-full tombol-riwayat-hasil';
        tautan.textContent = 'Lihat di Riwayat';
        hasilAnalisis.append(tautan);
    }
    if (hasilAnalisis) hasilAnalisis.hidden = false;
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = true;
    if (tombolUnggahBaru) tombolUnggahBaru.hidden = false;

    return true;
};

const perbaruiKuota = (quota) => {
    if (!ringkasanKuota || !quota || typeof quota.used !== 'number') return;

    ringkasanKuota.dataset.used = String(quota.used);
    if (quota.limit === null) {
        ringkasanKuota.textContent = 'Kuota analisis: tanpa batas';
        return;
    }

    const resetText = quota.resets_at
        ? `Kuota bertambah ${new Date(quota.resets_at).toLocaleString('id-ID')}.`
        : '';
    ringkasanKuota.replaceChildren(
        document.createTextNode(`${quota.used} dari ${quota.limit} analisis terpakai dalam 7 hari berjalan`),
    );
    if (resetText) {
        const nextQuota = document.createElement('span');
        nextQuota.textContent = resetText;
        ringkasanKuota.append(nextQuota);
    }
};

const pollingAnalisis = async (analysisId) => {
    const statusUrl = inputGambar?.dataset.statusUrl?.replace(
        '__ANALYSIS_ID__',
        encodeURIComponent(String(analysisId)),
    );

    if (!statusUrl) {
        throw new Error('URL status analisis tidak tersedia.');
    }

    const batasWaktu = Date.now() + 120_000;
    while (Date.now() < batasWaktu) {
        await new Promise((resolve) => window.setTimeout(resolve, 2000));
        const response = await fetch(statusUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: AbortSignal.timeout(10_000),
        });
        const payload = await response.json();

        perbaruiKuota(payload.quota);

        if (!response.ok) {
            throw new Error(payload.message || 'Status analisis tidak dapat diperiksa.');
        }

        if (payload.status === 'completed') {
            aturProgres(100);
            if (tautanRiwayatAntrean) tautanRiwayatAntrean.hidden = true;
            const predictionIsValid = tampilkanPrediksi(payload);
            if (kemajuanAnalisis) kemajuanAnalisis.hidden = true;
            unggahanBerlangsung = false;
            menungguAnalisis = false;
            if (kemajuanAnalisis) kemajuanAnalisis.setAttribute('aria-busy', 'false');
            aturStatusTombol(predictionIsValid);
            return;
        }

        if (payload.status === 'failed') {
            unggahanBerlangsung = false;
            menungguAnalisis = false;
            if (kemajuanAnalisis) {
                kemajuanAnalisis.hidden = true;
                kemajuanAnalisis.setAttribute('aria-busy', 'false');
            }
            aturStatusTombol();
            if (tautanRiwayatAntrean) tautanRiwayatAntrean.hidden = true;
            tampilkanPesan('Analisis gagal diproses. Silakan coba kembali.', { error: true });
            return;
        }

        if (payload.status !== 'pending' && payload.status !== 'processing') {
            throw new Error('Status analisis tidak dikenali.');
        }

        if (statusKemajuan) {
            statusKemajuan.textContent = payload.status === 'pending' ? 'Masuk antrean' : 'Sedang dianalisis';
        }
    }
    throw new Error('Analisis masih diproses. Periksa hasilnya di Riwayat.');
};

const aturProgres = (nilai) => {
    if (isiKemajuan) isiKemajuan.style.width = `${nilai}%`;
    if (nilaiKemajuan) nilaiKemajuan.textContent = `${nilai}%`;
    if (lajurProgres) lajurProgres.setAttribute('aria-valuenow', String(nilai));
};

const aturStatusTombol = (unggahanSelesai = false) => {
    if (!tombolAnalisis) return;

    tombolAnalisis.disabled = unggahanBerlangsung || unggahanSelesai;
    if (tombolGantiGambar) tombolGantiGambar.disabled = unggahanBerlangsung;
    if (teksTombolAnalisis) {
        teksTombolAnalisis.textContent = unggahanSelesai
            ? 'Analisis Selesai'
            : 'Analisis gambar';
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
    if (tautanRiwayatAntrean) tautanRiwayatAntrean.hidden = true;
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
    if (!file || unggahanBerlangsung) return;

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
    if (tautanRiwayatAntrean) tautanRiwayatAntrean.hidden = true;
    if (nilaiKemajuan) nilaiKemajuan.hidden = false;
    if (lajurProgres) lajurProgres.hidden = false;
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
        if (statusKemajuan) statusKemajuan.textContent = 'Memeriksa unggahan…';
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
            unggahanBerlangsung = false;
            aturProgres(100);
            const predictionIsValid = tampilkanPrediksi(payload);
            if (kemajuanAnalisis) kemajuanAnalisis.hidden = true;
            aturStatusTombol(predictionIsValid);
            return;
        }

        if (request.status === 202 && payload.status === 'pending' && payload.id) {
            menungguAnalisis = true;
            if (kemajuanAnalisis) kemajuanAnalisis.setAttribute('aria-busy', 'false');
            if (tautanRiwayatAntrean) tautanRiwayatAntrean.hidden = false;
            if (nilaiKemajuan) nilaiKemajuan.hidden = true;
            if (lajurProgres) lajurProgres.hidden = true;
            perbaruiKuota(payload.quota);
            if (statusKemajuan) statusKemajuan.textContent = 'Masuk antrean';
            pollingAnalisis(payload.id).catch((error) => {
                unggahanBerlangsung = false;
                menungguAnalisis = false;
                if (kemajuanAnalisis) {
                    kemajuanAnalisis.hidden = true;
                    kemajuanAnalisis.setAttribute('aria-busy', 'false');
                }
                aturStatusTombol();
                const pesan = error.message === 'Analisis masih diproses. Periksa hasilnya di Riwayat.'
                    ? 'Analisis masih diproses.'
                    : 'Status belum dapat diperiksa.';
                tampilkanPesan(pesan, { error: true, tautanRiwayat: true });
            });
            return;
        }

        const validationMessage = payload.errors?.image?.[0];
        if (request.status === 422) {
            tampilkanPesan(validationMessage || payload.message || 'File gambar tidak valid.', { error: true });
        } else if (request.status === 429) {
            perbaruiKuota(payload.quota);
            tampilkanPesan(payload.message || 'Kuota analisis 7 hari Anda telah habis.', { error: true });
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
        if (menungguAnalisis) return;
        unggahanBerlangsung = false;
        if (kemajuanAnalisis) kemajuanAnalisis.setAttribute('aria-busy', 'false');
        if (request.status !== 201) aturStatusTombol();
        if (kemajuanAnalisis && request.status !== 201) kemajuanAnalisis.hidden = true;
    });

    request.send(data);
};

tombolPilihGambar?.addEventListener('click', () => inputGambar?.click());
inputGambar?.addEventListener('change', () => pilihFile(inputGambar.files?.[0]));
tombolGantiGambar?.addEventListener('click', () => {
    if (!inputGambar || unggahanBerlangsung) return;
    inputGambar.value = '';
    inputGambar.click();
});
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
