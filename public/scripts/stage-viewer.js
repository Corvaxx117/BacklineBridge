(() => {
    document.querySelectorAll('[data-stage-viewer]').forEach((viewer) => {
        const scroll = viewer.querySelector('.stage-scroll');
        const viewport = viewer.querySelector('[data-stage-viewport]');
        const board = viewer.querySelector('.stage-board');
        const controls = viewer.querySelector('[data-stage-zoom-controls]');
        const output = viewer.querySelector('[data-stage-zoom-value]');
        const smaller = viewer.querySelector('[data-stage-zoom="out"]');
        const larger = viewer.querySelector('[data-stage-zoom="in"]');
        let fit = true;
        let zoom = 1;
        let fitZoom = 1;

        function layout() {
            const padding = getComputedStyle(scroll);
            const available =
                scroll.clientWidth -
                parseFloat(padding.paddingLeft) -
                parseFloat(padding.paddingRight);
            if (available <= 0) return;

            const width = Math.max(680, available);
            fitZoom = available / width;
            zoom = fit ? fitZoom : Math.min(2, Math.max(fitZoom, zoom));
            viewer.classList.add('is-enhanced');
            controls.hidden = false;
            board.style.width = `${width}px`;
            board.style.transform = `scale(${zoom})`;
            viewport.style.width = `${Math.ceil(width * zoom)}px`;
            viewport.style.height = `${Math.ceil(board.offsetHeight * zoom)}px`;
            output.textContent = `${Math.round(zoom * 100)} %`;
            smaller.disabled = zoom <= fitZoom + 0.001;
            larger.disabled = zoom >= 2;
            viewer
                .querySelector('[data-stage-zoom="fit"]')
                .setAttribute('aria-pressed', String(fit));
            board.dispatchEvent(new Event('stage:viewportchange', { bubbles: true }));
        }

        controls.addEventListener('click', (event) => {
            const action = event.target.closest('[data-stage-zoom]')?.dataset.stageZoom;
            if (!action) return;
            const previous = zoom;
            const center = {
                x: scroll.scrollLeft + scroll.clientWidth / 2,
                y: scroll.scrollTop + scroll.clientHeight / 2,
            };
            fit = action === 'fit';
            if (!fit) zoom += action === 'in' ? 0.2 : -0.2;
            layout();
            scroll.scrollLeft = fit ? 0 : (center.x * zoom) / previous - scroll.clientWidth / 2;
            scroll.scrollTop = fit ? 0 : (center.y * zoom) / previous - scroll.clientHeight / 2;
        });

        new ResizeObserver(layout).observe(scroll);
        layout();
    });
})();
