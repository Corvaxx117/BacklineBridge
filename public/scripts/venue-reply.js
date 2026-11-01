(() => {
    const form = document.querySelector('[data-venue-reply]');
    if (!form) return;

    const groups = [...form.querySelectorAll('[data-reply-group]')];
    const sections = [...form.querySelectorAll('[data-reply-section]')];
    const previousBatches = new Map();
    const editedRows = new Set();
    const rowsOf = (group) => [...group.querySelectorAll('[data-answer-row]')];
    const statusOf = (row) =>
        row.querySelector('[data-answer-choices] input:checked')?.value ?? 'unknown';
    const values = () => JSON.stringify([...new FormData(form)]);
    const baseline = values();
    const initiallyUnsaved = !!form.querySelector('.form-error-summary');
    const hasSavedReply = form.dataset.replySaved === 'true';
    const preferenceKey = `backlinebridge.venue-view:${form.dataset.replyViewKey}`;
    const saveBar = form.querySelector('[data-reply-save-bar]');
    let dirty = initiallyUnsaved;
    let submitting = false;
    let pendingOnly = hasSavedReply && !initiallyUnsaved;

    // Store only the view preference, never the response or the invitation token.
    try {
        const preferred = sessionStorage.getItem(preferenceKey);
        if (!initiallyUnsaved && ['all', 'pending'].includes(preferred))
            pendingOnly = preferred === 'pending';
    } catch {
        // The form also works when browser storage is unavailable.
    }

    function complete(row) {
        return (
            statusOf(row) !== 'unknown' &&
            (statusOf(row) !== 'alternative' || row.querySelector('textarea').value.trim() !== '')
        );
    }

    function hasErrors(row) {
        return !!row.querySelector('.field-errors li');
    }

    function updateRow(row) {
        const status = statusOf(row);
        const alternative = status === 'alternative';
        row.dataset.answerState = status;
        row.querySelector('[data-details-label]').textContent = alternative
            ? 'Décrire l’alternative proposée'
            : 'Préciser le matériel';
        row.querySelector('[data-details-optional]').textContent = alternative
            ? 'requis'
            : 'facultatif';
        row.querySelector('textarea').setAttribute('aria-required', String(alternative));
        if (alternative || hasErrors(row)) row.querySelector('[data-answer-details]').open = true;

        // Keep a line visible while it is being edited; hide it only after saving.
        // Hiding never disables controls: every existing answer stays in the POST.
        row.hidden = pendingOnly && complete(row) && !hasErrors(row) && !editedRows.has(row);
    }

    function batchTargets(group) {
        const allVisible = group.querySelector('[data-bulk-scope]').value === 'all';
        return rowsOf(group).filter(
            (row) => !row.hidden && (allVisible || statusOf(row) === 'unknown'),
        );
    }

    function updateProgress(answered, total, pending) {
        form.querySelector('[data-reply-progress]').textContent =
            `${answered} / ${total} demandes renseignées${dirty ? ' · modifications à enregistrer' : ''}`;
        const meter = form.querySelector('[data-reply-meter]');
        meter.max = Math.max(1, total);
        meter.value = answered;
        meter.hidden = false;
        form.querySelector('[data-reply-pending-count]').textContent = String(pending);
        form.querySelector('[data-reply-save-progress]').textContent =
            `${answered} / ${total} demandes renseignées`;
        form.querySelector('[data-reply-save-status]').textContent = dirty
            ? 'Modifications à enregistrer'
            : hasSavedReply
              ? 'Réponse enregistrée'
              : 'Enregistrez même une réponse partielle';
    }

    function update() {
        dirty = initiallyUnsaved || values() !== baseline;
        let answered = 0;
        let total = 0;
        let pending = 0;
        let visible = 0;
        for (const group of groups) {
            const rows = rowsOf(group);
            rows.forEach(updateRow);
            const count = rows.filter(complete).length;
            const shown = rows.filter((row) => !row.hidden).length;
            const missingDetails = rows.filter(
                (row) => statusOf(row) === 'alternative' && !complete(row),
            ).length;
            group.hidden = shown === 0;
            group.querySelector('[data-group-progress]').textContent =
                `${count} / ${rows.length} renseignées${missingDetails ? ' · alternative à préciser' : ''}`;
            group.querySelector('[data-group-request-count]').textContent = pendingOnly
                ? `${shown} affichée${shown > 1 ? 's' : ''} sur ${rows.length}`
                : `${rows.length} demande${rows.length > 1 ? 's' : ''}`;
            group.classList.toggle('is-complete', count === rows.length);
            group.querySelector('[data-bulk-scope] option[value="all"]').textContent = pendingOnly
                ? 'Toutes les demandes affichées'
                : 'Tout le groupe';
            const all = group.querySelector('[data-bulk-scope]').value === 'all';
            group.querySelector('[data-bulk-hint]').textContent = all
                ? 'Remplace les disponibilités des demandes affichées. Les précisions sont conservées.'
                : 'Complète les demandes affichées « À confirmer ». Les autres réponses sont conservées.';
            group.querySelectorAll('[data-bulk-status]').forEach((button) => {
                button.disabled = !batchTargets(group).length;
            });
            group.querySelector('[data-bulk-undo]').hidden = !previousBatches.has(group);
            answered += count;
            total += rows.length;
            visible += shown;
            pending += rows.filter((row) => !complete(row) || hasErrors(row)).length;
        }
        sections.forEach((section) => {
            section.hidden = !section.querySelector('[data-reply-group]:not([hidden])');
        });
        form.querySelectorAll('[data-reply-filter]').forEach((button) => {
            button.setAttribute(
                'aria-pressed',
                String((button.dataset.replyFilter === 'pending') === pendingOnly),
            );
        });
        form.querySelector('[data-reply-filter-hint]').textContent = pendingOnly
            ? 'Les réponses que vous saisissez restent visibles jusqu’à l’enregistrement.'
            : 'Les réponses déjà renseignées peuvent être revues et modifiées.';
        form.querySelector('[data-reply-empty]').hidden = !pendingOnly || visible > 0;
        updateProgress(answered, total, pending);
    }

    function setStatus(row, status) {
        row.querySelectorAll('[data-answer-choices] input').forEach((input) => {
            input.checked = input.value === status;
        });
        editedRows.add(row);
    }

    function setView(pending) {
        pendingOnly = pending;
        try {
            sessionStorage.setItem(preferenceKey, pending ? 'pending' : 'all');
        } catch {
            // A blocked storage must not prevent filtering or saving.
        }
        update();
        if (pendingOnly) {
            const first = groups.find((group) => !group.hidden);
            if (first) first.open = true;
        }
    }

    form.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-reply-filter]');
        const batch = event.target.closest('[data-bulk-status]');
        const undo = event.target.closest('[data-bulk-undo]');
        if (filter) {
            setView(filter.dataset.replyFilter === 'pending');
        } else if (batch) {
            const group = batch.closest('[data-reply-group]');
            const changed = batchTargets(group).filter(
                (row) => statusOf(row) !== batch.dataset.bulkStatus,
            );
            if (!changed.length) return;
            previousBatches.set(
                group,
                changed.map((row) => ({ row, status: statusOf(row) })),
            );
            changed.forEach((row) => setStatus(row, batch.dataset.bulkStatus));
            update();
        } else if (undo) {
            const group = undo.closest('[data-reply-group]');
            for (const { row, status } of previousBatches.get(group) ?? []) setStatus(row, status);
            previousBatches.delete(group);
            update();
        } else if (event.target.closest('[data-reply-expand]')) {
            groups
                .filter((group) => !group.hidden)
                .forEach((group) => {
                    group.open = true;
                });
        } else if (event.target.closest('[data-reply-collapse]')) {
            groups.forEach((group) => {
                group.open = !!group.querySelector('.field-errors li');
            });
        }
    });
    form.addEventListener('change', (event) => {
        const row = event.target.closest('[data-answer-row]');
        if (row) editedRows.add(row);
        if (event.target.closest('[data-answer-choices]'))
            previousBatches.delete(event.target.closest('[data-reply-group]'));
        update();
    });
    form.addEventListener('input', (event) => {
        const row = event.target.closest('[data-answer-row]');
        if (row) editedRows.add(row);
        update();
    });
    form.addEventListener('submit', () => {
        submitting = true;
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.textContent = 'Enregistrement…';
        });
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty && !submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    // Browsers can restore disabled buttons after a history navigation.
    window.addEventListener('pageshow', () => {
        submitting = false;
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = false;
            button.textContent = 'Enregistrer ma réponse';
        });
    });

    function resizeSaveBar() {
        document.body.style.setProperty('--reply-save-height', `${saveBar.offsetHeight}px`);
    }

    form.querySelectorAll('[data-reply-enhancement]').forEach((element) => {
        element.hidden = false;
    });
    saveBar.hidden = false;
    form.classList.add('is-enhanced');
    document.body.classList.add('guest-has-reply-tools');
    update();
    if (pendingOnly) {
        const first = groups.find((group) => !group.hidden);
        if (first) first.open = true;
    }
    resizeSaveBar();
    if ('ResizeObserver' in window) new ResizeObserver(resizeSaveBar).observe(saveBar);
    window.addEventListener('resize', resizeSaveBar);

    if (initiallyUnsaved) {
        const error = form.querySelector('.field-errors li');
        const field = error?.closest('.field') ?? error?.closest('[data-answer-row]');
        const control = field?.querySelector('input:not([type="hidden"]), textarea, select');
        if (control) {
            for (
                let parent = control.parentElement;
                parent && parent !== form;
                parent = parent.parentElement
            )
                if (parent.tagName === 'DETAILS') parent.open = true;
            control.setAttribute('aria-invalid', 'true');
            control.focus({ preventScroll: true });
            control.scrollIntoView({ block: 'center' });
        } else {
            const summary = form.querySelector('.form-error-summary');
            summary.setAttribute('tabindex', '-1');
            summary.focus();
        }
    }
})();
