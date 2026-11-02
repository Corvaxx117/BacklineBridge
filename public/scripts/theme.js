/* Exécuté avant les feuilles de style pour appliquer le thème dès le premier affichage. */
(() => {
    const storageKey = 'backlinebridge.theme';
    const choices = ['auto', 'light', 'dark'];
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const root = document.documentElement;
    let preference = 'auto';

    try {
        const saved = window.localStorage.getItem(storageKey);
        if (choices.includes(saved)) {
            preference = saved;
        }
    } catch {
        // Le choix reste utilisable sur cette page si le stockage du navigateur est désactivé.
    }

    function updateControls() {
        const dark = root.dataset.theme === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-checked', String(dark));
            button.title = dark ? 'Activer le mode clair' : 'Activer le mode sombre';
        });
        document.querySelectorAll('[data-theme-auto]').forEach((button) => {
            button.setAttribute('aria-pressed', String(preference === 'auto'));
            button.title =
                preference === 'auto'
                    ? 'Thème automatique activé'
                    : 'Suivre le thème de l’appareil';
        });
        document.querySelectorAll('[data-theme-control]').forEach((control) => {
            control.hidden = false;
        });
        const color = window.getComputedStyle(root).getPropertyValue('--header-background').trim();
        if (color) {
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', color);
        }
    }

    function apply() {
        const previous = root.dataset.theme;
        const theme = preference === 'auto' ? (system.matches ? 'dark' : 'light') : preference;
        root.dataset.theme = theme;
        root.dataset.themePreference = preference;
        if (document.readyState !== 'loading') {
            updateControls();
        }
        if (previous !== theme) {
            window.dispatchEvent(
                new CustomEvent('backlinebridge:themechange', {
                    detail: { theme, preference },
                }),
            );
        }
    }

    apply();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateControls, { once: true });
    } else {
        updateControls();
    }

    function choose(value) {
        preference = value;
        try {
            window.localStorage.setItem(storageKey, preference);
        } catch {
            // La préférence en mémoire reste active pour la page courante.
        }
        apply();
    }

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }
        const button = event.target.closest('[data-theme-toggle], [data-theme-auto]');
        if (button?.matches('[data-theme-toggle]')) {
            choose(root.dataset.theme === 'dark' ? 'light' : 'dark');
        } else if (button?.matches('[data-theme-auto]')) {
            choose('auto');
        }
    });

    system.addEventListener('change', () => {
        if (preference === 'auto') {
            apply();
        }
    });
    window.addEventListener('storage', (event) => {
        if (event.key !== storageKey && event.key !== null) {
            return;
        }
        preference = choices.includes(event.newValue) ? event.newValue : 'auto';
        apply();
    });
})();
