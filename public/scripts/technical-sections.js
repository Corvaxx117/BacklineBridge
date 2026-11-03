/* Raccourci facultatif : les sections natives fonctionnent aussi sans JavaScript. */
(() => {
    const container = document.querySelector('[data-sheet-sections]');
    const button = document.querySelector('[data-sheet-toggle]');
    if (!container || !button) return;
    const sections = [...container.querySelectorAll(':scope > [data-sheet-section]')];
    if (!sections.length) return;
    const label = button.querySelector('[data-sheet-toggle-label]');
    const storageKey = `backlinebridge.sheet.${container.dataset.sheetId}.sections`;
    const openedIds = () => sections.filter((section) => section.open).map((section) => section.id);

    function remember(ids) {
        try {
            window.sessionStorage.setItem(storageKey, JSON.stringify(ids));
        } catch {
            // Les sections restent utilisables sans stockage du navigateur.
        }
    }

    try {
        const saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '[]');
        if (!window.location.hash && Array.isArray(saved)) {
            sections.forEach((section) => {
                if (saved.includes(section.id)) section.open = true;
            });
        }
    } catch {
        // Un ancien réglage invalide ne bloque jamais la consultation de la fiche.
    }

    function update() {
        const ids = openedIds();
        const expanded = ids.length === sections.length;
        if (expanded || ids.length === 0) button.setAttribute('aria-expanded', String(expanded));
        else button.removeAttribute('aria-expanded');
        label.textContent = expanded ? 'Tout replier' : 'Tout déplier';
        remember(ids);
    }

    button.setAttribute('aria-controls', sections.map((section) => section.id).join(' '));
    button.addEventListener('click', () => {
        const expanded = !sections.every((section) => section.open);
        sections.forEach((section) => {
            section.open = expanded;
        });
        update();
    });
    sections.forEach((section) => section.addEventListener('toggle', update));
    document.querySelectorAll('[data-sheet-open]').forEach((link) => {
        link.addEventListener('click', () =>
            remember([...new Set([...openedIds(), link.dataset.sheetOpen])]),
        );
    });
    button.hidden = false;
    update();
})();
