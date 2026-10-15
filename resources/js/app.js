/* Titik masuk JavaScript. */
import './modules/navigation.js';
import './modules/auth.js';
import './modules/checker.js';
import './modules/history.js';

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

const tombolPeriodeSubscribe = document.querySelectorAll(
    '[data-subscribe-period]'
);

let periodeAktifSubscribe = 'month';

const daftarHargaSubscribe = {
    month: {
        harga: 'Rp29.000',
        periode: '/bulan',
    },
    year: {
        harga: 'Rp249.000',
        periode: '/tahun',
    },
};

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
    if (!daftarHargaSubscribe[periode]) return;

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
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;

    const tombolBuka = event.target.closest('[data-subscribe-open]');
    const tombolTutup = event.target.closest('[data-subscribe-close]');
    const tombolPeriode = event.target.closest('[data-subscribe-period]');
    const tombolPremium = event.target.closest('#subscribe-premium-select');

    if (tombolBuka) {
        bukaModalSubscribe();
        return;
    }

    if (tombolTutup) {
        tutupModalSubscribe();
        return;
    }

    if (tombolPeriode) {
        ubahPeriodeSubscribe(tombolPeriode.dataset.subscribePeriod);
        return;
    }

    if (tombolPremium) {
        const paket = daftarHargaSubscribe[periodeAktifSubscribe];

        window.alert(
            'Kamu memilih paket Premium ' +
            (periodeAktifSubscribe === 'month' ? 'bulanan' : 'tahunan') +
            ' dengan harga ' + paket.harga + paket.periode +
            '.\\n\\nPembayaran belum diaktifkan. Ini masih berupa simulasi.'
        );
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