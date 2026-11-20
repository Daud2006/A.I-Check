import Swal from 'sweetalert2';

const customClass = {
    popup: 'swal-riwayat-popup',
    title: 'swal-riwayat-title',
    htmlContainer: 'swal-riwayat-text',
    actions: 'swal-riwayat-actions',
    confirmButton: 'swal-riwayat-confirm',
    cancelButton: 'swal-riwayat-cancel',
};

if (document.body?.dataset.adminLogoutSuccess === 'true') {
    Swal.fire({
        title: 'Berhasil Keluar',
        text: 'Anda telah berhasil keluar dari akun A.I-Check.',
        icon: 'success',
        timer: 2500,
        timerProgressBar: true,
        showConfirmButton: false,
        buttonsStyling: false,
        customClass,
    });
}

document.querySelectorAll('[data-confirm-admin-logout]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const result = await Swal.fire({
            title: 'Keluar dari Admin Panel?',
            text: 'Apakah Anda yakin ingin mengakhiri sesi administrator?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            reverseButtons: true,
            focusCancel: true,
            customClass,
        });

        if (result.isConfirmed) {
            form.submit();
        }
    });
});

document.querySelectorAll('[data-confirm-ban]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const result = await Swal.fire({
            title: 'Ban Akun Pengguna?',
            text: 'Apakah Anda yakin ingin menonaktifkan akun pengguna ini? Pengguna tidak akan dapat menggunakan akun selama status ban masih aktif.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ban Akun',
            cancelButtonText: 'Tidak',
            buttonsStyling: false,
            reverseButtons: true,
            focusCancel: true,
            customClass,
        });

        if (! result.isConfirmed) return;

        const reason = await Swal.fire({
            title: 'Alasan Ban',
            input: 'textarea',
            inputPlaceholder: 'Tuliskan alasan ban...',
            inputAttributes: { 'aria-label': 'Alasan ban' },
            showCancelButton: true,
            confirmButtonText: 'Simpan Ban',
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            customClass,
            inputValidator: (value) => {
                if (! value || value.trim().length < 3) {
                    return 'Alasan ban wajib diisi minimal 3 karakter.';
                }
            },
        });

        if (! reason.isConfirmed) return;
        let input = form.querySelector('[name="reason"]');
        if (! input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reason';
            form.appendChild(input);
        }
        input.value = reason.value;
        form.submit();
    });
});

document.querySelectorAll('[data-confirm-unban]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const result = await Swal.fire({
            title: 'Pulihkan Akun Pengguna?',
            text: 'Apakah Anda yakin ingin mengaktifkan kembali akun ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Pulihkan',
            cancelButtonText: 'Tidak',
            buttonsStyling: false,
            reverseButtons: true,
            focusCancel: true,
            customClass,
        });

        if (result.isConfirmed) {
            form.submit();
        }
    });
});
