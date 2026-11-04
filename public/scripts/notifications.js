(() => {
    const bell = document.querySelector('[data-notification-bell]');
    const badge = bell?.querySelector('[data-notification-count]');
    if (!bell || !badge) return;

    let timer;
    let pending = false;
    let stopped = false;

    function schedule() {
        window.clearTimeout(timer);
        if (!stopped && document.visibilityState !== 'hidden') {
            timer = window.setTimeout(refresh, 60000);
        }
    }

    async function refresh() {
        if (pending || stopped || document.visibilityState === 'hidden') return;
        pending = true;
        try {
            const response = await fetch(bell.dataset.countUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (response.redirected || response.status === 401 || response.status === 403) {
                stopped = true;
                return;
            }
            if (!response.ok) return;
            const { unread } = await response.json();
            if (!Number.isSafeInteger(unread) || unread < 0) return;
            bell.dataset.unread = String(unread);
            bell.setAttribute(
                'aria-label',
                `Notifications, ${unread} ${unread === 1 ? 'non lue' : 'non lues'}`,
            );
            badge.hidden = unread === 0;
            badge.textContent = unread > 99 ? '99+' : String(unread);
        } catch {
            // Garder le dernier compteur connu ; la navigation reste utilisable.
        } finally {
            pending = false;
            schedule();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') window.clearTimeout(timer);
        else refresh();
    });
    window.addEventListener('focus', refresh);
    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearTimeout(timer);
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        stopped = false;
        refresh();
    });
    schedule();
})();
