document.querySelectorAll('[data-patch-workspace]').forEach((workspace) => {
    const body = workspace.querySelector('tbody');
    const rows = [...body.rows];
    const order = [...workspace.querySelectorAll('[data-technical-legend] li')].map(
        (item) => item.dataset.technicalFamily,
    );
    const buttons = [...workspace.querySelectorAll('[data-patch-sort]')];
    workspace.querySelector('[data-patch-sort-controls]').hidden = false;
    buttons.forEach((button) =>
        button.addEventListener('click', () => {
            const familyView = button.dataset.patchSort === 'family';
            const sorted = [...rows].sort((left, right) => {
                const familyDifference = familyView
                    ? order.indexOf(left.dataset.technicalFamily) -
                      order.indexOf(right.dataset.technicalFamily)
                    : 0;
                return (
                    familyDifference || Number(left.dataset.channel) - Number(right.dataset.channel)
                );
            });
            let previous;
            sorted.forEach((row) => {
                row.classList.toggle('is-family-start', previous !== row.dataset.technicalFamily);
                previous = row.dataset.technicalFamily;
                body.append(row);
            });
            buttons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        }),
    );
});
