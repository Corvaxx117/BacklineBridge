/* Une seule requête, avec un retour visible pendant la navigation vers l’aperçu. */
document.querySelectorAll('[data-import-preview]').forEach((form) => {
    const button = form.querySelector('[data-import-submit]');
    const label = form.querySelector('[data-import-label]');
    const status = form.querySelector('[data-import-status]');
    if (!button || !label || !status) return;

    const originalLabel = label.textContent;
    let busy = false;
    const reset = () => {
        busy = false;
        button.disabled = false;
        label.textContent = originalLabel;
        status.hidden = true;
        form.classList.remove('is-loading');
        form.removeAttribute('aria-busy');
    };
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (busy) {
            event.preventDefault();
            return;
        }
        busy = true;
        button.disabled = true;
        label.textContent = 'Chargement en cours…';
        status.hidden = false;
        form.classList.add('is-loading');
        form.setAttribute('aria-busy', 'true');
    });
    // Le navigateur peut restaurer la page précédente avec son état de chargement.
    window.addEventListener('pageshow', reset);
});
