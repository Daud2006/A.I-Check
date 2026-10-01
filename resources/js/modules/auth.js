// Menangani modal login dan registrasi agar pengguna bisa beralih mode dengan cepat.
const modalOtorisasi = document.getElementById('modal-otorisasi');
const formulirMasuk = document.getElementById('formulir-masuk');
const formulirDaftar = document.getElementById('formulir-daftar');
const pengalihModeOtorisasi = document.getElementById('pengalih-mode-otorisasi');
const judulModal = document.getElementById('judul-modal');
const deskripsiModal = document.getElementById('deskripsi-modal');
const ikonModal = document.getElementById('ikon-modal');
const teksBawahModal = document.getElementById('teks-bawah-modal');

const bukaModalOtorisasi = (mode = 'login') => {
    if (!modalOtorisasi) return;
    modalOtorisasi.classList.add('is-open');
    modalOtorisasi.setAttribute('aria-hidden', 'false');
    aturModeOtorisasi(mode);
};

const tutupModalOtorisasi = () => {
    if (!modalOtorisasi) return;
    modalOtorisasi.classList.remove('is-open');
    modalOtorisasi.setAttribute('aria-hidden', 'true');
};

const aturModeOtorisasi = (mode) => {
    const modeDaftar = mode === 'register';
    if (formulirMasuk) formulirMasuk.hidden = modeDaftar;
    if (formulirDaftar) formulirDaftar.hidden = !modeDaftar;
    if (judulModal) judulModal.textContent = modeDaftar ? 'Buat akun baru' : 'Selamat datang kembali';
    if (deskripsiModal) deskripsiModal.textContent = modeDaftar ? 'Lengkapi data berikut untuk mulai menggunakan A.I Check.' : 'Masuk untuk menyimpan dan menyinkronkan riwayat analisis.';
    if (teksBawahModal) teksBawahModal.textContent = modeDaftar ? 'Sudah punya akun?' : 'Belum punya akun?';
    if (pengalihModeOtorisasi) {
        pengalihModeOtorisasi.textContent = modeDaftar ? 'Masuk di sini' : 'Daftar gratis';
        pengalihModeOtorisasi.dataset.authMode = modeDaftar ? 'login' : 'register';
    }
    if (ikonModal) ikonModal.innerHTML = modeDaftar
        ? '<svg aria-hidden="true" class="icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>'
        : '<svg aria-hidden="true" class="icon" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>';
};

if (formulirMasuk && formulirDaftar) {
    aturModeOtorisasi('login');
}

document.addEventListener('click', (event) => {
    const pemicuModal = event.target.closest('[data-auth-open]');
    const tombolTutup = event.target.closest('[data-auth-close]');
    const tombolMode = event.target.closest('[data-auth-mode]');
    if (pemicuModal) bukaModalOtorisasi(pemicuModal.dataset.authOpen);
    if (tombolTutup) tutupModalOtorisasi();
    if (tombolMode && tombolMode.id === 'pengalih-mode-otorisasi') aturModeOtorisasi(tombolMode.dataset.authMode);
});

modalOtorisasi?.addEventListener('mousedown', (event) => {
    if (event.target === modalOtorisasi) tutupModalOtorisasi();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') tutupModalOtorisasi();
});

formulirMasuk?.addEventListener('submit', (event) => {
    event.preventDefault();
    const dataFormulir = new FormData(formulirMasuk);
    localStorage.setItem('ai-check-user', JSON.stringify({ name: String(dataFormulir.get('email')).split('@')[0], email: String(dataFormulir.get('email')) }));
    tutupModalOtorisasi();
});

formulirDaftar?.addEventListener('submit', (event) => {
    event.preventDefault();
    const dataFormulir = new FormData(formulirDaftar);
    if (dataFormulir.get('password') !== dataFormulir.get('confirmPassword')) {
        window.alert('Konfirmasi kata sandi belum sama.');
        return;
    }
    localStorage.setItem('ai-check-user', JSON.stringify({ name: String(dataFormulir.get('name')), email: String(dataFormulir.get('email')) }));
    tutupModalOtorisasi();
});
