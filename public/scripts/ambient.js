/* Deux animations natives : aucune boucle JavaScript de dessin. */
(() => {
    const background = document.querySelector('[data-ambient-background]');
    const control = document.querySelector('[data-ambient-control]');
    const toggle = control?.querySelector('[data-ambient-toggle]');
    if (!background || !toggle || typeof background.animate !== 'function') return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const printing = window.matchMedia('print');
    const storageKey = 'backlinebridge.ambient';
    const defaultPaused = Boolean(navigator.connection?.saveData);
    const preference = (value) =>
        value === 'paused' ? true : value === 'playing' ? false : defaultPaused;
    let paused = defaultPaused;
    let suspended = false;
    let animations = [];

    try {
        paused = preference(window.localStorage.getItem(storageKey));
    } catch {
        // Le contrôle reste utilisable si le stockage du navigateur est indisponible.
    }

    function createAnimations() {
        const paths = [
            [
                { transform: 'translate(0, 0) scale(1)', opacity: 0.58 },
                { transform: 'translate(-12%, 10%) scale(1.12)', opacity: 0.82 },
                { transform: 'translate(8%, -6%) scale(0.96)', opacity: 0.46 },
                { transform: 'translate(0, 0) scale(1)', opacity: 0.58 },
            ],
            [
                { transform: 'translate(0, 0) scale(1)', opacity: 0.48 },
                { transform: 'translate(14%, -12%) scale(1.08)', opacity: 0.74 },
                { transform: 'translate(-6%, 8%) scale(0.94)', opacity: 0.4 },
                { transform: 'translate(0, 0) scale(1)', opacity: 0.48 },
            ],
        ];
        return [...background.children].map((layer, index) =>
            layer.animate(paths[index], {
                duration: index === 0 ? 26000 : 34000,
                iterations: Infinity,
                easing: 'ease-in-out',
            }),
        );
    }

    function update() {
        control.hidden = reducedMotion.matches;
        toggle.setAttribute('aria-pressed', String(!paused));
        toggle.title = paused ? 'Animer le fond' : 'Mettre le fond en pause';
        if (reducedMotion.matches) {
            animations.forEach((animation) => animation.cancel());
            animations = [];
            return;
        }

        const playing = !paused && !document.hidden && !printing.matches && !suspended;
        if (playing && animations.length === 0) animations = createAnimations();
        animations.forEach((animation) => (playing ? animation.play() : animation.pause()));
    }

    toggle.addEventListener('click', () => {
        paused = !paused;
        try {
            window.localStorage.setItem(storageKey, paused ? 'paused' : 'playing');
        } catch {
            // La préférence reste active sur la page courante.
        }
        update();
    });
    document.addEventListener('visibilitychange', update);
    reducedMotion.addEventListener('change', update);
    printing.addEventListener('change', update);
    window.addEventListener('pagehide', () => {
        suspended = true;
        update();
    });
    window.addEventListener('pageshow', () => {
        suspended = false;
        update();
    });
    window.addEventListener('storage', (event) => {
        if (event.key !== storageKey && event.key !== null) return;
        paused = preference(event.newValue);
        update();
    });
    update();
})();
