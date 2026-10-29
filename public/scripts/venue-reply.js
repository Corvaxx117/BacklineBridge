(() => {
    const form = document.querySelector('[data-venue-reply]');
    if (!form) return;
    const groups = [...form.querySelectorAll('[data-reply-group]')];
    const previousBatches = new Map();
    const statusOf = (row) =>
        row.querySelector('[data-answer-choices] input:checked')?.value ?? 'unknown';
    const rowsOf = (group) => [...group.querySelectorAll('[data-answer-row]')];
    const values = () => JSON.stringify([...new FormData(form)]);
    const baseline = values();
    const initiallyUnsaved = !!form.querySelector('.form-error-summary');
    let dirty = initiallyUnsaved;
    let submitting = false;

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
        if (alternative || row.querySelector('.field-errors li'))
            row.querySelector('[data-answer-details]').open = true;
    }

    function complete(row) {
        return (
            statusOf(row) !== 'unknown' &&
            (statusOf(row) !== 'alternative' || row.querySelector('textarea').value.trim() !== '')
        );
    }

    function update() {
        dirty = initiallyUnsaved || values() !== baseline;
        let answered = 0;
        let total = 0;
        for (const group of groups) {
            const rows = rowsOf(group);
            rows.forEach(updateRow);
            const count = rows.filter(complete).length;
            const missingDetails = rows.filter(
                (row) => statusOf(row) === 'alternative' && !complete(row),
            ).length;
            group.querySelector('[data-group-progress]').textContent =
                `${count} / ${rows.length} renseignées${missingDetails ? ' · alternative à préciser' : ''}`;
            group.classList.toggle('is-complete', count === rows.length);
            const all = group.querySelector('[data-bulk-scope]').value === 'all';
            group.querySelector('[data-bulk-hint]').textContent = all
                ? 'Remplace les disponibilités de tout le groupe. Les précisions sont conservées.'
                : 'Complète les demandes « À confirmer ». Les autres réponses et les précisions sont conservées.';
            const targets = all ? rows : rows.filter((row) => statusOf(row) === 'unknown');
            group.querySelectorAll('[data-bulk-status]').forEach((button) => {
                button.disabled = !targets.length;
            });
            group.querySelector('[data-bulk-undo]').hidden = !previousBatches.has(group);
            answered += count;
            total += rows.length;
        }
        form.querySelector('[data-reply-progress]').textContent =
            `${answered} / ${total} demandes renseignées${dirty ? ' · modifications à enregistrer' : ''}`;
        const meter = form.querySelector('[data-reply-meter]');
        meter.max = Math.max(1, total);
        meter.value = answered;
        meter.hidden = false;
    }

    function setStatus(row, status) {
        row.querySelectorAll('[data-answer-choices] input').forEach((input) => {
            input.checked = input.value === status;
        });
    }

    form.addEventListener('click', (event) => {
        const batch = event.target.closest('[data-bulk-status]');
        const undo = event.target.closest('[data-bulk-undo]');
        if (batch) {
            const group = batch.closest('[data-reply-group]');
            const all = group.querySelector('[data-bulk-scope]').value === 'all';
            const targets = rowsOf(group).filter((row) => all || statusOf(row) === 'unknown');
            const changed = targets.filter((row) => statusOf(row) !== batch.dataset.bulkStatus);
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
            groups.forEach((group) => {
                group.open = true;
            });
        } else if (event.target.closest('[data-reply-collapse]')) {
            groups.forEach((group) => {
                group.open = !!group.querySelector('.field-errors li');
            });
        }
    });
    form.addEventListener('change', (event) => {
        if (event.target.closest('[data-answer-choices]'))
            previousBatches.delete(event.target.closest('[data-reply-group]'));
        update();
    });
    form.addEventListener('input', update);
    form.addEventListener('submit', () => {
        submitting = true;
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty && !submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    form.querySelectorAll('[data-reply-enhancement]').forEach((element) => {
        element.hidden = false;
    });
    update();
})();
