document.querySelectorAll('[data-merch-form]').forEach((form) => {
    const action = form.querySelector('[data-stock-action]');
    const saleFields = form.querySelector('[data-sale-fields]');
    if (!action || !saleFields) return;
    const update = () => {
        saleFields.hidden = action.value !== 'sale';
    };
    action.addEventListener('change', update);
    update();
});
