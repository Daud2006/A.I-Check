/* Titik masuk JavaScript. */
import './modules/navigation.js';
import './modules/auth.js';
import './modules/checker.js';
import './modules/history.js';
import './modules/admin.js';

/* ================================
    MODAL SUBSCRIBE A.I CHECK
================================ */

const modalSubscribe = document.getElementById('modal-subscribe');

const hargaSubscribe = document.getElementById(
    'subscribe-premium-price'
);

const periodeHargaSubscribe = document.getElementById(
    'subscribe-premium-period'
);

const durasiAksesPremiumSubscribe = document.querySelector(
    '[data-premium-duration]'
);

const tombolPeriodeSubscribe = document.querySelectorAll(
    '[data-subscribe-period]'
);
const tombolPilihPremium = document.getElementById('subscribe-premium-select');
const statusPembayaranSubscribe = document.getElementById('subscribe-payment-status');
const tombolPeriksaPembayaran = document.getElementById('subscribe-payment-check');

let periodeAktifSubscribe = 'month';

const daftarHargaSubscribe = Object.fromEntries(
    Array.from(tombolPeriodeSubscribe).map((tombol) => [
        tombol.dataset.subscribePeriod,
        {
            harga: tombol.dataset.priceLabel,
            periode: tombol.dataset.periodLabel,
            durasi: tombol.dataset.duration,
            plan: tombol.dataset.planSlug,
        },
    ])
);

function bukaModalSubscribe() {
    if (!modalSubscribe) return;

    modalSubscribe.classList.add('is-open');
    modalSubscribe.setAttribute('aria-hidden', 'false');
}

function tutupModalSubscribe() {
    if (!modalSubscribe) return;

    modalSubscribe.classList.remove('is-open');
    modalSubscribe.setAttribute('aria-hidden', 'true');
}

function ubahPeriodeSubscribe(periode) {
    if (!daftarHargaSubscribe[periode]?.plan) return;

    periodeAktifSubscribe = periode;

    tombolPeriodeSubscribe.forEach((tombol) => {
        const aktif = tombol.dataset.subscribePeriod === periode;

        tombol.classList.toggle('is-active', aktif);
        tombol.setAttribute('aria-pressed', String(aktif));
    });

    if (hargaSubscribe) {
        hargaSubscribe.textContent = daftarHargaSubscribe[periode].harga;
    }

    if (periodeHargaSubscribe) {
        periodeHargaSubscribe.textContent =
            daftarHargaSubscribe[periode].periode;
    }

    if (durasiAksesPremiumSubscribe) {
        durasiAksesPremiumSubscribe.textContent =
            'Akses Deteksi Gambar tidak terbatas selama ' +
            daftarHargaSubscribe[periode].durasi;
    }

    if (tombolPilihPremium) {
        tombolPilihPremium.disabled = false;
    }
}

const akunPremium = document.querySelectorAll('[data-premium-account]');

if (akunPremium.length > 0) {
    window.setInterval(() => {
        akunPremium.forEach((akun) => {
            akun.classList.toggle('is-premium-visible');
        });
    }, 5000);
}

function tampilkanStatusPembayaran(message, status = '') {
    if (!statusPembayaranSubscribe) return;

    statusPembayaranSubscribe.textContent = message;
    statusPembayaranSubscribe.classList.toggle('is-error', status === 'failed');
    statusPembayaranSubscribe.classList.toggle('is-success', status === 'paid');
}

async function periksaStatusPembayaran(statusUrl, attempt = 0) {
    if (!statusUrl) return;

    try {
        const response = await fetch(statusUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const result = await response.json();

        if (!response.ok) {
            tampilkanStatusPembayaran(
                result.message || 'Status pembayaran belum dapat diperiksa. Silakan coba kembali.'
            );
            if (tombolPeriksaPembayaran) tombolPeriksaPembayaran.hidden = false;
            return;
        }

        tampilkanStatusPembayaran(result.message, result.status);
        if (result.status === 'paid') {
            if (tombolPeriksaPembayaran) tombolPeriksaPembayaran.hidden = true;
            window.setTimeout(() => window.location.reload(), 1200);
            return;
        }

        if (result.status === 'pending' && attempt < 3) {
            window.setTimeout(() => {
                void periksaStatusPembayaran(statusUrl, attempt + 1);
            }, 2000);
            return;
        }

        if (tombolPeriksaPembayaran) {
            tombolPeriksaPembayaran.dataset.statusUrl = statusUrl;
            tombolPeriksaPembayaran.hidden = result.status !== 'pending';
        }
    } catch {
        tampilkanStatusPembayaran('Koneksi gagal saat memeriksa pembayaran. Silakan coba kembali.');
        if (tombolPeriksaPembayaran) {
            tombolPeriksaPembayaran.dataset.statusUrl = statusUrl;
            tombolPeriksaPembayaran.hidden = false;
        }
    }
}

async function mulaiPembayaran() {
    const paket = daftarHargaSubscribe[periodeAktifSubscribe];
    if (!paket?.plan || !tombolPilihPremium || tombolPilihPremium.disabled) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    tombolPilihPremium.disabled = true;
    if (tombolPeriksaPembayaran) tombolPeriksaPembayaran.hidden = true;
    tampilkanStatusPembayaran('Menyiapkan pembayaran Premium...');

    try {
        const response = await fetch(tombolPilihPremium.dataset.checkoutUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ plan: paket.plan }),
        });
        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = response.status === 401
                ? 'Silakan masuk terlebih dahulu untuk berlangganan Premium.'
                : result.message || 'Pembayaran belum dapat dimulai.';
            tampilkanStatusPembayaran(message, 'failed');
            return;
        }

        if (!window.snap || typeof window.snap.pay !== 'function') {
            tampilkanStatusPembayaran('Checkout Midtrans belum dapat dimuat. Muat ulang halaman lalu coba kembali.', 'failed');
            return;
        }

        const cekStatus = () => {
            if (tombolPeriksaPembayaran) {
                tombolPeriksaPembayaran.dataset.statusUrl = result.status_url;
                tombolPeriksaPembayaran.hidden = false;
            }
            void periksaStatusPembayaran(result.status_url);
        };

        window.snap.pay(result.snap_token, {
            onSuccess: cekStatus,
            onPending: cekStatus,
            onError: cekStatus,
            onClose: cekStatus,
        });
    } catch {
        tampilkanStatusPembayaran('Koneksi gagal saat menyiapkan pembayaran. Silakan coba kembali.', 'failed');
    } finally {
        tombolPilihPremium.disabled = false;
    }
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;

    const tombolBuka = event.target.closest('[data-subscribe-open]');
    const tombolTutup = event.target.closest('[data-subscribe-close]');
    const tombolPeriode = event.target.closest('[data-subscribe-period]');
    const tombolPremium = event.target.closest('#subscribe-premium-select');
    const tombolPeriksaStatus = event.target.closest('#subscribe-payment-check');

    if (tombolBuka) {
        bukaModalSubscribe();
        return;
    }

    if (tombolTutup) {
        tutupModalSubscribe();
        return;
    }

    if (tombolPeriode) {
        if (tombolPeriode.disabled) return;
        ubahPeriodeSubscribe(tombolPeriode.dataset.subscribePeriod);
        return;
    }

    if (tombolPremium) {
        void mulaiPembayaran();
        return;
    }

    if (tombolPeriksaStatus) {
        void periksaStatusPembayaran(tombolPeriksaStatus.dataset.statusUrl);
    }
});

/* Tutup pop-up jika area di luar kartu diklik. */
modalSubscribe?.addEventListener('click', (event) => {
    if (event.target === modalSubscribe) {
        tutupModalSubscribe();
    }
});

/* Tutup pop-up menggunakan tombol Escape. */
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        tutupModalSubscribe();
    }
});