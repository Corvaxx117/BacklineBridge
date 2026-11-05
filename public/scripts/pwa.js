(() => {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
    navigator.serviceWorker
        .register('/service-worker.js', { updateViaCache: 'none' })
        .catch(() => {});
    document.addEventListener('submit', (event) => {
        const action = event.target instanceof HTMLFormElement ? event.target.action : '';
        if (new URL(action || '/', location.origin).pathname !== '/deconnexion') return;
        navigator.serviceWorker.getRegistration('/').then((registration) => {
            registration?.active?.postMessage({ type: 'BB_LOGOUT' });
        });
    });
})();
