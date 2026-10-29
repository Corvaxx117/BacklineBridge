(() => {
    const form = document.querySelector('[data-setup-editor="equipment"]');
    if (!form) return;
    const collection = form.querySelector('[data-collection]');
    const groups = () => [...collection.querySelectorAll('[data-equipment-group]')];
    const rows = () => [...collection.querySelectorAll('[data-collection-item]')];
    let nextIndex =
        Math.max(
            -1,
            ...[...collection.querySelectorAll('[name]')].map((el) =>
                Number(el.name.match(/\[items\]\[(\d+)\]/)?.[1] ?? -1),
            ),
        ) + 1;
    let removed = null;
    let submitting = false;
    const values = () =>
        JSON.stringify([...new FormData(form)].sort(([a], [b]) => a.localeCompare(b)));
    const baseline = values();
    const initiallyUnsaved = !!form.querySelector('.form-error-summary');
    let dirty = initiallyUnsaved;

    function update() {
        const all = rows();
        const categories = groups().map((group) => group.dataset.category);
        const suggestions = form.querySelector('#equipment-categories');
        if (
            JSON.stringify([...suggestions.options].map((option) => option.value)) !==
            JSON.stringify(categories)
        ) {
            suggestions.replaceChildren(
                ...categories.map((category) => new Option(category, category)),
            );
        }
        for (const group of groups()) {
            const count = group.querySelectorAll('[data-collection-item]').length;
            group.querySelector('[data-equipment-count]').textContent =
                `${count} équipement${count > 1 ? 's' : ''}`;
        }
        for (const row of all) {
            const categoryInput = row.querySelector('[name$="[category]"]');
            if (!categoryInput.hasAttribute('list'))
                categoryInput.setAttribute('list', 'equipment-categories');
            const name = row.querySelector('[name$="[label]"]').value.trim() || 'cet équipement';
            row.querySelector('[data-remove-item]').setAttribute('aria-label', `Retirer : ${name}`);
            row.querySelector('legend').textContent = name;
        }
        dirty = initiallyUnsaved || values() !== baseline;
        form.querySelector('[data-equipment-status]').textContent =
            `${all.length} équipements · ${groups().length} catégories${dirty ? ' · modifications à enregistrer' : ''}`;
        form.querySelectorAll('[data-add-item]').forEach((button) => {
            button.disabled = all.length >= 60;
            button.hidden = false;
        });
    }

    function groupFor(category) {
        const existing = groups().find((group) => group.dataset.category === category);
        if (existing) return existing;
        const group = document.createElement('details');
        group.className = 'material-form-group';
        group.dataset.equipmentGroup = '';
        group.dataset.category = category;
        const summary = document.createElement('summary');
        const icon = document.createElement('span');
        icon.className = 'equipment-category-icon';
        icon.append(form.querySelector('[data-equipment-generic-icon]').content.cloneNode(true));
        const title = document.createElement('span');
        title.className = 'material-group-title';
        title.textContent = category;
        const count = document.createElement('span');
        count.className = 'material-group-progress';
        count.dataset.equipmentCount = '';
        const chevron = document.createElement('span');
        chevron.className = 'material-group-chevron';
        chevron.textContent = '⌄';
        chevron.setAttribute('aria-hidden', 'true');
        summary.append(icon, title, count, chevron);
        const items = document.createElement('div');
        items.className = 'equipment-edit-items';
        items.dataset.groupItems = '';
        const actions = document.createElement('div');
        actions.className = 'equipment-group-add';
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'button button-secondary';
        add.dataset.addItem = '';
        add.dataset.category = category;
        add.textContent = `+ Ajouter dans ${category}`;
        actions.append(add);
        group.append(summary, actions, items);
        collection.append(group);
        return group;
    }

    form.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-item]');
        const add = event.target.closest('[data-add-item]');
        const move = event.target.closest('[data-move-item]');
        if (move) {
            const row = move.closest('[data-collection-item]');
            const input = row.querySelector('[name$="[category]"]');
            const category = input.value.trim();
            if (!category) {
                input.focus();
                return;
            }
            input.value = category;
            const group = groupFor(category);
            group.querySelector('[data-group-items]').prepend(row);
            group.open = true;
            row.querySelector('.equipment-category-change').open = false;
            update();
            row.querySelector('[name$="[label]"]').focus();
        } else if (remove) {
            const row = remove.closest('[data-collection-item]');
            removed = { row, parent: row.parentElement, next: row.nextElementSibling };
            form.querySelector('[data-equipment-undo-label]').textContent =
                `${row.querySelector('[name$="[label]"]').value || 'Équipement'} retiré.`;
            row.remove();
            form.querySelector('[data-equipment-undo-panel]').hidden = false;
            update();
        } else if (event.target.closest('[data-equipment-undo]') && removed) {
            removed.parent.insertBefore(
                removed.row,
                removed.next?.isConnected ? removed.next : null,
            );
            removed.parent.closest('details').open = true;
            removed = null;
            form.querySelector('[data-equipment-undo-panel]').hidden = true;
            update();
        } else if (add && rows().length < 60) {
            const category =
                add.dataset.category ??
                (form.querySelector('[data-new-category]').value.trim() || 'Autre');
            const group = groupFor(category);
            const wrapper = document.createElement('div');
            // Le prototype HTML provient de Twig ; les valeurs saisies passent par les propriétés DOM.
            wrapper.innerHTML = collection.dataset.prototype.replaceAll(
                '__name__',
                String(nextIndex++),
            );
            const row = wrapper.firstElementChild;
            row.querySelector('[name$="[category]"]').value = category;
            group.querySelector('[data-group-items]').prepend(row);
            group.open = true;
            update();
            row.querySelector('[name$="[label]"]').focus();
        } else if (event.target.closest('[data-equipment-expand]')) {
            groups().forEach((group) => {
                group.open = true;
            });
        } else if (event.target.closest('[data-equipment-collapse]')) {
            groups().forEach((group) => {
                group.open = !!group.querySelector('.field-errors li');
            });
        }
    });
    form.addEventListener('change', update);
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
    form.querySelectorAll('[data-equipment-enhancement]').forEach((element) => {
        element.hidden = false;
    });
    update();
})();
