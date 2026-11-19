// Menu seluler.
const tombolMenuMobile = document.getElementById('pembuka-menu-mobile');
const menuMobile = document.getElementById('menu-mobile');
const menuAkun = document.querySelectorAll('details[data-account-menu]');

if (tombolMenuMobile && menuMobile) {
    tombolMenuMobile.addEventListener('click', () => {
        const terbuka = menuMobile.classList.toggle('is-open');
        tombolMenuMobile.setAttribute('aria-expanded', String(terbuka));
        if (!terbuka) menuAkun.forEach((menu) => { menu.open = false; });
    });
}

if (menuAkun.length) {
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || event.target.closest('.swal2-container')) return;

        menuAkun.forEach((menu) => {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        });
    });

    menuAkun.forEach((menu) => {
        menu.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !menu.open) return;

            menu.open = false;
            menu.querySelector('summary').focus();
        });
    });
}
