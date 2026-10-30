(() => {
    const toggle = document.querySelector('[data-navigation-toggle]');
    const navigation = document.getElementById(toggle?.getAttribute('aria-controls'));
    if (!toggle || !navigation) return;

    const compact = window.matchMedia('(max-width: 1100px)');
    const label = toggle.querySelector('[data-navigation-label]');

    function setExpanded(expanded) {
        toggle.setAttribute('aria-expanded', String(expanded));
        navigation.dataset.collapsed = String(!expanded);
        label.textContent = expanded ? 'Fermer' : 'Menu';
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
        if (event.key !== 'Escape' || !compact.matches) return;
        if (toggle.getAttribute('aria-expanded') !== 'true') return;
        setExpanded(false);
        toggle.focus();
    });
    document.addEventListener('pointerdown', (event) => {
        if (compact.matches && !event.target.closest('.site-header')) setExpanded(false);
    });
    compact.addEventListener('change', adaptNavigation);
    adaptNavigation();
})();
