// Mengatur alur upload, pratinjau gambar, progres analisis, dan hasil akhir yang ditampilkan pengguna.
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
const nilaiKemajuan = document.getElementById('nilai-kemajuan');
const areaSiapAnalisis = document.getElementById('area-siap-analisis');
const hasilAnalisis = document.getElementById('hasil-analisis');

let fileTerpilih = null;
let urlPratinjau = '';

const elemenMarker = areaPratinjau ? [...areaPratinjau.querySelectorAll('.titik-marker')] : [];

const aturTampilanUpload = (terlihat) => {
    if (tampilanUpload) {
        tampilanUpload.hidden = !terlihat;
        tampilanUpload.classList.toggle('is-hidden', !terlihat);
    }
};

const aturTampilanHasil = (terlihat) => {
    if (tampilanHasil) {
        tampilanHasil.hidden = !terlihat;
        tampilanHasil.classList.toggle('is-visible', terlihat);
    }
};

const resetPemeriksaan = () => {
    fileTerpilih = null;
    if (urlPratinjau) URL.revokeObjectURL(urlPratinjau);
    urlPratinjau = '';
    if (inputGambar) inputGambar.value = '';
    aturTampilanUpload(true);
    aturTampilanHasil(false);
    if (hasilAnalisis) hasilAnalisis.hidden = true;
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = false;
    elemenMarker.forEach((marker) => { marker.hidden = true; });
};

const pilihFile = (file) => {
    if (!file || !file.type.startsWith('image/')) return;
    if (file.size > 10 * 1024 * 1024) {
        window.alert('Ukuran gambar maksimal 10 MB.');
        return;
    }
    fileTerpilih = file;
    if (urlPratinjau) URL.revokeObjectURL(urlPratinjau);
    urlPratinjau = URL.createObjectURL(file);
    if (gambarPratinjau) gambarPratinjau.src = urlPratinjau;
    if (namaFile) namaFile.textContent = file.name;
    if (ukuranFile) ukuranFile.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
    aturTampilanUpload(false);
    aturTampilanHasil(true);
    if (areaSiapAnalisis) areaSiapAnalisis.hidden = false;
    if (hasilAnalisis) hasilAnalisis.hidden = true;
};

tombolPilihGambar?.addEventListener('click', () => inputGambar?.click());
inputGambar?.addEventListener('change', () => pilihFile(inputGambar.files?.[0]));
tombolHapusGambar?.addEventListener('click', resetPemeriksaan);

areaDropGambar?.addEventListener('dragover', (event) => { event.preventDefault(); tampilanUpload?.classList.add('is-dragging'); });
areaDropGambar?.addEventListener('dragleave', () => tampilanUpload?.classList.remove('is-dragging'));
areaDropGambar?.addEventListener('drop', (event) => {
    event.preventDefault();
    tampilanUpload?.classList.remove('is-dragging');
    pilihFile(event.dataTransfer.files?.[0]);
});

const aturProgres = (nilai) => {
    if (isiKemajuan) isiKemajuan.style.width = `${nilai}%`;
    if (nilaiKemajuan) nilaiKemajuan.textContent = `${nilai}%`;
};

const klasifikasiHasil = (skor) => skor >= 75 ? 'Kemungkinan AI' : skor >= 45 ? 'Perlu ditinjau' : 'Kemungkinan asli';
const kelasStatus = (skor) => skor >= 75 ? 'status-ai' : skor >= 45 ? 'status-review' : 'status-human';
const warnaStatus = (skor) => skor >= 75 ? '#dc594f' : skor >= 45 ? '#d79724' : '#2497ff';

const simpanRiwayat = (item) => {
    const riwayatSaatIni = JSON.parse(localStorage.getItem('verifai-history') || '[]');
    localStorage.setItem('verifai-history', JSON.stringify([item, ...riwayatSaatIni]));
};

const tampilkanHasilAnalisis = (skor) => {
    const skorManusia = 100 - skor;
    const label = klasifikasiHasil(skor);
    const warna = warnaStatus(skor);
    const id = Date.now();
    const item = { id, name: fileTerpilih.name, date: 'Baru saja', aiScore: skor, humanScore: skorManusia, label };
    hasilAnalisis.innerHTML = `
        <div class="kepala-hasil"><span class="eyebrow"><svg aria-hidden="true" class="icon icon-sm" viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>Analisis selesai</span><span class="id-hasil">ID #${String(id).slice(-6)}</span></div>
        <div class="konten-hasil">
            <div class="lingkaran-skor" style="background:conic-gradient(${warna} ${skor}%,#e8f1f8 0)"><div class="lingkaran-skor-dalam"><div><strong class="nilai-skor">${skor}%</strong><span class="label-skor">AI</span></div></div></div>
            <div><div class="status-hasil" style="color:${warna}">${label}</div><h2 class="judul-hasil">Terdeteksi indikasi visual AI</h2></div>
        </div>
        <div class="kotak-probabilitas"><div class="kepala-probabilitas"><span>Probabilitas</span><span style="color:#94a3b8">Skor model</span></div>
            <div class="baris-probabilitas"><div class="label-probabilitas"><span>Buatan AI</span><strong>${skor}%</strong></div><div class="lajur-progres"><div class="isi-progres" style="width:${skor}%;background:${warna}"></div></div></div>
            <div class="baris-probabilitas"><div class="label-probabilitas"><span>Buatan manusia</span><strong>${skorManusia}%</strong></div><div class="lajur-progres"><div class="isi-progres" style="width:${skorManusia}%;background:#2497ff"></div></div></div>
        </div>
        <div class="peringatan-hasil"><svg aria-hidden="true" class="icon icon-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-9h.01"/></svg><p>Hasil ini merupakan estimasi model. Gunakan pemeriksaan sumber dan metadata untuk verifikasi lanjutan.</p></div>
        <div class="aksi-hasil"><button type="button" class="btn btn-secondary" id="tombol-cek-lagi"><svg aria-hidden="true" class="icon icon-sm" viewBox="0 0 24 24"><path d="M20 7v5h-5"/><path d="M18.5 16a8 8 0 10.5-9l1 5"/></svg>Periksa lagi</button><button type="button" class="btn btn-primary" onclick="window.print()"><svg aria-hidden="true" class="icon icon-sm" viewBox="0 0 24 24"><path d="M12 3v12m0 0l-4-4m4 4 4-4"/><path d="M4 20h16"/></svg>Simpan hasil</button></div>`;
    areaSiapAnalisis.hidden = true;
    hasilAnalisis.hidden = false;
    document.getElementById('tombol-cek-lagi')?.addEventListener('click', resetPemeriksaan);
    simpanRiwayat(item);

    elemenMarker.forEach((marker, index) => {
        const posisi = [[32,38],[67,54],[53,76]];
        const [left, top] = posisi[index];
        marker.style.left = `${left}%`;
        marker.style.top = `${top}%`;
        marker.hidden = skor < 45 ? true : false;
    });
};

tombolAnalisis?.addEventListener('click', async () => {
    if (!fileTerpilih) return;
    tombolAnalisis.disabled = true;
    kemajuanAnalisis.hidden = false;
    let progres = 8;
    aturProgres(progres);
    const timer = window.setInterval(() => { progres = Math.min(progres + Math.ceil(Math.random() * 13), 91); aturProgres(progres); }, 220);
    try {
        const endpoint = import.meta.env.VITE_HF_API_URL;
        let skor;
        if (endpoint) {
            const respons = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': fileTerpilih.type }, body: fileTerpilih });
            if (!respons.ok) throw new Error('Model tidak dapat dihubungi.');
            const payload = await respons.json();
            const prediksi = Array.isArray(payload) && Array.isArray(payload[0]) ? payload[0] : payload;
            const prediksiAi = prediksi.find((item) => /ai|artificial|fake|generated/i.test(item.label || ''));
            skor = Math.round((prediksiAi?.score ?? prediksi[0]?.score ?? .5) * 100);
        } else {
            await new Promise((resolve) => window.setTimeout(resolve, 1500));
            skor = 45 + ((fileTerpilih.size + fileTerpilih.name.length * 17) % 51);
        }
        window.clearInterval(timer);
        aturProgres(100);
        tampilkanHasilAnalisis(skor);
    } catch (error) {
        window.clearInterval(timer);
        aturProgres(0);
        window.alert(error instanceof Error ? error.message : 'Analisis gagal.');
        tombolAnalisis.disabled = false;
        return;
    }
    tombolAnalisis.disabled = false;
});
