// Menu seluler.
const tombolMenuMobile = document.getElementById('pembuka-menu-mobile');
const menuMobile = document.getElementById('menu-mobile');

if (tombolMenuMobile && menuMobile) {
    tombolMenuMobile.addEventListener('click', () => {
        const terbuka = menuMobile.classList.toggle('is-open');
        tombolMenuMobile.setAttribute('aria-expanded', String(terbuka));
    });
}
