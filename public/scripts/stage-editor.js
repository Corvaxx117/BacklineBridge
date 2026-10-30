(() => {
    const form = document.querySelector('[data-stage-editor]');
    if (!form) return;

    const MAX_ITEMS = 40;
    const payload = form.querySelector('[data-stage-payload]');
    const board = form.querySelector('[data-stage-board]');
    const shell = form.querySelector('.stage-canvas-shell');
    const context = form.querySelector('[data-stage-context]');
    const properties = form.querySelector('[data-stage-properties]');
    const elementList = form.querySelector('[data-stage-element-list]');
    const nameInput = form.querySelector('[data-stage-name]');
    const notesInput = form.querySelector('[data-stage-notes]');
    const angleInput = form.querySelector('[data-stage-angle]');
    const status = form.querySelector('[data-stage-status]');
    const announce = (message) => {
        form.querySelector('[data-stage-announcement]').textContent = message;
    };
    const palette = new Map(
        [...form.querySelectorAll('[data-add-stage-kind]')].map((button) => [
            button.dataset.addStageKind,
            button,
        ]),
    );
    const nodes = new Map();
    const undoStack = [];
    const redoStack = [];
    let nextId = 1;
    let selectedId = null;
    let textBefore = null;
    let submitting = false;
    let dirty = false;
    const initiallyUnsaved = form.dataset.stageUnsaved === 'true';

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const round = (value, precision = 1) => Math.round(value * 10 ** precision) / 10 ** precision;
    const rotation = (value) => round(((value % 360) + 360) % 360);
    const read = (row, key, fallback = '') =>
        row.querySelector(`[name$="[${key}]"]`)?.value ?? fallback;
    const readNumber = (row, key, fallback) => {
        const value = read(row, key);
        return value !== '' && Number.isFinite(Number(value)) ? Number(value) : fallback;
    };
    let items = [...payload.querySelectorAll('[data-stage-initial-item]')].map((row) => ({
        id: String(nextId++),
        label: read(row, 'label'),
        notes: read(row, 'notes'),
        kind: palette.has(read(row, 'kind')) ? read(row, 'kind') : 'other',
        x: clamp(readNumber(row, 'x', 50), 0, 100),
        y: clamp(readNumber(row, 'y', 50), 0, 100),
        rotation: rotation(readNumber(row, 'rotation', 0)),
        scale: clamp(readNumber(row, 'scale', 1), 0.5, 2.5),
    }));
    const serialize = (list) => JSON.stringify(list.map(({ id, ...item }) => item));
    const saved = serialize(items);
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const capture = () => ({ items: clone(items), selectedId });
    const selected = () => items.find((item) => item.id === selectedId);

    function updateStatus() {
        dirty = initiallyUnsaved || serialize(items) !== saved;
        status.textContent = dirty ? 'Modifications à enregistrer' : 'Aucune modification';
        status.classList.toggle('is-dirty', dirty);
        form.querySelector('[data-stage-action="undo"]').disabled = undoStack.length === 0;
        form.querySelector('[data-stage-action="redo"]').disabled = redoStack.length === 0;
        for (const button of palette.values()) {
            button.disabled = items.length >= MAX_ITEMS;
            button.draggable = false;
        }
        form.querySelector('[data-stage-action="duplicate"]').disabled = items.length >= MAX_ITEMS;
        form.querySelector('[data-stage-count]').textContent = `(${items.length})`;
        form.querySelector('[data-stage-empty]').hidden = items.length !== 0;
    }

    function commit(before) {
        if (serialize(before.items) !== serialize(items)) {
            undoStack.push(before);
            if (undoStack.length > 60) undoStack.shift();
            redoStack.length = 0;
        }
        updateStatus();
    }

    function finishText() {
        if (textBefore) commit(textBefore);
        textBefore = null;
        properties.hidden = true;
        form.querySelector('[data-stage-action="text"]').setAttribute('aria-expanded', 'false');
    }

    function positionContext() {
        if (context.hidden) return;
        const node = nodes.get(selectedId);
        if (!node) return;
        const frame = shell.getBoundingClientRect();
        const target = node.getBoundingClientRect();
        const width = context.offsetWidth;
        const height = context.offsetHeight;
        let top = target.top - frame.top - height - 36;
        if (top < 8) top = target.bottom - frame.top + 26;
        context.style.left = `${clamp(target.left + target.width / 2 - frame.left - width / 2, 8, Math.max(8, frame.width - width - 8))}px`;
        context.style.top = `${clamp(top, 8, Math.max(8, frame.height - height - 8))}px`;
    }

    function updateContext() {
        const item = selected();
        context.hidden = !item;
        if (!item) return;
        const label = context.querySelector('[data-selected-label]');
        label.textContent = item.label || 'Élément';
        label.title = label.textContent;
        if (document.activeElement !== angleInput) angleInput.value = item.rotation;
        context.querySelector('[data-stage-size]').textContent =
            `${Math.round(item.scale * 100)} %`;
        positionContext();
    }

    function paint(item) {
        const node = nodes.get(item.id);
        node.className = `stage-node stage-${item.kind} stage-editable${item.id === selectedId ? ' is-selected' : ''}`;
        node.style.left = `${item.x}%`;
        node.style.top = `${item.y}%`;
        node.style.setProperty('--stage-rotation', `${item.rotation}deg`);
        node.style.setProperty('--stage-scale', item.scale);
        node.querySelector('strong').textContent = item.label || 'Élément';
        node.querySelector('small').textContent = item.notes;
        const hit = node.querySelector('[data-stage-hit]');
        hit.setAttribute('aria-label', `Sélectionner : ${item.label || 'Élément'}`);
        hit.setAttribute('aria-pressed', String(item.id === selectedId));
        node.querySelector('[data-stage-handle="rotate"]').setAttribute(
            'aria-label',
            `Faire pivoter : ${item.label || 'Élément'}`,
        );
        node.querySelector('[data-stage-handle="resize"]').setAttribute(
            'aria-label',
            `Redimensionner : ${item.label || 'Élément'}`,
        );
    }

    function select(id, focus = false) {
        if (id !== selectedId) finishText();
        selectedId = items.some((item) => item.id === id) ? id : null;
        items.forEach(paint);
        elementList.value = selectedId ?? '';
        updateContext();
        if (focus && selectedId)
            nodes.get(selectedId).querySelector('[data-stage-hit]').focus({ preventScroll: true });
    }

    function gesture(event, id, mode) {
        if (event.button !== 0) return;
        event.preventDefault();
        finishText();
        select(id);
        const item = selected();
        const before = capture();
        const start = { ...item };
        const rect = board.getBoundingClientRect();
        const center = {
            x: rect.left + (start.x / 100) * rect.width,
            y: rect.top + (start.y / 100) * rect.height,
        };
        const startPoint = { x: event.clientX, y: event.clientY };
        const initialAngle = Math.atan2(event.clientY - center.y, event.clientX - center.x);
        const initialDistance = Math.max(
            1,
            Math.hypot(event.clientX - center.x, event.clientY - center.y),
        );
        const handle = event.currentTarget;
        handle.focus({ preventScroll: true });
        handle.setPointerCapture(event.pointerId);
        let moved = false;
        function move(e) {
            if (e.pointerId !== event.pointerId) return;
            if (!moved && Math.hypot(e.clientX - startPoint.x, e.clientY - startPoint.y) < 3)
                return;
            moved = true;
            if (mode === 'move') {
                item.x = round(
                    clamp(start.x + ((e.clientX - startPoint.x) / rect.width) * 100, 2, 98),
                );
                item.y = round(
                    clamp(start.y + ((e.clientY - startPoint.y) / rect.height) * 100, 2, 98),
                );
            } else if (mode === 'rotate') {
                let angle =
                    start.rotation +
                    ((Math.atan2(e.clientY - center.y, e.clientX - center.x) - initialAngle) *
                        180) /
                        Math.PI;
                if (e.shiftKey) angle = Math.round(angle / 15) * 15;
                item.rotation = rotation(angle);
            } else {
                item.scale = round(
                    clamp(
                        (start.scale * Math.hypot(e.clientX - center.x, e.clientY - center.y)) /
                            initialDistance,
                        0.5,
                        2.5,
                    ),
                    2,
                );
            }
            paint(item);
            updateContext();
        }
        function end(e) {
            if (e.pointerId !== event.pointerId) return;
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', end);
            handle.removeEventListener('pointercancel', end);
            if (handle.hasPointerCapture(e.pointerId)) handle.releasePointerCapture(e.pointerId);
            if (e.type === 'pointercancel') {
                items = before.items;
                selectedId = before.selectedId;
                render();
            } else {
                commit(before);
                if (moved) announce(`${item.label} : modification effectuée.`);
            }
        }
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
    }

    function createNode(item) {
        const node = document.createElement('div');
        node.dataset.stageNode = item.id;
        const hit = document.createElement('button');
        hit.type = 'button';
        hit.className = 'stage-node-hit';
        hit.dataset.stageHit = '';
        const symbol = document.createElement('span');
        symbol.className = 'stage-symbol';
        symbol.setAttribute('aria-hidden', 'true');
        symbol.append(
            form.querySelector(`[data-stage-icon="${item.kind}"]`).content.cloneNode(true),
        );
        hit.append(symbol, document.createElement('strong'), document.createElement('small'));
        hit.addEventListener('click', () => select(item.id));
        hit.addEventListener('pointerdown', (e) => gesture(e, item.id, 'move'));
        hit.addEventListener('keydown', (e) => {
            const offsets = {
                ArrowLeft: [-1, 0],
                ArrowRight: [1, 0],
                ArrowUp: [0, -1],
                ArrowDown: [0, 1],
            };
            if (!offsets[e.key]) return;
            e.preventDefault();
            change(() => {
                const target = items.find((candidate) => candidate.id === item.id);
                selectedId = item.id;
                const step = e.shiftKey ? 5 : 1;
                target.x = round(clamp(target.x + offsets[e.key][0] * step, 2, 98));
                target.y = round(clamp(target.y + offsets[e.key][1] * step, 2, 98));
            });
        });
        node.append(hit);
        for (const mode of ['rotate', 'resize']) {
            const handle = document.createElement('button');
            handle.type = 'button';
            handle.className = `stage-handle stage-handle-${mode}`;
            handle.dataset.stageHandle = mode;
            handle.tabIndex = -1;
            handle.textContent = mode === 'rotate' ? '↻' : '';
            handle.addEventListener('pointerdown', (e) => {
                e.stopPropagation();
                gesture(e, item.id, mode);
            });
            node.append(handle);
        }
        nodes.set(item.id, node);
        return node;
    }

    function render() {
        for (const [id, node] of nodes) {
            if (!items.some((item) => item.id === id)) {
                node.remove();
                nodes.delete(id);
            }
        }
        const options = [new Option('Choisir un élément…', '')];
        for (const item of items) {
            const node = nodes.get(item.id) ?? createNode(item);
            board.append(node);
            paint(item);
            options.push(new Option(item.label || 'Élément', item.id));
        }
        if (!selected()) selectedId = null;
        elementList.replaceChildren(...options);
        elementList.value = selectedId ?? '';
        updateContext();
        updateStatus();
    }

    function change(operation) {
        finishText();
        const before = capture();
        operation();
        commit(before);
        render();
    }

    function add(kind, point = { x: 50, y: 50 }) {
        if (!palette.has(kind) || items.length >= MAX_ITEMS) {
            announce('Le plan accepte au maximum 40 éléments.');
            return;
        }
        change(() => {
            const item = {
                id: String(nextId++),
                kind,
                label: palette.get(kind).dataset.label,
                notes: '',
                x: round(clamp(point.x, 2, 98)),
                y: round(clamp(point.y, 2, 98)),
                rotation: 0,
                scale: 1,
            };
            items.push(item);
            selectedId = item.id;
        });
        announce('Élément ajouté. Vous pouvez le déplacer ou modifier son nom.');
    }

    function undo(redo = false) {
        finishText();
        const source = redo ? redoStack : undoStack;
        const destination = redo ? undoStack : redoStack;
        if (!source.length) return;
        destination.push(capture());
        const snapshot = source.pop();
        items = clone(snapshot.items);
        selectedId = snapshot.selectedId;
        render();
        announce(redo ? 'Action rétablie.' : 'Action annulée.');
    }

    const actions = {
        undo: () => undo(),
        redo: () => undo(true),
        'rotate-left': () =>
            change(() => {
                selected().rotation = rotation(selected().rotation - 15);
            }),
        'rotate-right': () =>
            change(() => {
                selected().rotation = rotation(selected().rotation + 15);
            }),
        shrink: () =>
            change(() => {
                selected().scale = round(clamp(selected().scale - 0.1, 0.5, 2.5), 2);
            }),
        grow: () =>
            change(() => {
                selected().scale = round(clamp(selected().scale + 0.1, 0.5, 2.5), 2);
            }),
        duplicate: () => {
            if (items.length >= MAX_ITEMS) return;
            change(() => {
                const item = {
                    ...selected(),
                    id: String(nextId++),
                    label: `${Array.from(selected().label).slice(0, 62).join('')} (copie)`,
                    x: clamp(selected().x + 4, 2, 98),
                    y: clamp(selected().y + 4, 2, 98),
                };
                items.push(item);
                selectedId = item.id;
            });
        },
        delete: () =>
            change(() => {
                items = items.filter((item) => item.id !== selectedId);
                selectedId = null;
                announce('Élément retiré. Utilisez Annuler pour le restaurer.');
            }),
        text: () => {
            if (!properties.hidden) {
                finishText();
                positionContext();
                return;
            }
            const item = selected();
            textBefore = capture();
            nameInput.value = item.label;
            notesInput.value = item.notes;
            properties.hidden = false;
            form.querySelector('[data-stage-action="text"]').setAttribute('aria-expanded', 'true');
            positionContext();
            nameInput.focus();
            nameInput.select();
        },
        'finish-text': () => {
            finishText();
            updateContext();
        },
    };
    form.addEventListener('click', (event) => {
        const action = event.target.closest('[data-stage-action]')?.dataset.stageAction;
        if (action && actions[action] && (['undo', 'redo'].includes(action) || selected()))
            actions[action]();
    });
    properties.addEventListener('input', () => {
        const item = selected();
        if (!item) return;
        item.label = nameInput.value;
        item.notes = notesInput.value;
        paint(item);
        updateContext();
        updateStatus();
        elementList.querySelector(`option[value="${item.id}"]`).textContent =
            item.label || 'Élément';
    });
    properties.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target === nameInput) {
            e.preventDefault();
            finishText();
            updateContext();
        }
    });
    angleInput.addEventListener('change', () => {
        const value = Number(angleInput.value);
        if (angleInput.value === '' || !Number.isFinite(value)) {
            angleInput.value = selected().rotation;
            return;
        }
        change(() => {
            selected().rotation = rotation(value);
        });
        angleInput.value = selected().rotation;
    });
    angleInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            angleInput.blur();
        }
    });
    elementList.addEventListener('change', () => select(elementList.value));
    board.addEventListener('click', (e) => {
        if (!e.target.closest('.stage-node')) select(null);
    });

    // Les ajouts utilisent les mêmes événements pointeur que les éléments du plan,
    // pour fonctionner à la souris comme au toucher, sans dépendre du glisser-déposer HTML.
    for (const [kind, button] of palette) {
        let pointerType = 'mouse';
        button.addEventListener('click', (e) => {
            if (e.detail === 0 || pointerType !== 'mouse') add(kind);
        });
        button.addEventListener('pointerdown', (event) => {
            pointerType = event.pointerType;
            // Sur écran tactile, un appui ajoute l’élément ; glisser fait défiler la palette.
            if (pointerType !== 'mouse' || event.button !== 0 || button.disabled) return;
            event.preventDefault();
            button.focus({ preventScroll: true });
            button.setPointerCapture(event.pointerId);
            const start = { x: event.clientX, y: event.clientY };
            let moved = false;
            let ghost = null;
            function point(e) {
                const rect = board.getBoundingClientRect();
                const viewport = form.querySelector('.stage-scroll').getBoundingClientRect();
                const inside =
                    e.clientX >= Math.max(rect.left, viewport.left) &&
                    e.clientX <= Math.min(rect.right, viewport.right) &&
                    e.clientY >= Math.max(rect.top, viewport.top) &&
                    e.clientY <= Math.min(rect.bottom, viewport.bottom);
                return {
                    inside,
                    x: ((e.clientX - rect.left) / rect.width) * 100,
                    y: ((e.clientY - rect.top) / rect.height) * 100,
                };
            }
            function move(e) {
                if (e.pointerId !== event.pointerId) return;
                if (!moved && Math.hypot(e.clientX - start.x, e.clientY - start.y) < 6) return;
                moved = true;
                if (!ghost) {
                    ghost = document.createElement('div');
                    ghost.className = 'stage-palette-ghost';
                    ghost.setAttribute('aria-hidden', 'true');
                    ghost.textContent = button.dataset.label;
                    document.body.append(ghost);
                }
                ghost.style.left = `${e.clientX + 12}px`;
                ghost.style.top = `${e.clientY + 12}px`;
                board.classList.toggle('is-drop-target', point(e).inside);
            }
            function end(e) {
                if (e.pointerId !== event.pointerId) return;
                button.removeEventListener('pointermove', move);
                button.removeEventListener('pointerup', end);
                button.removeEventListener('pointercancel', end);
                if (button.hasPointerCapture(e.pointerId))
                    button.releasePointerCapture(e.pointerId);
                ghost?.remove();
                board.classList.remove('is-drop-target');
                if (e.type === 'pointercancel') return;
                if (!moved) add(kind);
                else {
                    const target = point(e);
                    if (target.inside) add(kind, target);
                }
            }
            button.addEventListener('pointermove', move);
            button.addEventListener('pointerup', end);
            button.addEventListener('pointercancel', end);
        });
    }

    form.addEventListener('keydown', (e) => {
        if (e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undo(e.shiftKey);
        } else if (e.ctrlKey && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            undo(true);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            select(null);
        } else if (selected() && ['Delete', 'Backspace'].includes(e.key)) {
            e.preventDefault();
            actions.delete();
        }
    });
    window.addEventListener('resize', positionContext);
    form.addEventListener('stage:viewportchange', positionContext);
    form.querySelector('.stage-scroll').addEventListener('scroll', positionContext);
    window.addEventListener('beforeunload', (e) => {
        if (dirty && !submitting) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    form.addEventListener('submit', () => {
        finishText();
        const fields = [];
        items.forEach(({ id, ...item }, index) => {
            for (const [name, value] of Object.entries(item)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${payload.dataset.rootName}[${index}][${name}]`;
                input.value = value;
                fields.push(input);
            }
        });
        // Le jeton CSRF et l'empreinte du formulaire restent en dehors de cette collection.
        payload.replaceChildren(...fields);
        submitting = true;
    });
    form.querySelector('[data-stage-action="text"]').setAttribute('aria-expanded', 'false');
    render();
    form.querySelector('[data-stage-save]').disabled = false;
})();
