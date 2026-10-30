import Swal from 'sweetalert2';

// Modal masuk dan daftar.
const modalOtorisasi = document.getElementById('modal-otorisasi');
const formulirMasuk = document.getElementById('formulir-masuk');
const formulirDaftar = document.getElementById('formulir-daftar');
const formulirLupaPassword = document.getElementById('formulir-lupa-password');
const pengalihModeOtorisasi = document.getElementById('pengalih-mode-otorisasi');
const judulModal = document.getElementById('judul-modal');
const deskripsiModal = document.getElementById('deskripsi-modal');
const ikonModal = document.getElementById('ikon-modal');
const teksBawahModal = document.getElementById('teks-bawah-modal');
const pemberitahuanResetPassword = document.getElementById('auth-reset-notice');
const inputEmailResetPassword = formulirLupaPassword?.querySelector('[name="email"]');
const tombolKirimOtp = formulirLupaPassword?.querySelector('[data-auth-send-otp]');
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
    const modeLupaPassword = mode === 'forgot';
    if (formulirMasuk) formulirMasuk.hidden = modeDaftar || modeLupaPassword;
    if (formulirDaftar) formulirDaftar.hidden = !modeDaftar;
    if (formulirLupaPassword) formulirLupaPassword.hidden = !modeLupaPassword;
    if (judulModal) {
        judulModal.textContent = modeLupaPassword
            ? 'Lupa Password'
            : modeDaftar ? 'Buat akun baru' : 'Selamat datang kembali';
    }
    if (deskripsiModal) {
        deskripsiModal.textContent = modeLupaPassword
            ? 'Isi data berikut untuk mengatur ulang password Anda.'
            : modeDaftar
                ? 'Lengkapi data berikut untuk mulai menggunakan A.I Check.'
                : 'Masuk untuk menyimpan dan menyinkronkan riwayat analisis.';
    }
    if (teksBawahModal) {
        teksBawahModal.textContent = modeLupaPassword
            ? 'Sudah Punya Akun?'
            : modeDaftar ? 'Sudah punya akun?' : 'Belum punya akun?';
    }
    if (pengalihModeOtorisasi) {
        pengalihModeOtorisasi.textContent = modeLupaPassword
            ? 'Kembali ke Login'
            : modeDaftar ? 'Masuk di sini' : 'Daftar gratis';
        pengalihModeOtorisasi.dataset.authMode = modeLupaPassword
            ? 'login'
            : modeDaftar ? 'login' : 'register';
    }
    if (ikonModal) ikonModal.innerHTML = modeDaftar
        ? '<svg aria-hidden="true" class="icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>'
        : '<svg aria-hidden="true" class="icon" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>';
    if (pemberitahuanResetPassword) {
        pemberitahuanResetPassword.hidden = true;
        pemberitahuanResetPassword.textContent = '';
    }
};

const tampilkanPemberitahuanResetPassword = (pesan, jenis) => {
    if (!pemberitahuanResetPassword) return;

    pemberitahuanResetPassword.textContent = pesan;
    pemberitahuanResetPassword.classList.toggle('is-error', jenis === 'error');
    pemberitahuanResetPassword.classList.toggle('is-success', jenis === 'success');
    pemberitahuanResetPassword.hidden = false;
};

const kirimPermintaanResetPassword = async (url, data, tombol) => {
    if (!url || !tombol) return false;

    tombol.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(data),
        });
        const hasil = await response.json();

        if (!response.ok) {
            const pesanValidasi = hasil.errors
                ? Object.values(hasil.errors).flat()[0]
                : null;
            tampilkanPemberitahuanResetPassword(
                pesanValidasi || hasil.message || 'Permintaan tidak dapat diproses.',
                'error'
            );
            return false;
        }

        tampilkanPemberitahuanResetPassword(hasil.message, 'success');
        return true;
    } catch {
        tampilkanPemberitahuanResetPassword(
            'Tidak dapat terhubung ke server. Periksa koneksi lokal dan coba lagi.',
            'error'
        );
        return false;
    } finally {
        tombol.disabled = false;
    }
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
    if (tombolMode) aturModeOtorisasi(tombolMode.dataset.authMode);
});

formulirLupaPassword?.addEventListener('submit', (event) => {
    event.preventDefault();
    const dataFormulir = new FormData(formulirLupaPassword);
    const tombolUbahPassword = formulirLupaPassword.querySelector('[data-auth-reset-password]');

    void kirimPermintaanResetPassword(
        formulirLupaPassword.dataset.resetPasswordUrl,
        Object.fromEntries(dataFormulir.entries()),
        tombolUbahPassword
    ).then((berhasil) => {
        if (!berhasil) return;
        formulirLupaPassword.querySelector('[name="otp"]').value = '';
        formulirLupaPassword.querySelector('[name="password"]').value = '';
        formulirLupaPassword.querySelector('[name="password_confirmation"]').value = '';
    });
});

tombolKirimOtp?.addEventListener('click', () => {
    if (!inputEmailResetPassword?.reportValidity()) return;

    void kirimPermintaanResetPassword(
        formulirLupaPassword.dataset.sendOtpUrl,
        { email: inputEmailResetPassword.value },
        tombolKirimOtp
    );
});

modalOtorisasi?.addEventListener('mousedown', (event) => {
    if (event.target === modalOtorisasi) tutupModalOtorisasi();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') tutupModalOtorisasi();
});
