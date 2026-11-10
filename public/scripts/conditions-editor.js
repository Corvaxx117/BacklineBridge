(() => {
    const form = document.querySelector('[data-conditions-editor]');
    if (!form) return;
    const feedback = form.querySelector('[data-conditions-feedback]');
    const status = feedback.querySelector('[data-conditions-status]');
    const cancel = feedback.querySelector('[data-conditions-cancel]');
    let pending = null;
    const reset = () => {
        if (pending) {
            pending.button.textContent = pending.label;
            pending.button.setAttribute('aria-label', pending.ariaLabel);
        }
        pending = null;
        cancel.hidden = true;
    };
    cancel.addEventListener('click', () => {
        reset();
        status.textContent = 'La saisie existante est conservée.';
    });
    form.addEventListener('input', () => {
        if (pending) {
            reset();
            feedback.hidden = true;
        }
    });
    for (const button of form.querySelectorAll('[data-condition-copy]')) {
        const field = document.getElementById(button.dataset.target);
        if (!field || !form.contains(field)) continue;
        button.hidden = false;
        button.addEventListener('click', () => {
            feedback.hidden = false;
            if (
                field.value.trim() &&
                field.value !== button.dataset.value &&
                pending?.button !== button
            ) {
                reset();
                pending = {
                    button,
                    label: button.textContent,
                    ariaLabel: button.getAttribute('aria-label'),
                };
                button.textContent = 'Remplacer ma saisie';
                button.setAttribute(
                    'aria-label',
                    'Remplacer ma saisie par cette proposition Orfeo',
                );
                cancel.hidden = false;
                status.textContent =
                    'Ce champ est déjà renseigné. Clique sur « Remplacer ma saisie » pour confirmer.';
                return;
            }
            reset();
            field.value = button.dataset.value;
            const basis = document.getElementById(button.dataset.basisField);
            if (basis && form.contains(basis)) {
                basis.value = button.dataset.basis;
                basis.dispatchEvent(new Event('input', { bubbles: true }));
            }
            field.dispatchEvent(new Event('input', { bubbles: true }));
            const details = field.closest('details');
            if (details) details.open = true;
            status.textContent =
                'Proposition reprise dans le formulaire. Vérifie puis enregistre les conditions.';
            field.focus({ preventScroll: true });
        });
    }
    if (location.hash === '#orfeo-conditions') {
        const proposals = form.querySelector('[data-conditions-proposals]');
        if (proposals) proposals.open = true;
    }
})();
