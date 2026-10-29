document.querySelectorAll('[data-bandsintown-import]').forEach((form) => {
    form.querySelectorAll('[data-import-select]').forEach((button) => {
        button.addEventListener('click', () => {
            form.querySelectorAll('.import-event-choice input[type="checkbox"]').forEach(
                (input, index) => {
                    input.checked = button.dataset.importSelect === 'all' && index < 100;
                },
            );
        });
    });
});
