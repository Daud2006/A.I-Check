const adminShell = document.getElementById('admin-shell');
const sidebarToggle = document.getElementById('admin-sidebar-toggle');
const sidebar = document.getElementById('admin-sidebar');
const sidebarBackdrop = document.querySelector('[data-admin-sidebar-close]');
const mobileSidebar = window.matchMedia('(max-width: 900px)');
const tabletSidebar = window.matchMedia('(min-width: 901px) and (max-width: 1100px)');

if (adminShell && sidebarToggle && sidebar && sidebarBackdrop) {
    const syncExpandedState = () => {
        const expanded = mobileSidebar.matches
            ? adminShell.classList.contains('is-sidebar-open')
            : tabletSidebar.matches
                ? adminShell.classList.contains('is-sidebar-expanded')
                : !adminShell.classList.contains('is-sidebar-compact');

        sidebarToggle.setAttribute('aria-expanded', String(expanded));
        sidebarToggle.setAttribute(
            'aria-label',
            expanded ? 'Tutup atau perkecil navigasi Admin' : 'Buka navigasi Admin'
        );
    };

    const closeMobileSidebar = () => {
        adminShell.classList.remove('is-sidebar-open');
        syncExpandedState();
    };

    sidebarToggle.addEventListener('click', () => {
        if (mobileSidebar.matches) {
            adminShell.classList.toggle('is-sidebar-open');
        } else if (tabletSidebar.matches) {
            adminShell.classList.toggle('is-sidebar-expanded');
        } else {
            adminShell.classList.toggle('is-sidebar-compact');
        }

        syncExpandedState();
    });

    sidebarBackdrop.addEventListener('click', closeMobileSidebar);

    sidebar.addEventListener('click', (event) => {
        if (mobileSidebar.matches && event.target instanceof Element && event.target.closest('a')) {
            closeMobileSidebar();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileSidebar.matches) {
            closeMobileSidebar();
            sidebarToggle.focus();
        }
    });

    window.addEventListener('resize', syncExpandedState);
    syncExpandedState();
}
