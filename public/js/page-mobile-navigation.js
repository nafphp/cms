const mobileNavs = [...document.querySelectorAll('[data-page-mobile-navigation]')];

mobileNavs.forEach((root) => {
    const trigger = root.querySelector('[data-page-mobile-menu-trigger]');
    const drawer = root.querySelector('[data-page-mobile-menu-drawer]');
    const closeButton = root.querySelector('[data-page-mobile-menu-close]');
    const links = [...root.querySelectorAll('[data-page-mobile-menu-link]')];
    const header = root.closest('[data-page-header]');
    const media = window.matchMedia('(max-width: 859px)');

    if (!(trigger instanceof HTMLButtonElement) || !(drawer instanceof HTMLElement)) {
        return;
    }

    const setHeaderHeight = () => {
        if (header instanceof HTMLElement) {
            document.documentElement.style.setProperty('--page-mobile-header-height', `${header.offsetHeight}px`);
        }
    };

    const setOpen = (isOpen) => {
        setHeaderHeight();
        root.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('page-mobile-navigation-open', isOpen);
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    };

    const close = () => setOpen(false);

    trigger.addEventListener('click', () => setOpen(!root.classList.contains('is-open')));
    closeButton?.addEventListener('click', close);
    links.forEach((link) => link.addEventListener('click', close));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            close();
            trigger.focus();
        }
    });

    media.addEventListener('change', (event) => {
        setHeaderHeight();
        if (!event.matches) {
            close();
        }
    });

    window.addEventListener('resize', setHeaderHeight, {passive: true});
    setHeaderHeight();
});
