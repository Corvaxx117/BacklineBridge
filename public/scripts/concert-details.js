/* Ouvrir la bonne section après un retour de formulaire ou un lien direct. */
(() => {
    function reveal(hash) {
        let id;
        try {
            id = decodeURIComponent(hash.slice(1));
        } catch {
            return;
        }
        const target = id ? document.getElementById(id) : null;
        if (!target) return;

        let element = target;
        let opened = false;
        while (element) {
            if (element instanceof HTMLDetailsElement) {
                element.open = true;
                opened = true;
            }
            element = element.parentElement;
        }
        if (opened) {
            window.requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
        }
    }

    window.addEventListener('hashchange', () => reveal(window.location.hash));
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const link = event.target.closest('a[href^="#"]');
        if (link && link.hash === window.location.hash) reveal(link.hash);
    });
    reveal(window.location.hash);
})();
