(() => {
    const toggle = document.querySelector('[data-navigation-toggle]');
    const navigation = document.getElementById(toggle?.getAttribute('aria-controls'));
    if (!toggle || !navigation) return;

    const compact = window.matchMedia('(max-width: 1100px)');
    const menus = [...navigation.querySelectorAll('[data-nav-menu]')];
    const label = toggle.querySelector('[data-navigation-label]');

    function setExpanded(expanded) {
        toggle.setAttribute('aria-expanded', String(expanded));
        navigation.dataset.collapsed = String(!expanded);
        label.textContent = expanded ? 'Fermer' : 'Menu';
        if (!expanded)
            menus.forEach((menu) => {
                menu.open = false;
            });
    }

    function adaptNavigation() {
        toggle.hidden = !compact.matches;
        setExpanded(!compact.matches);
    }

    toggle.addEventListener('click', () => {
        setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
    });
    navigation.addEventListener('click', (event) => {
        if (compact.matches && event.target.closest('a')) setExpanded(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openMenu = menus.find((menu) => menu.open);
        if (openMenu) {
            openMenu.open = false;
            openMenu.querySelector('summary').focus();
            return;
        }
        if (!compact.matches) return;
        if (toggle.getAttribute('aria-expanded') !== 'true') return;
        setExpanded(false);
        toggle.focus();
    });
    document.addEventListener('pointerdown', (event) => {
        menus.forEach((menu) => {
            if (!menu.contains(event.target)) menu.open = false;
        });
        if (compact.matches && !event.target.closest('.site-header')) setExpanded(false);
    });
    compact.addEventListener('change', adaptNavigation);
    adaptNavigation();
})();
