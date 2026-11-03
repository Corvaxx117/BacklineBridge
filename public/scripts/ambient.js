/* Fond doux à résolution limitée : deux lumières, 20 mises à jour par seconde au maximum. */
(() => {
    const background = document.querySelector('[data-ambient-background]');
    const control = document.querySelector('[data-ambient-control]');
    const toggle = control?.querySelector('[data-ambient-toggle]');
    if (!background || !toggle || typeof window.requestAnimationFrame !== 'function') return;

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    if (!context) return;
    canvas.className = 'ambient-canvas';
    background.append(canvas);

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const printing = window.matchMedia('print');
    const storageKey = 'backlinebridge.ambient';
    const defaultPaused = Boolean(navigator.connection?.saveData);
    const preference = (value) =>
        value === 'paused' ? true : value === 'playing' ? false : defaultPaused;
    let paused = defaultPaused;
    let suspended = false;
    let frame = null;
    let previousTime = null;
    let previousPaint = null;
    let elapsed = 0;
    let width = 0;
    let height = 0;
    let resolution = 1;
    let colors = [];
    const frameInterval = 1000 / 20;

    try {
        paused = preference(window.localStorage.getItem(storageKey));
    } catch {
        // Le contrôle reste utilisable si le stockage du navigateur est indisponible.
    }

    function drawLight(x, y, radius, color, opacity) {
        const gradient = context.createRadialGradient(x, y, 0, x, y, radius);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, 'transparent');
        context.globalAlpha = opacity;
        context.fillStyle = gradient;
        context.fillRect(x - radius, y - radius, radius * 2, radius * 2);
    }

    function paint() {
        if (!width || !height) return;
        context.clearRect(0, 0, width, height);
        const warm = Math.sin((elapsed / 22000) * Math.PI * 2);
        const cool = Math.sin((elapsed / 29000) * Math.PI * 2);
        const radius = Math.min(320, width * 0.55);
        const travelX = Math.min(160, width * 0.22);
        const travelY = Math.min(100, height * 0.12);
        drawLight(
            width * 0.86 + warm * travelX,
            height * 0.35 + warm * travelY,
            radius,
            colors[0],
            0.18 + warm * 0.04,
        );
        drawLight(
            width * 0.14 - cool * travelX,
            height * 0.72 - cool * travelY,
            radius,
            colors[1],
            0.14 - cool * 0.03,
        );
    }

    function resize() {
        const bounds = background.getBoundingClientRect();
        width = bounds.width;
        height = bounds.height;
        // Un fond diffus n’a pas besoin de la résolution de l’écran Retina.
        resolution = Math.min(0.5, 800 / Math.max(width, 1), 600 / Math.max(height, 1));
        canvas.width = Math.max(1, Math.round(width * resolution));
        canvas.height = Math.max(1, Math.round(height * resolution));
        context.setTransform(resolution, 0, 0, resolution, 0, 0);
        paint();
    }

    function updateColors() {
        const style = getComputedStyle(document.documentElement);
        colors = ['--accent-text', '--success-text'].map((name) =>
            style.getPropertyValue(name).trim(),
        );
        paint();
    }

    function tick(time) {
        if (previousTime !== null) elapsed += time - previousTime;
        previousTime = time;
        if (previousPaint === null || time - previousPaint >= frameInterval) {
            paint();
            previousPaint = time;
        }
        frame = window.requestAnimationFrame(tick);
    }

    function stop() {
        if (frame !== null) window.cancelAnimationFrame(frame);
        frame = null;
        previousTime = null;
        previousPaint = null;
    }

    function update() {
        control.hidden = reducedMotion.matches;
        toggle.setAttribute('aria-pressed', String(!paused));
        toggle.title = paused ? 'Animer le fond' : 'Mettre le fond en pause';
        const playing =
            !paused &&
            !reducedMotion.matches &&
            !document.hidden &&
            !printing.matches &&
            !suspended;
        background.dataset.ambientState = playing ? 'playing' : 'paused';
        if (playing) {
            if (frame === null) frame = window.requestAnimationFrame(tick);
        } else {
            stop();
        }
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
    window.addEventListener('backlinebridge:themechange', updateColors);
    updateColors();
    resize();
    new ResizeObserver(resize).observe(background);
    background.dataset.ambientRenderer = 'canvas';
    update();
})();
