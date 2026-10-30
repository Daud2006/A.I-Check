import Swal from 'sweetalert2';

// Modal masuk dan daftar.
const modalOtorisasi = document.getElementById('modal-otorisasi');
const formulirMasuk = document.getElementById('formulir-masuk');
const formulirDaftar = document.getElementById('formulir-daftar');
const pengalihModeOtorisasi = document.getElementById('pengalih-mode-otorisasi');
const judulModal = document.getElementById('judul-modal');
const deskripsiModal = document.getElementById('deskripsi-modal');
const ikonModal = document.getElementById('ikon-modal');
const teksBawahModal = document.getElementById('teks-bawah-modal');
const formulirLogout = document.querySelectorAll('form[data-confirm-logout]');

let konfirmasiLogoutTerbuka = false;
let formulirLogoutDisetujui = null;

if (document.body?.dataset.logoutSuccess === 'true') {
    Swal.fire({
        title: 'Berhasil Keluar',
        text: 'Anda telah berhasil keluar dari akun A.I-Check.',
        icon: 'success',
        timer: 2500,
        timerProgressBar: true,
        showConfirmButton: false,
        buttonsStyling: false,
        customClass: {
            popup: 'swal-riwayat-popup',
            title: 'swal-riwayat-title',
            htmlContainer: 'swal-riwayat-text',
        },
    });
}

formulirLogout.forEach((form) => {
    form.addEventListener('submit', async (event) => {
        if (formulirLogoutDisetujui === form) {
            formulirLogoutDisetujui = null;
            return;
        }

        event.preventDefault();
        if (konfirmasiLogoutTerbuka) return;

        konfirmasiLogoutTerbuka = true;
        const tombolLogout = form.querySelector('button[type="submit"]');
        if (tombolLogout) tombolLogout.disabled = true;

        try {
            const result = await Swal.fire({
                title: 'Keluar dari Akun?',
                text: 'Apakah kamu yakin ingin keluar dari akun A.I-Check?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Keluar',
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

            if (result.isConfirmed) {
                formulirLogoutDisetujui = form;
                form.requestSubmit();
            }
        } finally {
            konfirmasiLogoutTerbuka = false;
            if (tombolLogout) tombolLogout.disabled = false;
        }
    });
});

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
    aturModeOtorisasi(modalOtorisasi?.dataset.authInitialMode || 'login');

    if (modalOtorisasi?.dataset.authOpenOnLoad === 'true') {
        bukaModalOtorisasi(modalOtorisasi.dataset.authInitialMode || 'login');
    }
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
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
